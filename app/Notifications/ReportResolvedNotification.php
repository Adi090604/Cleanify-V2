<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportResolvedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Report $report,
        public bool $isFollower = false
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable->notificationPreferenceEnabled('report_updates', 'reports')) {
            return [];
        }

        $channels = ['database'];

        if ($notifiable->email_notifications ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resolver = $this->report->resolver;
        $resolverName = $resolver ? $resolver->name : 'Admin';
        $subject = $this->isFollower
            ? 'Report You Follow Has Been Resolved - Cleanify'
            : 'Your Report Has Been Resolved - Cleanify';

        $intro = $this->isFollower
            ? 'A community report you follow has been resolved.'
            : 'Great news! Your community report has been resolved.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($intro)
            ->line('**Report Details:**')
            ->line('📍 Location: '.$this->report->location)
            ->line('📝 Description: '.$this->report->description)
            ->when($this->report->admin_notes, function ($mail) {
                return $mail->line('**Admin Notes:**')
                    ->line($this->report->admin_notes);
            })
            ->line('Resolved by: '.$resolverName)
            ->when($this->report->resolved_at, function ($mail) {
                return $mail->line('Resolved on: '.$this->report->resolved_at->format('F d, Y \a\t h:i A'));
            }, function ($mail) {
                return $mail->line('Resolved on: '.now()->format('F d, Y \a\t h:i A'));
            })
            ->action('View Report', route('community-reports'))
            ->line('Thank you for helping keep our community clean!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'status' => 'resolved',
            'location' => $this->report->location,
            'follower' => $this->isFollower,
            'category' => 'reports',
            'title' => $this->isFollower ? 'Report you follow resolved' : 'Your report is resolved',
            'message' => 'Location: '.$this->report->location,
            'icon' => 'fa-check-circle',
            'color' => 'bg-green-600',
            'url' => '/community-reports',
        ];
    }
}
