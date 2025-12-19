<?php

return [
    'backup' => [
        'name' => env('BACKUP_NAME', env('APP_NAME', 'laravel-backup')),

        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('storage/framework/cache'),
                    base_path('storage/framework/sessions'),
                    base_path('storage/framework/views'),
                    base_path('storage/logs'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
            ],

            'databases' => [
                'default',
            ],
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'notifiable' => \Illuminate\Support\Facades\Notification::class,
        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'your@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Laravel Backup'),
            ],
        ],
        'slack' => [
            'webhook_url' => env('BACKUP_SLACK_WEBHOOK_URL', ''),
            'channel' => env('BACKUP_SLACK_CHANNEL', null),
            'username' => env('BACKUP_SLACK_USERNAME', null),
            'icon' => env('BACKUP_SLACK_ICON', null),
        ],
        'notifications' => [
            \Phpfy\LaravelBackup\Notifications\BackupSuccessful::class => ['mail', 'slack'],
            \Phpfy\LaravelBackup\Notifications\BackupFailed::class => ['mail', 'slack'],
            \Phpfy\LaravelBackup\Notifications\CleanupSuccessful::class => ['mail'],
            \Phpfy\LaravelBackup\Notifications\UnhealthyBackup::class => ['mail', 'slack'],
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Phpfy\LaravelBackup\HealthChecks\MaximumAgeInDays::class => 1,
                \Phpfy\LaravelBackup\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Phpfy\LaravelBackup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

    'destination' => [
        'filename_prefix' => '',
        'disks' => [
            'local',
        ],
    ],

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', false),
        'password' => env('BACKUP_ENCRYPTION_PASSWORD', ''),
    ],

    'temporary_directory' => storage_path('app/backup-temp'),
    'timeout' => 3600,
    'tries' => 1,
    'retry_delay' => 0,
];