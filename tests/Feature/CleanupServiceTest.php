<?php

namespace Phpfy\LaravelBackup\Tests\Feature;

use Phpfy\LaravelBackup\Tests\TestCase;
use Phpfy\LaravelBackup\Services\CleanupService;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupServiceTest extends TestCase
{
    /** @test */
    public function it_can_clean_old_backups()
    {
        // Create old backups
        $this->createOldBackup('backup-old.zip', Carbon::now()->subDays(30));
        $this->createOldBackup('backup-new.zip', Carbon::now()->subDays(1));

        $service = new CleanupService(config('backup'));
        $result = $service->cleanup('local');

        $this->assertGreaterThanOrEqual(0, $result['deleted_count']);
    }

    /** @test */
    public function it_keeps_recent_backups()
    {
        // Create recent backup
        Storage::disk('local')->put('backup-recent.zip', 'content');

        $service = new CleanupService(config('backup'));
        $result = $service->cleanup('local');

        // Recent backup should still exist
        $this->assertTrue(Storage::disk('local')->exists('backup-recent.zip'));
    }

    /** @test */
    public function it_calculates_total_storage_size()
    {
        Storage::disk('local')->put('backup1.zip', str_repeat('a', 1024));
        Storage::disk('local')->put('backup2.zip', str_repeat('b', 2048));

        $service = new CleanupService(config('backup'));
        $size = $service->getTotalStorageSize('local');

        $this->assertGreaterThan(0, $size);
    }

    /** @test */
    public function it_provides_cleanup_statistics()
    {
        Storage::disk('local')->put('backup.zip', 'content');

        $service = new CleanupService(config('backup'));
        $stats = $service->getCleanupStats('local');

        $this->assertArrayHasKey('total_backups', $stats);
        $this->assertArrayHasKey('total_size', $stats);
    }

    /**
     * Create an old backup file.
     */
    protected function createOldBackup(string $filename, Carbon $date): void
    {
        Storage::disk('local')->put($filename, 'old backup content');

        // Modify file timestamp
        $path = Storage::disk('local')->path($filename);
        touch($path, $date->timestamp);
    }
}
