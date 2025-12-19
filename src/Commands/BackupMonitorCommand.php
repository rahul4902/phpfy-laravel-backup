<?php

namespace Phpfy\LaravelBackup\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Phpfy\LaravelBackup\Notifications\UnhealthyBackup;
use Illuminate\Support\Facades\Notification;

class BackupMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:monitor 
                            {--fail-on-error : Exit with error code if unhealthy backups found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor backup health';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Monitoring backup health...');
        $this->newLine();

        $monitors = config('backup.monitor_backups', []);
        $allHealthy = true;
        $issues = [];

        if (empty($monitors)) {
            $this->warn('⚠ No backup monitors configured.');
            $this->info('Configure monitors in config/backup.php under "monitor_backups"');
            return self::SUCCESS;
        }

        foreach ($monitors as $monitor) {
            $name = $monitor['name'] ?? 'Unnamed';
            $disks = $monitor['disks'] ?? [];

            $this->info("Checking: {$name}");
            $this->newLine();

            foreach ($disks as $disk) {
                try {
                    $storage = Storage::disk($disk);

                    if (!$storage->exists('')) {
                        $this->error("  ✗ Disk '{$disk}' is not accessible");
                        $allHealthy = false;
                        $issues[] = "Disk '{$disk}' for '{$name}' is not accessible";
                        continue;
                    }

                    $files = $storage->allFiles();

                    $backups = collect($files)
                        ->filter(function ($file) {
                            return str_ends_with($file, '.zip') || 
                                   str_ends_with($file, '.zip.enc');
                        })
                        ->sortByDesc(function ($file) use ($storage) {
                            return $storage->lastModified($file);
                        });

                    if ($backups->isEmpty()) {
                        $this->error("  ✗ No backups found on disk: {$disk}");
                        $allHealthy = false;
                        $issues[] = "No backups found for '{$name}' on disk '{$disk}'";
                        continue;
                    }

                    $latestBackup = $backups->first();
                    $lastModified = Carbon::createFromTimestamp($storage->lastModified($latestBackup));
                    $ageInHours = $lastModified->diffInHours(Carbon::now());
                    $ageInDays = $lastModified->diffInDays(Carbon::now());
                    $size = $storage->size($latestBackup);

                    // Check age (default: warn if older than 25 hours)
                    $maxAgeInDays = $monitor['health_checks']['MaximumAgeInDays'] ?? 1;
                    $maxAgeInHours = $maxAgeInDays * 24;

                    if ($ageInHours > $maxAgeInHours) {
                        $this->warn("  ⚠ Latest backup is {$ageInDays} days old (disk: {$disk})");
                        $this->warn("    Expected: Max {$maxAgeInDays} day(s) old");
                        $allHealthy = false;
                        $issues[] = "Latest backup for '{$name}' on disk '{$disk}' is {$ageInDays} days old (exceeds {$maxAgeInDays} day limit)";
                    } else {
                        $this->info("  ✓ Backup age: {$ageInHours} hours (disk: {$disk})");
                    }

                    // Check size
                    $maxSizeInMB = $monitor['health_checks']['MaximumStorageInMegabytes'] ?? 5000;
                    $totalSizeInMB = $size / 1024 / 1024;

                    $this->info("  ✓ Backup size: " . $this->formatBytes($size) . " (disk: {$disk})");
                    $this->info("  ✓ Last backup: " . $lastModified->format('Y-m-d H:i:s'));
                    $this->info("  ✓ Total backups: " . $backups->count());

                } catch (\Exception $e) {
                    $this->error("  ✗ Error checking disk '{$disk}': " . $e->getMessage());
                    $allHealthy = false;
                    $issues[] = "Error checking '{$name}' on disk '{$disk}': " . $e->getMessage();
                }

                $this->newLine();
            }
        }

        // Summary
        if ($allHealthy) {
            $this->info('✓ All backups are healthy!');
            return self::SUCCESS;
        } else {
            $this->warn('⚠ Some backups need attention!');
            $this->newLine();

            $this->error('Issues found:');
            foreach ($issues as $issue) {
                $this->line('  • ' . $issue);
            }

            // Send notification
            $config = config('backup');
            if ($config['notifications']['enabled'] ?? false) {
                $notifiable = $config['notifications']['notifiable'] ?? null;
                if ($notifiable) {
                    $message = "Backup health check failed:\n" . implode("\n", $issues);
                    $notifiable::route('mail', $config['notifications']['mail']['to'])
                        ->notify(new UnhealthyBackup($message));
                }
            }

            return $this->option('fail-on-error') ? self::FAILURE : self::SUCCESS;
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