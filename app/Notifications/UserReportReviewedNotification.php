<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\UserReport;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserReportReviewedNotification extends Notification implements SendsSmsNotification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public UserReport $userReport
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
        $reportedUser = $this->userReport->reportedUser;
        $reviewer = $this->userReport->reviewer;
        $reviewerName = $reviewer ? $reviewer->name : 'Admin';

        $statusMessages = [
            'reviewed' => 'Your user report has been reviewed by our team.',
            'dismissed' => 'Your user report has been dismissed.',
            'action_taken' => 'Action has been taken based on your user report.',
        ];

        $statusMessage = $statusMessages[$this->userReport->status] ?? 'Your user report status has been updated.';

        $details = [
            ['label' => 'Reported User', 'value' => $reportedUser ? $reportedUser->name : 'Unknown'],
            ['label' => 'Reason', 'value' => $this->userReport->getReasonLabel()],
        ];

        if ($this->userReport->description) {
            $details[] = ['label' => 'Description', 'value' => $this->userReport->description];
        }

        $details[] = ['label' => 'Status', 'value' => ucfirst(str_replace('_', ' ', $this->userReport->status))];

        if ($this->userReport->admin_notes) {
            $details[] = ['label' => 'Admin Notes', 'value' => $this->userReport->admin_notes];
        }

        $details[] = ['label' => 'Reviewed by', 'value' => $reviewerName];

        if ($this->userReport->reviewed_at) {
            $details[] = ['label' => 'Reviewed on', 'value' => $this->userReport->reviewed_at->format('F d, Y \a\t h:i A')];
        }

        return (new MailMessage)
            ->subject('User Report Status Update - Cleanify')
            ->view('emails.cleanify-notification', [
                'title' => 'User Report Status Update',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$statusMessage],
                'details' => $details,
                'actionText' => 'View Reports',
                'actionUrl' => route('community-reports'),
                'closing' => ['Thank you for helping keep our community safe!'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return match ($this->userReport->status) {
            'reviewed' => 'Cleanify: Your user report has been reviewed. Check Cleanify for details.',
            'dismissed' => 'Cleanify: Your user report was dismissed. Check Cleanify for details.',
            'action_taken' => 'Cleanify: Action was taken based on your user report. Check Cleanify for details.',
            default => 'Cleanify: Your user report status was updated. Check Cleanify for details.',
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reportedUser = $this->userReport->reportedUser;
        $statusMessages = [
            'reviewed' => 'Your user report has been reviewed',
            'dismissed' => 'Your user report was dismissed',
            'action_taken' => 'Action taken on your user report',
        ];

        $title = $statusMessages[$this->userReport->status] ?? 'User report status updated';
        $message = 'Reported user: '.($reportedUser ? $reportedUser->name : 'Unknown');

        $colors = [
            'reviewed' => 'bg-blue-600',
            'dismissed' => 'bg-gray-600',
            'action_taken' => 'bg-green-600',
        ];

        return [
            'user_report_id' => $this->userReport->id,
            'reported_user_id' => $this->userReport->reported_user_id,
            'status' => $this->userReport->status,
            'reason' => $this->userReport->reason,
            'category' => 'reports',
            'title' => $title,
            'message' => $message,
            'icon' => 'fa-user-shield',
            'color' => $colors[$this->userReport->status] ?? 'bg-blue-600',
            'url' => '/community-reports',
        ];
    }
}
