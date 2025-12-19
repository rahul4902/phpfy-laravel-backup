<?php

namespace Phpfy\LaravelBackup\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Throwable;

class BackupFailed extends Notification
{
    protected Throwable $exception;

    /**
     * Create a new BackupFailed notification instance.
     */
    public function __construct(Throwable $exception)
    {
        $this->exception = $exception;
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
            ->error()
            ->subject("✗ Backup Failed - {$appName}")
            ->greeting('Backup Failed! ✗')
            ->line("The backup process encountered an error and could not complete.")
            ->line('')
            ->line('**Error Details:**')
            ->line("⚠ **Error:** {$this->exception->getMessage()}")
            ->line("📍 **Location:** {$this->exception->getFile()}:{$this->exception->getLine()}")
            ->line("🔢 **Code:** {$this->exception->getCode()}")
            ->line('')
            ->line('**Recommended Actions:**')
            ->line('1. Check the application logs for detailed error information')
            ->line('2. Verify database connections are working')
            ->line('3. Ensure sufficient disk space is available')
            ->line('4. Check file permissions on backup directories')
            ->line('5. Review backup configuration settings')
            ->line('')
            ->line('Please investigate and resolve this issue as soon as possible.')
            ->line('Your data is NOT backed up until this error is resolved.')
            ->action('View Application Logs', url('/logs'))
            ->line('')
            ->line('**Error occurred at:** ' . now()->format('Y-m-d H:i:s'));
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable): SlackMessage
    {
        $appName = config('app.name', 'Laravel Application');

        return (new SlackMessage)
            ->error()
            ->from(config('backup.notifications.slack.username', 'Laravel Backup'), ':x:')
            ->to(config('backup.notifications.slack.channel'))
            ->content("✗ Backup failed for *{$appName}*")
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Error Details')
                    ->color('danger')
                    ->fields([
                        'Error Message' => $this->truncate($this->exception->getMessage(), 200),
                        'Location' => $this->exception->getFile() . ':' . $this->exception->getLine(),
                        'Time' => now()->format('Y-m-d H:i:s'),
                        'Status' => '✗ Failed',
                    ]);
            })
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Action Required')
                    ->text('Please check application logs and resolve the issue immediately.')
                    ->color('warning');
            });
    }

    /**
     * Truncate text to specified length.
     */
    protected function truncate(string $text, int $length = 100): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'backup_failed',
            'error_message' => $this->exception->getMessage(),
            'error_code' => $this->exception->getCode(),
            'error_file' => $this->exception->getFile(),
            'error_line' => $this->exception->getLine(),
            'exception_class' => get_class($this->exception),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}