<?php

namespace Phpfy\LaravelBackup\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:list 
                            {disk? : The disk to list backups from}
                            {--all : List backups from all configured disks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = $this->argument('disk');
        $allDisks = $this->option('all');

        if ($allDisks) {
            $disks = config('backup.destination.disks', ['local']);
        } else {
            $disks = [$diskName ?? config('backup.destination.disks')[0] ?? 'local'];
        }

        $totalBackups = 0;

        foreach ($disks as $disk) {
            $this->info("📂 Listing backups from disk: {$disk}");
            $this->newLine();

            try {
                $storage = Storage::disk($disk);

                if (!$storage->exists('')) {
                    $this->warn("Disk '{$disk}' is not accessible or doesn't exist.");
                    $this->newLine();
                    continue;
                }

                $files = $storage->allFiles();

                $backups = collect($files)
                    ->filter(function ($file) {
                        return str_ends_with($file, '.zip') || 
                               str_ends_with($file, '.zip.enc');
                    })
                    ->map(function ($file) use ($storage) {
                        return [
                            'Name' => basename($file),
                            'Size' => $this->formatBytes($storage->size($file)),
                            'Date' => Carbon::createFromTimestamp($storage->lastModified($file))
                                ->format('Y-m-d H:i:s'),
                            'Age' => Carbon::createFromTimestamp($storage->lastModified($file))
                                ->diffForHumans(),
                        ];
                    })
                    ->sortByDesc('Date')
                    ->values()
                    ->all();

                if (empty($backups)) {
                    $this->warn('No backups found on this disk.');
                    $this->newLine();
                    continue;
                }

                $this->table(
                    ['Name', 'Size', 'Date', 'Age'],
                    $backups
                );

                $totalBackups += count($backups);

                $this->info('Total backups on ' . $disk . ': ' . count($backups));
                $this->newLine();

            } catch (\Exception $e) {
                $this->error("Error accessing disk '{$disk}': " . $e->getMessage());
                $this->newLine();
            }
        }

        if ($totalBackups === 0) {
            $this->warn('⚠ No backups found on any disk.');
        } else {
            $this->info("✓ Total backups found: {$totalBackups}");
        }

        return self::SUCCESS;
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
