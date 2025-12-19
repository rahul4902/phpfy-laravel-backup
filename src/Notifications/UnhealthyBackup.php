<?php

namespace Phpfy\LaravelBackup\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class UnhealthyBackup extends Notification
{
    protected string $message;
    protected array $issues;

    /**
     * Create a new UnhealthyBackup notification instance.
     */
    public function __construct(string $message, array $issues = [])
    {
        $this->message = $message;
        $this->issues = $issues;
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

        $message = (new MailMessage)
            ->error()
            ->subject("⚠ Unhealthy Backup Detected - {$appName}")
            ->greeting('Backup Health Warning! ⚠')
            ->line("Your backup system requires attention.")
            ->line('')
            ->line('**Issue:**')
            ->line($this->message)
            ->line('');

        if (!empty($this->issues)) {
            $message->line('**Detected Problems:**');
            foreach ($this->issues as $issue) {
                $message->line("• {$issue}");
            }
            $message->line('');
        }

        $message->line('**Recommended Actions:**')
            ->line('1. Check if backup jobs are running on schedule')
            ->line('2. Verify sufficient disk space is available')
            ->line('3. Ensure backup processes are not failing')
            ->line('4. Review backup configuration settings')
            ->line('5. Check application logs for errors')
            ->line('')
            ->line('**Health Checks:**')
            ->line('• Backup age should be less than 25 hours')
            ->line('• Storage usage should be within limits')
            ->line('• All configured disks should be accessible')
            ->line('')
            ->action('Run Backup Now', url('/admin/backup'))
            ->line('')
            ->line('Please resolve this issue promptly to ensure your data is properly backed up.')
            ->line('**Issue detected at:** ' . now()->format('Y-m-d H:i:s'));

        return $message;
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable): SlackMessage
    {
        $appName = config('app.name', 'Laravel Application');

        $slackMessage = (new SlackMessage)
            ->warning()
            ->from(config('backup.notifications.slack.username', 'Laravel Backup'), ':warning:')
            ->to(config('backup.notifications.slack.channel'))
            ->content("⚠ Unhealthy backup detected for *{$appName}*")
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Health Warning')
                    ->color('warning')
                    ->fields([
                        'Issue' => $this->message,
                        'Status' => '⚠ Needs Attention',
                        'Time' => now()->format('Y-m-d H:i:s'),
                        'Severity' => 'Warning',
                    ]);
            });

        if (!empty($this->issues)) {
            $slackMessage->attachment(function ($attachment) {
                $issuesList = implode("\n", array_map(fn($issue) => "• {$issue}", $this->issues));
                $attachment
                    ->title('Detected Problems')
                    ->content($issuesList)
                    ->color('danger');
            });
        }

        $slackMessage->attachment(function ($attachment) {
            $attachment
                ->title('Action Required')
                ->text('Please investigate and resolve backup health issues immediately.')
                ->color('warning');
        });

        return $slackMessage;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'unhealthy_backup',
            'message' => $this->message,
            'issues' => $this->issues,
            'issue_count' => count($this->issues),
            'severity' => 'warning',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}