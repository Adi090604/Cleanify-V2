<?php

namespace App\Notifications\Channels;

use App\Contracts\SendsSmsNotification;
use App\Services\SmsNotificationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsChannel
{
    public function __construct(private readonly SmsNotificationService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof SendsSmsNotification) {
            return;
        }

        $phone = $this->sms->normalizePhoneNumber($notifiable->phone ?? null);

        if (! $phone) {
            Log::warning('Notification SMS skipped because the recipient phone is missing or invalid.', [
                'user_id' => $notifiable->getKey(),
                'notification' => $notification::class,
            ]);

            return;
        }

        try {
            $this->sms->send($phone, $notification->toSms($notifiable));
        } catch (Throwable $exception) {
            Log::error('Notification SMS delivery failed.', [
                'user_id' => $notifiable->getKey(),
                'notification' => $notification::class,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
