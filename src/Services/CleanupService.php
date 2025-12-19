<?php

namespace Phpfy\LaravelBackup\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CleanupService
{
    protected array $config;
    protected int $keepAllBackupsForDays;
    protected int $keepDailyBackupsForDays;
    protected int $keepWeeklyBackupsForWeeks;
    protected int $keepMonthlyBackupsForMonths;
    protected int $keepYearlyBackupsForYears;
    protected int $maxStorageInMegabytes;

    /**
     * Create a new CleanupService instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $strategy = $config['cleanup']['default_strategy'] ?? [];

        $this->keepAllBackupsForDays = $strategy['keep_all_backups_for_days'] ?? 7;
        $this->keepDailyBackupsForDays = $strategy['keep_daily_backups_for_days'] ?? 16;
        $this->keepWeeklyBackupsForWeeks = $strategy['keep_weekly_backups_for_weeks'] ?? 8;
        $this->keepMonthlyBackupsForMonths = $strategy['keep_monthly_backups_for_months'] ?? 4;
        $this->keepYearlyBackupsForYears = $strategy['keep_yearly_backups_for_years'] ?? 2;
        $this->maxStorageInMegabytes = $strategy['delete_oldest_backups_when_using_more_megabytes_than'] ?? 5000;
    }

    /**
     * Clean up old backups on a specific disk.
     */
    public function cleanup(string $disk): array
    {
        $storage = Storage::disk($disk);
        $backups = $this->getBackups($storage);

        if ($backups->isEmpty()) {
            return [
                'deleted_count' => 0,
                'deleted_size' => 0,
                'deleted_files' => [],
            ];
        }

        $backupsToKeep = $this->determineBackupsToKeep($backups);
        $backupsToDelete = $backups->reject(function ($backup) use ($backupsToKeep) {
            return $backupsToKeep->contains('path', $backup['path']);
        });

        // Delete backups
        $deletedFiles = [];
        $deletedSize = 0;

        foreach ($backupsToDelete as $backup) {
            try {
                $storage->delete($backup['path']);
                $deletedFiles[] = $backup['path'];
                $deletedSize += $backup['size'];
            } catch (\Exception $e) {
                // Log error but continue with other deletions
            }
        }

        return [
            'deleted_count' => count($deletedFiles),
            'deleted_size' => $deletedSize,
            'deleted_files' => $deletedFiles,
        ];
    }

    /**
     * Get all backup files from storage.
     */
    protected function getBackups($storage): Collection
    {
        try {
            $files = $storage->allFiles();
        } catch (\Exception $e) {
            return collect([]);
        }

        return collect($files)
            ->filter(function ($file) {
                return str_ends_with($file, '.zip') || 
                       str_ends_with($file, '.zip.enc');
            })
            ->map(function ($file) use ($storage) {
                try {
                    return [
                        'path' => $file,
                        'date' => Carbon::createFromTimestamp($storage->lastModified($file)),
                        'size' => $storage->size($file),
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            })
            ->filter()
            ->sortBy('date')
            ->values();
    }

    /**
     * Determine which backups to keep based on retention strategy.
     */
    protected function determineBackupsToKeep(Collection $backups): Collection
    {
        $now = Carbon::now();
        $toKeep = collect([]);

        // Keep all recent backups
        $recentBackups = $backups->filter(function ($backup) use ($now) {
            return $backup['date']->diffInDays($now) < $this->keepAllBackupsForDays;
        });
        $toKeep = $toKeep->merge($recentBackups);

        // Keep daily backups
        $dailyBackups = $this->getPeriodicBackups(
            $backups,
            $now,
            $this->keepAllBackupsForDays,
            $this->keepDailyBackupsForDays,
            'Y-m-d'
        );
        $toKeep = $toKeep->merge($dailyBackups);

        // Keep weekly backups
        $weeklyBackups = $this->getPeriodicBackups(
            $backups,
            $now,
            $this->keepDailyBackupsForDays,
            $this->keepWeeklyBackupsForWeeks * 7,
            'Y-W'
        );
        $toKeep = $toKeep->merge($weeklyBackups);

        // Keep monthly backups
        $monthlyBackups = $this->getPeriodicBackups(
            $backups,
            $now,
            $this->keepWeeklyBackupsForWeeks * 7,
            $this->keepMonthlyBackupsForMonths * 30,
            'Y-m'
        );
        $toKeep = $toKeep->merge($monthlyBackups);

        // Keep yearly backups
        $yearlyBackups = $this->getPeriodicBackups(
            $backups,
            $now,
            $this->keepMonthlyBackupsForMonths * 30,
            $this->keepYearlyBackupsForYears * 365,
            'Y'
        );
        $toKeep = $toKeep->merge($yearlyBackups);

        // Remove duplicates
        return $toKeep->unique('path');
    }

    /**
     * Get periodic backups (one per period).
     */
    protected function getPeriodicBackups(
        Collection $backups,
        Carbon $now,
        int $minAgeInDays,
        int $maxAgeInDays,
        string $periodFormat
    ): Collection {
        $periodicBackups = collect([]);
        $periods = [];

        foreach ($backups as $backup) {
            $age = $backup['date']->diffInDays($now);

            if ($age >= $minAgeInDays && $age < $maxAgeInDays) {
                $period = $backup['date']->format($periodFormat);

                // Keep only one backup per period (the most recent)
                if (!isset($periods[$period])) {
                    $periods[$period] = $backup;
                    $periodicBackups->push($backup);
                } elseif ($backup['date']->gt($periods[$period]['date'])) {
                    // Replace with newer backup in the same period
                    $periodicBackups = $periodicBackups->reject(function ($b) use ($periods, $period) {
                        return $b['path'] === $periods[$period]['path'];
                    });
                    $periods[$period] = $backup;
                    $periodicBackups->push($backup);
                }
            }
        }

        return $periodicBackups;
    }

    /**
     * Get total storage size used by backups.
     */
    public function getTotalStorageSize(string $disk): int
    {
        $storage = Storage::disk($disk);
        $backups = $this->getBackups($storage);

        return $backups->sum('size');
    }

    /**
     * Check if storage exceeds maximum allowed.
     */
    public function exceedsStorageLimit(string $disk): bool
    {
        $totalSize = $this->getTotalStorageSize($disk);
        $maxSize = $this->maxStorageInMegabytes * 1024 * 1024;

        return $totalSize > $maxSize;
    }

    /**
     * Get cleanup statistics.
     */
    public function getCleanupStats(string $disk): array
    {
        $storage = Storage::disk($disk);
        $backups = $this->getBackups($storage);

        if ($backups->isEmpty()) {
            return [
                'total_backups' => 0,
                'total_size' => 0,
                'oldest_backup' => null,
                'newest_backup' => null,
            ];
        }

        return [
            'total_backups' => $backups->count(),
            'total_size' => $backups->sum('size'),
            'oldest_backup' => $backups->first()['date']->toDateTimeString(),
            'newest_backup' => $backups->last()['date']->toDateTimeString(),
        ];
    }
}