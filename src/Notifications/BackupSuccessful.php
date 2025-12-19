<?php

namespace Phpfy\LaravelBackup\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class BackupSuccessful extends Notification
{
    protected string $filename;
    protected int $size;
    protected array $destinations;

    /**
     * Create a new BackupSuccessful notification instance.
     */
    public function __construct(string $filename, int $size, array $destinations)
    {
        $this->filename = $filename;
        $this->size = $size;
        $this->destinations = $destinations;
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

        return (new MailMessage)
            ->subject("✓ Backup Successful - {$appName}")
            ->greeting('Backup Completed Successfully! ✓')
            ->line("Your backup has been created and stored successfully.")
            ->line('')
            ->line('**Backup Details:**')
            ->line("📄 **Filename:** {$this->filename}")
            ->line("💾 **Size:** {$this->formatBytes($this->size)}")
            ->line("📍 **Destinations:** " . count($this->destinations))
            ->line('')
            ->line('**Storage Locations:**')
            ->lines($this->formatDestinations())
            ->line('')
            ->line('The backup process completed without any errors.')
            ->line('Your data is now safely backed up.')
            ->success();
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable): SlackMessage
    {
        $appName = config('app.name', 'Laravel Application');

        return (new SlackMessage)
            ->success()
            ->from(config('backup.notifications.slack.username', 'Laravel Backup'), ':white_check_mark:')
            ->to(config('backup.notifications.slack.channel'))
            ->content("✓ Backup completed successfully for *{$appName}*")
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Backup Details')
                    ->color('good')
                    ->fields([
                        'Filename' => $this->filename,
                        'Size' => $this->formatBytes($this->size),
                        'Destinations' => count($this->destinations) . ' location(s)',
                        'Status' => '✓ Success',
                        'Time' => now()->format('Y-m-d H:i:s'),
                    ]);
            })
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Storage Locations')
                    ->content($this->formatDestinationsForSlack());
            });
    }

    /**
     * Format destinations for email.
     */
    protected function formatDestinations(): array
    {
        return array_map(function ($destination, $index) {
            return ($index + 1) . ". " . basename(dirname($destination)) . "/" . basename($destination);
        }, $this->destinations, array_keys($this->destinations));
    }

    /**
     * Format destinations for Slack.
     */
    protected function formatDestinationsForSlack(): string
    {
        return implode("\n", array_map(function ($destination, $index) {
            return "• " . basename(dirname($destination)) . "/" . basename($destination);
        }, $this->destinations, array_keys($this->destinations)));
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
            'type' => 'backup_successful',
            'filename' => $this->filename,
            'size' => $this->size,
            'size_human' => $this->formatBytes($this->size),
            'destinations' => $this->destinations,
            'destination_count' => count($this->destinations),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}