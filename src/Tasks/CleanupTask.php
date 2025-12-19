<?php

namespace Phpfy\LaravelBackup\Tasks;

use Phpfy\LaravelBackup\Services\CleanupService;
use Psr\Log\LoggerInterface;
use Phpfy\LaravelBackup\Notifications\CleanupSuccessful;
use Illuminate\Support\Facades\Notification;

class CleanupTask
{
    protected array $config;
    protected LoggerInterface $log;

    /**
     * Create a new CleanupTask instance.
     */
    public function __construct(array $config, LoggerInterface $log)
    {
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * Execute the cleanup task on specified disks.
     *
     * @param array $disks Array of disk names to clean
     * @return array Results for each disk
     */
    public function execute(array $disks): array
    {
        $this->log->info('Starting cleanup task', [
            'disks' => $disks,
            'strategy' => $this->getRetentionStrategy(),
        ]);

        $results = [];
        $totalDeleted = 0;
        $totalFreed = 0;

        foreach ($disks as $disk) {
            try {
                $this->log->info("Cleaning up backups on disk: {$disk}");

                $startTime = microtime(true);

                // Get cleanup stats before cleanup
                $service = new CleanupService($this->config);
                $statsBefore = $service->getCleanupStats($disk);

                $this->log->debug("Backup stats before cleanup on {$disk}", $statsBefore);

                // Perform cleanup
                $result = $service->cleanup($disk);

                $duration = round(microtime(true) - $startTime, 2);

                $results[$disk] = $result;
                $totalDeleted += $result['deleted_count'];
                $totalFreed += $result['deleted_size'];

                $this->log->info("Cleanup completed for disk: {$disk}", [
                    'deleted_count' => $result['deleted_count'],
                    'deleted_size' => $result['deleted_size'],
                    'deleted_size_human' => $this->formatBytes($result['deleted_size']),
                    'duration' => $duration . 's',
                    'deleted_files' => $result['deleted_files'] ?? [],
                ]);

                // Get stats after cleanup
                $statsAfter = $service->getCleanupStats($disk);
                $this->log->debug("Backup stats after cleanup on {$disk}", $statsAfter);

            } catch (\Exception $e) {
                $this->log->error("Cleanup failed for disk: {$disk}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results[$disk] = [
                    'deleted_count' => 0,
                    'deleted_size' => 0,
                    'deleted_files' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->log->info('Cleanup task completed', [
            'total_deleted' => $totalDeleted,
            'total_freed' => $totalFreed,
            'total_freed_human' => $this->formatBytes($totalFreed),
            'disks_processed' => count($disks),
        ]);

        // Send notification if enabled
        if ($this->config['notifications']['enabled'] ?? false) {
            $this->sendNotification($results);
        }

        return $results;
    }

    /**
     * Get retention strategy from config.
     */
    protected function getRetentionStrategy(): array
    {
        return $this->config['cleanup']['default_strategy'] ?? [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
        ];
    }

    /**
     * Send cleanup notification.
     */
    protected function sendNotification(array $results): void
    {
        try {
            $notifiable = $this->config['notifications']['notifiable'] ?? null;

            if ($notifiable && class_exists($notifiable)) {
                $notifiable::route('mail', $this->config['notifications']['mail']['to'])
                    ->notify(new CleanupSuccessful($results));

                $this->log->info('Cleanup notification sent');
            }
        } catch (\Exception $e) {
            $this->log->error('Failed to send cleanup notification', [
                'error' => $e->getMessage(),
            ]);
        }
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
     * Get cleanup preview (what would be deleted without actually deleting).
     */
    public function preview(array $disks): array
    {
        $this->log->info('Generating cleanup preview', ['disks' => $disks]);

        $preview = [];

        foreach ($disks as $disk) {
            try {
                $service = new CleanupService($this->config);
                $stats = $service->getCleanupStats($disk);

                $preview[$disk] = [
                    'current_stats' => $stats,
                    'retention_strategy' => $this->getRetentionStrategy(),
                ];

            } catch (\Exception $e) {
                $preview[$disk] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $preview;
    }

    /**
     * Check if cleanup is needed for any disk.
     */
    public function isCleanupNeeded(array $disks): bool
    {
        foreach ($disks as $disk) {
            try {
                $service = new CleanupService($this->config);

                if ($service->exceedsStorageLimit($disk)) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->log->warning("Could not check cleanup status for disk: {$disk}");
            }
        }

        return false;
    }

    /**
     * Get configured disks.
     */
    public function getConfiguredDisks(): array
    {
        return $this->config['destination']['disks'] ?? ['local'];
    }
}