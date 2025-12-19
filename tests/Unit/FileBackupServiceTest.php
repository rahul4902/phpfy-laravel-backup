<?php

namespace Phpfy\LaravelBackup\Tests\Unit;

use Phpfy\LaravelBackup\Tests\TestCase;
use Phpfy\LaravelBackup\Services\FileBackupService;

class FileBackupServiceTest extends TestCase
{
    /** @test */
    public function it_can_collect_files()
    {
        $this->createTestFixtures();

        $config = config('backup');
        $service = new FileBackupService($config);

        $files = $service->getFiles();

        $this->assertIsArray($files);
        $this->assertGreaterThan(0, count($files));

        $this->cleanupTestFixtures();
    }

    /** @test */
    /** @test */
    public function it_excludes_specified_paths()
    {
        $this->createTestFixtures();

        $config = config('backup');
        $service = new FileBackupService($config);

        $files = $service->getFiles();

        // Check that files inside 'excluded' directory are not in the list
        $hasExcludedFile = false;
        foreach ($files as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR . 'excluded' . DIRECTORY_SEPARATOR . 'excluded.txt')) {
                $hasExcludedFile = true;
                break;
            }
        }

        $this->assertFalse($hasExcludedFile, "Excluded file 'excluded.txt' should not be in backup");

        $this->cleanupTestFixtures();
    }


    /** @test */
    public function it_calculates_total_size()
    {
        $this->createTestFixtures();

        $config = config('backup');
        $service = new FileBackupService($config);

        $size = $service->getTotalSize();

        $this->assertGreaterThan(0, $size);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_counts_files()
    {
        $this->createTestFixtures();

        $config = config('backup');
        $service = new FileBackupService($config);

        $count = $service->getFileCount();

        $this->assertGreaterThan(0, $count);

        $this->cleanupTestFixtures();
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_paths()
    {
        $config = config('backup');
        $config['backup']['source']['files']['include'] = ['/non/existent/path'];

        $service = new FileBackupService($config);
        $files = $service->getFiles();

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }
}
