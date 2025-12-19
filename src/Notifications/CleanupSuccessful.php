<?php

namespace Phpfy\LaravelBackup\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class CleanupSuccessful extends Notification
{
    protected array $results;

    /**
     * Create a new CleanupSuccessful notification instance.
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = config('backup.notifications.notifications')[self::class] ?? ['mail'];

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'Laravel Application');
        $totalDeleted = $this->getTotalDeleted();
        $totalFreed = $this->getTotalFreed();

        $message = (new MailMessage)
            ->subject("🧹 Backup Cleanup Successful - {$appName}")
            ->greeting('Cleanup Completed! ✓')
            ->line("Old backups have been cleaned up successfully based on your retention strategy.")
            ->line('')
            ->line('**Cleanup Summary:**')
            ->line("🗑 **Total Deleted:** {$totalDeleted} backup(s)")
            ->line("💾 **Space Freed:** {$this->formatBytes($totalFreed)}")
            ->line('')
            ->line('**Details by Storage Disk:**');

        foreach ($this->results as $disk => $result) {
            $deletedCount = $result['deleted_count'] ?? 0;
            $deletedSize = $result['deleted_size'] ?? 0;

            if ($deletedCount > 0) {
                $message->line("📂 **{$disk}:** {$deletedCount} backup(s) - {$this->formatBytes($deletedSize)} freed");
            } else {
                $message->line("📂 **{$disk}:** No cleanup needed");
            }
        }

        $strategy = config('backup.cleanup.default_strategy', []);

        $message->line('')
            ->line('**Retention Strategy:**')
            ->line("• Keep all backups for {$strategy['keep_all_backups_for_days']} days")
            ->line("• Keep daily backups for {$strategy['keep_daily_backups_for_days']} days")
            ->line("• Keep weekly backups for {$strategy['keep_weekly_backups_for_weeks']} weeks")
            ->line("• Keep monthly backups for {$strategy['keep_monthly_backups_for_months']} months")
            ->line("• Keep yearly backups for {$strategy['keep_yearly_backups_for_years']} years");

        return $message;
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable): SlackMessage
    {
        $appName = config('app.name', 'Laravel Application');
        $totalDeleted = $this->getTotalDeleted();
        $totalFreed = $this->getTotalFreed();

        $message = (new SlackMessage)
            ->success()
            ->from(config('backup.notifications.slack.username', 'Laravel Backup'), ':broom:')
            ->to(config('backup.notifications.slack.channel'))
            ->content("🧹 Backup cleanup completed for *{$appName}*")
            ->attachment(function ($attachment) use ($totalDeleted, $totalFreed) {
                $attachment
                    ->title('Cleanup Summary')
                    ->color('good')
                    ->fields([
                        'Deleted' => $totalDeleted . ' backup(s)',
                        'Space Freed' => $this->formatBytes($totalFreed),
                        'Status' => '✓ Success',
                        'Time' => now()->format('Y-m-d H:i:s'),
                    ]);
            });

        // Add disk-specific details
        $diskDetails = [];
        foreach ($this->results as $disk => $result) {
            $deletedCount = $result['deleted_count'] ?? 0;
            $deletedSize = $result['deleted_size'] ?? 0;

            if ($deletedCount > 0) {
                $diskDetails[] = "• *{$disk}:* {$deletedCount} backup(s) - {$this->formatBytes($deletedSize)}";
            }
        }

        if (!empty($diskDetails)) {
            $message->attachment(function ($attachment) use ($diskDetails) {
                $attachment
                    ->title('Details by Disk')
                    ->content(implode("\n", $diskDetails));
            });
        }

        return $message;
    }

    /**
     * Get total number of deleted backups.
     */
    protected function getTotalDeleted(): int
    {
        return array_sum(array_column($this->results, 'deleted_count'));
    }

    /**
     * Get total freed space.
     */
    protected function getTotalFreed(): int
    {
        return array_sum(array_column($this->results, 'deleted_size'));
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'cleanup_successful',
            'results' => $this->results,
            'total_deleted' => $this->getTotalDeleted(),
            'total_freed' => $this->getTotalFreed(),
            'total_freed_human' => $this->formatBytes($this->getTotalFreed()),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}