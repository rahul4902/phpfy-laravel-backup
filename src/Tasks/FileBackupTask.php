<?php

namespace Phpfy\LaravelBackup\Tasks;

use Psr\Log\LoggerInterface;
use Phpfy\LaravelBackup\Services\FileBackupService;
use Phpfy\LaravelBackup\Exceptions\FileBackupException;

class FileBackupTask
{
    protected array $config;
    protected LoggerInterface $log;

    /**
     * Create a new FileBackupTask instance.
     */
    public function __construct(array $config, LoggerInterface $log)
    {
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * Execute the file backup task.
     * Copies files to temp directory with clean relative structure.
     *
     * @param string $tempDir Temporary directory for backup
     * @return array Backup result with file count and size
     * @throws FileBackupException
     */
    public function execute(string $tempDir): array
    {
        $this->log->info('Starting file backup task');

        try {
            $startTime = microtime(true);

            // Create file backup service
            $service = new FileBackupService($this->config);

            // Get all files to backup (returns ['relative/path' => 'absolute/path'])
            $files = $service->getFiles();
            $fileCount = count($files);
            $totalSize = $service->getTotalSize();

            if ($fileCount === 0) {
                $this->log->warning('No files found to backup');
                return [
                    'success' => true,
                    'files_count' => 0,
                    'total_size' => 0,
                ];
            }

            $this->log->info('Files collected for backup', [
                'file_count' => $fileCount,
                'total_size' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize),
                'included_paths' => $service->getIncludes(),
                'excluded_paths' => count($service->getExcludes()) . ' patterns',
            ]);

            // Use app name as root folder for clean structure
            $appName = $this->config['backup']['name'] ?? 'app';
            $filesDir = $tempDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $appName;

            if (!file_exists($filesDir)) {
                mkdir($filesDir, 0777, true);
            }

            $copiedCount = 0;
            $copiedSize = 0;
            $unreadableCount = 0;
            $errors = [];

            // Copy files with relative path structure
            foreach ($files as $relativePath => $absolutePath) {
                try {
                    // Validate file
                    if (!file_exists($absolutePath)) {
                        $unreadableCount++;
                        $errors[] = "File not found: {$relativePath}";
                        $this->log->warning("File not found: {$absolutePath}");
                        continue;
                    }

                    if (!is_readable($absolutePath)) {
                        $unreadableCount++;
                        $errors[] = "File not readable: {$relativePath}";
                        $this->log->warning("File not readable: {$absolutePath}");
                        continue;
                    }

                    // Create destination path with clean relative structure
                    $destinationPath = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                    $destinationDir = dirname($destinationPath);

                    // Create directory structure if needed
                    if (!file_exists($destinationDir)) {
                        if (!mkdir($destinationDir, 0777, true)) {
                            $errors[] = "Failed to create directory: {$relativePath}";
                            $this->log->error("Failed to create directory: {$destinationDir}");
                            continue;
                        }
                    }

                    // Copy file
                    if (copy($absolutePath, $destinationPath)) {
                        $copiedCount++;
                        $copiedSize += filesize($absolutePath);
                    } else {
                        $errors[] = "Failed to copy: {$relativePath}";
                        $this->log->error("Failed to copy file: {$absolutePath} to {$destinationPath}");
                    }

                } catch (\Exception $e) {
                    $unreadableCount++;
                    $errors[] = "Error copying {$relativePath}: {$e->getMessage()}";
                    $this->log->error("Error copying file: {$absolutePath}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            // Log summary
            $this->log->info('File backup task completed', [
                'total_files' => $fileCount,
                'copied_files' => $copiedCount,
                'unreadable_files' => $unreadableCount,
                'copied_size' => $this->formatBytes($copiedSize),
                'duration' => $duration . 's',
                'errors' => count($errors),
            ]);

            // Log errors if any
            if (!empty($errors)) {
                $this->log->warning('File backup completed with errors', [
                    'error_count' => count($errors),
                    'sample_errors' => array_slice($errors, 0, 10),
                ]);
            }

            return [
                'success' => true,
                'files_count' => $copiedCount,
                'total_size' => $copiedSize,
                'total_size_human' => $this->formatBytes($copiedSize),
                'unreadable_count' => $unreadableCount,
                'errors' => $errors,
                'duration' => $duration,
            ];

        } catch (FileBackupException $e) {
            $this->log->error('File backup task failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->log->error('Unexpected error in file backup task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new FileBackupException(
                'File backup task failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get files to backup (for preview/testing).
     *
     * @return array Array with relative path as key, absolute path as value
     */
    public function getFiles(): array
    {
        $service = new FileBackupService($this->config);
        return $service->getFiles();
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        // Don't add decimals for bytes
        if ($unitIndex === 0) {
            return round($size) . ' ' . $units[$unitIndex];
        }

        return number_format($size, $precision) . ' ' . $units[$unitIndex];
    }

    /**
     * Get included paths from configuration.
     */
    public function getIncludedPaths(): array
    {
        return $this->config['backup']['source']['files']['include'] ?? [];
    }

    /**
     * Get excluded paths from configuration.
     */
    public function getExcludedPaths(): array
    {
        return $this->config['backup']['source']['files']['exclude'] ?? [];
    }

    /**
     * Estimate backup size without actually copying files.
     */
    public function estimateSize(): array
    {
        $service = new FileBackupService($this->config);

        $totalSize = $service->getTotalSize();

        return [
            'file_count' => $service->getFileCount(),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Validate that included paths exist.
     */
    public function validatePaths(): array
    {
        $includedPaths = $this->getIncludedPaths();
        $invalid = [];

        foreach ($includedPaths as $path) {
            if (!file_exists($path)) {
                $invalid[] = $path;
            }
        }

        return $invalid;
    }

    /**
     * Get total size of files to backup.
     */
    public function getTotalSize(): int
    {
        $service = new FileBackupService($this->config);
        return $service->getTotalSize();
    }

    /**
     * Get file count.
     */
    public function getFileCount(): int
    {
        $service = new FileBackupService($this->config);
        return $service->getFileCount();
    }
}
