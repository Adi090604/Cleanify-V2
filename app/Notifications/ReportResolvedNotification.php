<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\Report;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportResolvedNotification extends Notification implements SendsSmsNotification
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

        if ($notifiable->sms_notifications ?? false) {
            $channels[] = SmsChannel::class;
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

        $resolvedAt = $this->report->resolved_at ?? now();
        $details = [
            ['label' => 'Location', 'value' => $this->report->location],
            ['label' => 'Description', 'value' => $this->report->description],
        ];

        if ($this->report->admin_notes) {
            $details[] = ['label' => 'Admin Notes', 'value' => $this->report->admin_notes];
        }

        $details[] = ['label' => 'Resolved by', 'value' => $resolverName];
        $details[] = ['label' => 'Resolved on', 'value' => $resolvedAt->format('F d, Y \a\t h:i A')];

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.cleanify-notification', [
                'title' => 'Report Resolved',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$intro],
                'details' => $details,
                'actionText' => 'View Report',
                'actionUrl' => route('community-reports'),
                'closing' => ['Thank you for helping keep our community clean!'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return $this->isFollower
            ? 'Cleanify: A report you follow has been resolved. Check Cleanify for details.'
            : 'Cleanify: Your report has been resolved. Check Cleanify for the resolution details.';
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
