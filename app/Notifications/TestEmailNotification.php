<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestEmailNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only send if user has email notifications enabled
        if ($notifiable->email_notifications ?? true) {
            return ['mail'];
        }

        return [];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test Email Notification - Cleanify Admin')
            ->view('emails.cleanify-notification', [
                'title' => 'Test Email Notification',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [
                    'This is a test email notification from Cleanify Admin Panel.',
                    'If you received this email, it means your email notifications are working correctly!',
                    'You can manage your notification preferences in the Admin Settings page.',
                ],
                'actionText' => 'Go to Settings',
                'actionUrl' => url('/admin/settings'),
                'closing' => ['Thank you for using Cleanify!'],
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
