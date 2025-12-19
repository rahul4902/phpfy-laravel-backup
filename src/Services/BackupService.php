<?php

namespace Phpfy\LaravelBackup\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Psr\Log\LoggerInterface;
use Phpfy\LaravelBackup\Tasks\DatabaseBackupTask;
use Phpfy\LaravelBackup\Tasks\FileBackupTask;
use Phpfy\LaravelBackup\Exceptions\BackupException;
use Phpfy\LaravelBackup\Notifications\BackupSuccessful;
use Phpfy\LaravelBackup\Notifications\BackupFailed;
use ZipArchive;

class BackupService
{
    protected array $config;
    protected FilesystemFactory $filesystem;
    protected LoggerInterface $log;
    protected string $tempDirectory;
    protected array $manifest = [];

    /**
     * Create a new BackupService instance.
     */
    public function __construct(array $config, FilesystemFactory $filesystem, LoggerInterface $log)
    {
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->log = $log;
        $this->tempDirectory = $config['temporary_directory'] ?? storage_path('app/backup-temp');

        $this->ensureTempDirectoryExists();
    }

    /**
     * Run the backup process.
     */
    public function run(bool $onlyDb = false, bool $onlyFiles = false): array
    {
        try {
            $this->log->info('Starting backup process...');

            $backupFilename = $this->generateBackupFilename();
            $tempZipPath = $this->tempDirectory . '/' . $backupFilename;

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupException("Could not create backup zip file at: {$tempZipPath}");
            }

            // Backup databases
            if (!$onlyFiles) {
                $this->log->info('Backing up databases...');
                $this->backupDatabases($zip);
            }

            // Backup files
            if (!$onlyDb) {
                $this->log->info('Backing up files...');
                $this->backupFiles($zip);
            }

            // Add manifest
            $this->addManifest($zip);

            $zip->close();

            // Get file size before encryption
            $backupSize = filesize($tempZipPath);

            // Encrypt if enabled
            if ($this->config['encryption']['enabled'] ?? false) {
                $this->log->info('Encrypting backup...');
                $tempZipPath = $this->encryptBackup($tempZipPath);
                $backupFilename .= '.enc';
                $backupSize = filesize($tempZipPath);
            }

            // Copy to destination disks
            $this->log->info('Copying backup to destination disks...');
            $destinations = $this->copyToDestinations($tempZipPath, $backupFilename);

            // Cleanup temporary files
            $this->cleanup($tempZipPath);

            // Send success notification
            $this->notify(new BackupSuccessful($backupFilename, $backupSize, $destinations));

            $this->log->info('Backup completed successfully', [
                'filename' => $backupFilename,
                'size' => $backupSize,
                'destinations' => $destinations,
            ]);

            return [
                'success' => true,
                'filename' => $backupFilename,
                'size' => $backupSize,
                'destinations' => $destinations,
            ];

        } catch (\Exception $e) {
            $this->log->error('Backup failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Send failure notification
            $this->notify(new BackupFailed($e));

            throw new BackupException('Backup failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Backup all configured databases.
     */
    protected function backupDatabases(ZipArchive $zip): void
    {
        $task = new DatabaseBackupTask($this->config, $this->log);
        $dumpFiles = $task->execute($this->tempDirectory);

        foreach ($dumpFiles as $connection => $dumpFile) {
            if (file_exists($dumpFile)) {
                $zip->addFile($dumpFile, 'database/' . basename($dumpFile));
                $this->manifest['databases'][] = [
                    'connection' => $connection,
                    'file' => basename($dumpFile),
                    'size' => filesize($dumpFile),
                ];
            }
        }
    }

    /**
     * Backup all configured files.
     */
    protected function backupFiles(ZipArchive $zip): void
    {
        $task = new FileBackupTask($this->config, $this->log);
        
        // FIX: Pass $tempDirectory to execute() method
        $result = $task->execute($this->tempDirectory);

        // Get the files that were copied to temp directory
        $tempFilesDir = $this->tempDirectory . DIRECTORY_SEPARATOR . 'files';
        
        if (is_dir($tempFilesDir)) {
            $this->addDirectoryToZip($zip, $tempFilesDir, 'files');
        }

        $this->manifest['files'] = [
            'total_count' => $result['files_count'] ?? 0,
            'total_size' => $result['total_size'] ?? 0,
        ];
    }

    /**
     * Recursively add directory to ZIP.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPath): void
    {
        $files = scandir($sourcePath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $sourcePath . DIRECTORY_SEPARATOR . $file;
            $zipFilePath = $zipPath . '/' . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addDirectoryToZip($zip, $filePath, $zipFilePath);
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }

    /**
     * Add manifest file to the backup.
     */
    protected function addManifest(ZipArchive $zip): void
    {
        $this->manifest['backup_name'] = $this->config['backup']['name'] ?? 'laravel-backup';
        $this->manifest['created_at'] = now()->toIso8601String();
        $this->manifest['laravel_version'] = app()->version();
        $this->manifest['php_version'] = PHP_VERSION;

        $manifestJson = json_encode($this->manifest, JSON_PRETTY_PRINT);
        $zip->addFromString('manifest.json', $manifestJson);
    }

    /**
     * Copy backup to all configured destination disks.
     */
    protected function copyToDestinations(string $tempZipPath, string $filename): array
    {
        $destinations = [];
        $disks = $this->config['destination']['disks'] ?? ['local'];
        $prefix = $this->config['destination']['filename_prefix'] ?? '';

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                $path = $prefix . $filename;

                // Stream the file to disk
                $stream = fopen($tempZipPath, 'r');
                if ($stream === false) {
                    throw new BackupException("Could not open temp file for reading: {$tempZipPath}");
                }

                $disk->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $fullPath = $disk->path($path);
                $destinations[] = $fullPath;

                $this->log->info("Backup copied to disk: {$diskName}", [
                    'path' => $fullPath,
                ]);

            } catch (\Exception $e) {
                $this->log->error("Failed to copy backup to disk: {$diskName}", [
                    'error' => $e->getMessage(),
                ]);
                throw new BackupException("Failed to copy backup to disk '{$diskName}': " . $e->getMessage(), 0, $e);
            }
        }

        return $destinations;
    }

    /**
     * Encrypt the backup file using AES-256.
     */
    protected function encryptBackup(string $filePath): string
    {
        $password = $this->config['encryption']['password'] ?? '';

        if (empty($password)) {
            throw new BackupException('Encryption password is not set in config');
        }

        $encryptedPath = $filePath . '.enc';

        // Read the file
        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new BackupException("Could not read file for encryption: {$filePath}");
        }

        // Generate IV for AES-256-CBC
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Encrypt the data
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $password, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new BackupException('Failed to encrypt backup file');
        }

        // Prepend IV to encrypted data
        $encryptedWithIv = $iv . $encrypted;

        // Write encrypted file
        if (file_put_contents($encryptedPath, $encryptedWithIv) === false) {
            throw new BackupException("Could not write encrypted file: {$encryptedPath}");
        }

        // Remove original unencrypted file
        unlink($filePath);

        return $encryptedPath;
    }

    /**
     * Generate a unique backup filename.
     */
    protected function generateBackupFilename(): string
    {
        $name = $this->config['backup']['name'] ?? 'laravel-backup';
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
        $timestamp = now()->format('Y-m-d-H-i-s');

        return "{$name}-{$timestamp}.zip";
    }

    /**
     * Ensure the temporary directory exists.
     */
    protected function ensureTempDirectoryExists(): void
    {
        if (!is_dir($this->tempDirectory)) {
            if (!mkdir($this->tempDirectory, 0755, true) && !is_dir($this->tempDirectory)) {
                throw new BackupException("Could not create temporary directory: {$this->tempDirectory}");
            }
        }

        if (!is_writable($this->tempDirectory)) {
            throw new BackupException("Temporary directory is not writable: {$this->tempDirectory}");
        }
    }

    /**
     * Cleanup temporary files.
     */
    protected function cleanup(string $tempFilePath): void
    {
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }

        // Clean up temporary backup files directory
        $tempFilesDir = $this->tempDirectory . DIRECTORY_SEPARATOR . 'files';
        if (is_dir($tempFilesDir)) {
            $this->deleteDirectory($tempFilesDir);
        }

        // Clean up database directory
        $tempDbDir = $this->tempDirectory . DIRECTORY_SEPARATOR . 'databases';
        if (is_dir($tempDbDir)) {
            $this->deleteDirectory($tempDbDir);
        }

        // Clean up old database dump files
        $files = glob($this->tempDirectory . '/*.sql');
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Clean up old SQLite files
        $files = glob($this->tempDirectory . '/*.sqlite');
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Recursively delete directory.
     */
    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Send notification.
     */
    protected function notify($notification): void
    {
        if (!($this->config['notifications']['enabled'] ?? false)) {
            return;
        }

        try {
            $notifiable = $this->config['notifications']['notifiable'] ?? null;
            if ($notifiable && class_exists($notifiable)) {
                $notifiable::route('mail', $this->config['notifications']['mail']['to'])
                    ->notify($notification);
            }
        } catch (\Exception $e) {
            $this->log->error('Failed to send notification: ' . $e->getMessage());
        }
    }
}
