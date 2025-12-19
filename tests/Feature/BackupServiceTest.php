<?php

namespace Phpfy\LaravelBackup\Tests\Feature;

use Phpfy\LaravelBackup\Tests\TestCase;
use Phpfy\LaravelBackup\Services\BackupService;
use Phpfy\LaravelBackup\Exceptions\BackupException;
use Illuminate\Support\Facades\Storage;

class BackupServiceTest extends TestCase
{
    /** @test */
    public function it_can_create_a_full_backup()
    {
        $this->createTestFixtures();

        $service = app(BackupService::class);
        $result = $service->run();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['filename']);
        $this->assertGreaterThan(0, $result['size']);
        $this->assertIsArray($result['destinations']);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_can_create_database_only_backup()
    {
        $service = app(BackupService::class);
        $result = $service->run(onlyDb: true);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['filename']);
    }

    /** @test */
    public function it_can_create_files_only_backup()
    {
        $this->createTestFixtures();

        $service = app(BackupService::class);
        $result = $service->run(onlyFiles: true);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['filename']);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_creates_backup_with_correct_structure()
    {
        $this->createTestFixtures();

        $service = app(BackupService::class);
        $result = $service->run();

        // Verify file exists
        $this->assertNotEmpty($result['destinations']);

        foreach ($result['destinations'] as $destination) {
            $this->assertTrue(Storage::disk('local')->exists(basename($destination)));
        }

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_throws_exception_on_invalid_database_connection()
    {
        config(['backup.backup.source.databases' => ['invalid']]);

        $this->expectException(BackupException::class);

        $service = app(BackupService::class);
        $service->run(onlyDb: true);
    }

    /** @test */
    public function it_can_encrypt_backup_when_enabled()
    {
        config(['backup.encryption.enabled' => true]);

        $this->createTestFixtures();

        $service = app(BackupService::class);
        $result = $service->run();

        $this->assertTrue($result['success']);
        $this->assertStringEndsWith('.enc', $result['filename']);

        $this->cleanupTestFixtures();
    }
}