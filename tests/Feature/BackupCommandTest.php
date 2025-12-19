<?php

namespace Phpfy\LaravelBackup\Tests\Feature;

use Phpfy\LaravelBackup\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class BackupCommandTest extends TestCase
{
    /** @test */
    public function it_can_run_full_backup_command()
    {
        $this->createTestFixtures();

        $this->artisan('backup:run')
            ->assertExitCode(0);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_can_run_database_only_backup()
    {
        $this->artisan('backup:run --only-db')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_run_files_only_backup()
    {
        $this->createTestFixtures();

        $this->artisan('backup:run --only-files')
            ->assertExitCode(0);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_can_run_cleanup_command()
    {
        // Create some dummy backup files
        Storage::disk('local')->put('backup-1.zip', 'content1');
        Storage::disk('local')->put('backup-2.zip', 'content2');

        // Fix: Use correct command name
        $this->artisan('backup:clean')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_list_backups()
    {
        Storage::disk('local')->put('backup-test.zip', 'content');

        $this->artisan('backup:list')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_error_on_backup_failure()
    {
        // Set invalid database connection
        config(['backup.backup.source.databases' => ['invalid_connection']]);

        $this->artisan('backup:run')
            ->assertExitCode(1);
    }
}
