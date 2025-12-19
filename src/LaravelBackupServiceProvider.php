<?php

namespace Phpfy\LaravelBackup;

use Illuminate\Support\ServiceProvider;
use Phpfy\LaravelBackup\Commands\BackupRunCommand;
use Phpfy\LaravelBackup\Commands\BackupListCommand;
use Phpfy\LaravelBackup\Commands\BackupCleanCommand;
use Phpfy\LaravelBackup\Commands\BackupMonitorCommand;
use Phpfy\LaravelBackup\Services\BackupService;

class LaravelBackupServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(__DIR__ . '/../config/backup.php', 'backup');

        // Register the main BackupService
        $this->app->singleton(BackupService::class, function ($app) {
            return new BackupService(
                $app['config']['backup'],
                $app['filesystem'],
                $app['log']
            );
        });

        // Create an alias for easier access
        $this->app->alias(BackupService::class, 'backup');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only register commands and publishes when running in console
        if ($this->app->runningInConsole()) {
            // Publish configuration file
            $this->publishes([
                __DIR__ . '/../config/backup.php' => config_path('backup.php'),
            ], 'backup-config');

            // Register artisan commands
            $this->commands([
                BackupRunCommand::class,
                BackupListCommand::class,
                BackupCleanCommand::class,
                BackupMonitorCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            BackupService::class,
            'backup',
        ];
    }
}
