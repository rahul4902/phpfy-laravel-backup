<?php

namespace Phpfy\LaravelBackup\Commands;

use Illuminate\Console\Command;
use Phpfy\LaravelBackup\Tasks\CleanupTask;
use Phpfy\LaravelBackup\Notifications\CleanupSuccessful;
use Illuminate\Support\Facades\Notification;

class BackupCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:clean 
                            {--disable-notifications : Disable cleanup notifications}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old backups based on retention strategy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be deleted');
            $this->newLine();
        }

        $this->info('🧹 Cleaning old backups...');
        $this->newLine();

        $config = config('backup');
        $disks = $config['destination']['disks'] ?? ['local'];

        $this->info('Retention Strategy:');
        $strategy = $config['cleanup']['default_strategy'] ?? [];
        $this->table(
            ['Rule', 'Value'],
            [
                ['Keep all backups for', ($strategy['keep_all_backups_for_days'] ?? 7) . ' days'],
                ['Keep daily backups for', ($strategy['keep_daily_backups_for_days'] ?? 16) . ' days'],
                ['Keep weekly backups for', ($strategy['keep_weekly_backups_for_weeks'] ?? 8) . ' weeks'],
                ['Keep monthly backups for', ($strategy['keep_monthly_backups_for_months'] ?? 4) . ' months'],
                ['Keep yearly backups for', ($strategy['keep_yearly_backups_for_years'] ?? 2) . ' years'],
            ]
        );

        $this->newLine();

        try {
            $task = new CleanupTask($config, app('log'));
            $results = $task->execute($disks);

            $totalDeleted = 0;
            $totalFreed = 0;

            $tableData = [];

            foreach ($results as $disk => $result) {
                $tableData[] = [
                    'Disk' => $disk,
                    'Deleted' => $result['deleted_count'] . ' backups',
                    'Freed Space' => $this->formatBytes($result['deleted_size']),
                ];

                $totalDeleted += $result['deleted_count'];
                $totalFreed += $result['deleted_size'];
            }

            if ($totalDeleted > 0) {
                $this->table(['Disk', 'Deleted', 'Freed Space'], $tableData);
                $this->newLine();

                $this->info('✓ Cleanup completed successfully!');
                $this->info("Total deleted: {$totalDeleted} backups");
                $this->info("Total freed space: " . $this->formatBytes($totalFreed));

                // Send notification
                if (!$this->option('disable-notifications') && $config['notifications']['enabled'] ?? false) {
                    $notifiable = $config['notifications']['notifiable'] ?? null;
                    if ($notifiable) {
                        $notifiable::route('mail', $config['notifications']['mail']['to'])
                            ->notify(new CleanupSuccessful($results));
                    }
                }
            } else {
                $this->info('✓ No old backups to clean up.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Cleanup failed!');
            $this->error('Error: ' . $e->getMessage());

            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
