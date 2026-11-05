<?php

namespace App\Notifications;

use App\Notifications\Channels\GmailApiChannel;
use Illuminate\Auth\Notifications\ResetPassword;

class GmailResetPasswordNotification extends ResetPassword
{
    /** @return array<int, class-string> */
    public function via($notifiable): array
    {
        return [GmailApiChannel::class];
    }

    /**
     * @return array{subject: string, html: string, text: string}
     */
    public function toGmailApi(object $notifiable): array
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expiration = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $subject = 'Reset Your Cleanify Password';

        return [
            'subject' => $subject,
            'html' => view('emails.cleanify-notification', [
                'title' => $subject,
                'preview' => 'Use this secure link to reset your Cleanify password.',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => ['We received a request to reset the password for your Cleanify account.'],
                'actionText' => 'Reset Password',
                'actionUrl' => $resetUrl,
                'closing' => [
                    "This password reset link will expire in {$expiration} minutes.",
                    'If you did not request a password reset, you can safely ignore this email.',
                ],
            ])->render(),
            'text' => implode("\n\n", [
                'Hello '.$notifiable->name.'!',
                'We received a request to reset the password for your Cleanify account.',
                'Reset your password: '.$resetUrl,
                "This password reset link will expire in {$expiration} minutes.",
                'If you did not request a password reset, you can safely ignore this email.',
            ]),
        ];
    }
}
