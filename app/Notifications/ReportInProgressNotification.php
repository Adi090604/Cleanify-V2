<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\Report;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportInProgressNotification extends Notification implements SendsSmsNotification
{
    use Queueable;

    public function __construct(
        public Report $report,
        public bool $isFollower = false
    ) {}

    /** @return array<int, string> */
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

    public function toMail(object $notifiable): MailMessage
    {
        $intro = $this->isFollower
            ? 'A community report you follow is now being handled.'
            : 'Your reported garbage issue is now being handled.';

        return (new MailMessage)
            ->subject($this->isFollower ? 'Report You Follow Is In Progress - Cleanify' : 'Your Report Is In Progress - Cleanify')
            ->view('emails.cleanify-notification', [
                'title' => 'Report In Progress',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$intro],
                'details' => [
                    ['label' => 'Location', 'value' => $this->report->location],
                    ['label' => 'Description', 'value' => $this->report->description],
                ],
                'actionText' => 'View Report',
                'actionUrl' => route('community-reports'),
                'closing' => ['We will notify you again when its status changes.'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return $this->isFollower
            ? 'Cleanify: A report you follow is now In Progress. Check Cleanify for details.'
            : 'Cleanify: Your report is now In Progress and is being reviewed. Check Cleanify for details.';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'status' => Report::STATUS_IN_PROGRESS,
            'location' => $this->report->location,
            'follower' => $this->isFollower,
            'category' => 'reports',
            'title' => $this->isFollower ? 'Report you follow is in progress' : 'Your report is in progress',
            'message' => 'Location: '.$this->report->location,
            'icon' => 'fa-spinner',
            'color' => 'bg-blue-600',
            'url' => '/community-reports',
        ];
    }
}
