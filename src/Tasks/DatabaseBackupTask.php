<?php

namespace Phpfy\LaravelBackup\Tasks;

use Psr\Log\LoggerInterface;
use Phpfy\LaravelBackup\Services\DatabaseDumper;
use Phpfy\LaravelBackup\Exceptions\DatabaseDumpException;

class DatabaseBackupTask
{
    protected array $config;
    protected LoggerInterface $log;

    /**
     * Create a new DatabaseBackupTask instance.
     */
    public function __construct(array $config, LoggerInterface $log)
    {
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * Execute the database backup task.
     *
     * @param string $outputPath The directory where dump files will be saved
     * @return array Array of dump files keyed by connection name
     * @throws DatabaseDumpException
     */
    public function execute(string $outputPath): array
    {
        $dumpFiles = [];
        $connections = $this->config['backup']['source']['databases'] ?? ['default'];

        if (empty($connections)) {
            $this->log->warning('No database connections configured for backup');
            return $dumpFiles;
        }

        $this->log->info('Starting database backup task', [
            'connections' => $connections,
            'output_path' => $outputPath,
        ]);

        foreach ($connections as $connection) {
            try {
                $this->log->info("Dumping database connection: {$connection}");

                $startTime = microtime(true);

                // Create database dumper instance
                $dumper = new DatabaseDumper($connection, $this->config['timeout'] ?? 3600);

                // Perform the dump
                $dumpFile = $dumper->dump($outputPath);

                $duration = round(microtime(true) - $startTime, 2);
                $fileSize = file_exists($dumpFile) ? filesize($dumpFile) : 0;

                $dumpFiles[$connection] = $dumpFile;

                $this->log->info("Database '{$connection}' dumped successfully", [
                    'file' => $dumpFile,
                    'size' => $fileSize,
                    'size_human' => $this->formatBytes($fileSize),
                    'duration' => $duration . 's',
                    'driver' => $dumper->getDriver(),
                ]);

            } catch (DatabaseDumpException $e) {
                $this->log->error("Failed to dump database '{$connection}'", [
                    'error' => $e->getMessage(),
                    'connection' => $connection,
                ]);

                // Re-throw to stop backup process
                throw $e;

            } catch (\Exception $e) {
                $this->log->error("Unexpected error dumping database '{$connection}'", [
                    'error' => $e->getMessage(),
                    'connection' => $connection,
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new DatabaseDumpException(
                    "Failed to dump database '{$connection}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $totalSize = array_sum(array_map('filesize', array_filter($dumpFiles, 'file_exists')));

        $this->log->info('Database backup task completed', [
            'total_databases' => count($dumpFiles),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ]);

        return $dumpFiles;
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get configured database connections.
     */
    public function getConnections(): array
    {
        return $this->config['backup']['source']['databases'] ?? ['default'];
    }

    /**
     * Validate that all configured connections exist.
     */
    public function validateConnections(): array
    {
        $connections = $this->getConnections();
        $invalid = [];

        foreach ($connections as $connection) {
            $connectionName = $connection === 'default' ? config('database.default') : $connection;
            $connectionConfig = config("database.connections.{$connectionName}");

            if (empty($connectionConfig)) {
                $invalid[] = $connection;
            }
        }

        return $invalid;
    }
}