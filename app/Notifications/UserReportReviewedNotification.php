<?php

namespace App\Notifications;

use App\Models\UserReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserReportReviewedNotification extends Notification
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

        $mail = (new MailMessage)
            ->subject('User Report Status Update - Cleanify')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($statusMessage)
            ->line('**Report Details:**')
            ->line('👤 Reported User: '.($reportedUser ? $reportedUser->name : 'Unknown'))
            ->line('📋 Reason: '.$this->userReport->getReasonLabel())
            ->when($this->userReport->description, function ($mail) {
                return $mail->line('📝 Description: '.$this->userReport->description);
            })
            ->line('✅ Status: '.ucfirst(str_replace('_', ' ', $this->userReport->status)))
            ->when($this->userReport->admin_notes, function ($mail) {
                return $mail->line('**Admin Notes:**')
                    ->line($this->userReport->admin_notes);
            })
            ->line('Reviewed by: '.$reviewerName)
            ->when($this->userReport->reviewed_at, function ($mail) {
                return $mail->line('Reviewed on: '.$this->userReport->reviewed_at->format('F d, Y \a\t h:i A'));
            })
            ->action('View Reports', route('community-reports'))
            ->line('Thank you for helping keep our community safe!');

        return $mail;
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
