<?php

namespace Phpfy\LaravelBackup\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Phpfy\LaravelBackup\LaravelBackupServiceProvider;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('backups');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelBackupServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Backup' => \Phpfy\LaravelBackup\Facades\Backup::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use actual SQLite file instead of :memory: for testing backups
        $dbPath = __DIR__ . '/temp/test-database.sqlite';
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0777, true);
        }

        // Create empty SQLite file
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
        ]);

        // Backup config
        $app['config']->set('backup', [
            'backup' => [
                'name' => 'test-app',
                'source' => [
                    'databases' => ['testbench'],
                    'files' => [
                        'include' => [
                            __DIR__ . '/fixtures',
                        ],
                        'exclude' => [
                            __DIR__ . '/fixtures/excluded',
                        ],
                    ],
                ],
            ],
            'destination' => [
                'disks' => ['local'],
            ],
            'encryption' => [
                'enabled' => false,
                'password' => 'test-password',
            ],
            'notifications' => [
                'enabled' => false,
            ],
            'cleanup' => [
                'strategy' => 'default',
                'default_strategy' => [
                    'keep_all_backups_for_days' => 7,
                    'keep_daily_backups_for_days' => 16,
                    'keep_weekly_backups_for_weeks' => 8,
                    'keep_monthly_backups_for_months' => 4,
                    'keep_yearly_backups_for_years' => 2,
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test database
        $dbPath = __DIR__ . '/temp/test-database.sqlite';
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        parent::tearDown();
    }

    protected function createTestFixtures(): void
    {
        $fixturesPath = __DIR__ . '/fixtures';
        
        if (!file_exists($fixturesPath)) {
            mkdir($fixturesPath, 0777, true);
        }

        file_put_contents($fixturesPath . '/test1.txt', 'Test content 1');
        file_put_contents($fixturesPath . '/test2.txt', 'Test content 2');

        $excludedPath = $fixturesPath . '/excluded';
        if (!file_exists($excludedPath)) {
            mkdir($excludedPath, 0777, true);
        }
        
        file_put_contents($excludedPath . '/excluded.txt', 'Excluded content');
    }

    protected function cleanupTestFixtures(): void
    {
        $fixturesPath = __DIR__ . '/fixtures';
        
        if (file_exists($fixturesPath)) {
            $this->deleteDirectory($fixturesPath);
        }
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
