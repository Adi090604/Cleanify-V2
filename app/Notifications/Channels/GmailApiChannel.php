<?php

namespace App\Notifications\Channels;

use App\Notifications\GmailResetPasswordNotification;
use App\Services\GmailApiService;

class GmailApiChannel
{
    public function __construct(private readonly GmailApiService $gmail) {}

    public function send(object $notifiable, GmailResetPasswordNotification $notification): void
    {
        $message = $notification->toGmailApi($notifiable);

        $this->gmail->send(
            $notifiable->getEmailForPasswordReset(),
            $message['subject'],
            $message['html'],
            $message['text']
        );
    }
}
