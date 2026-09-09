<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsNotificationService
{
    public function normalizePhoneNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (!$digits) return null;

        $countryCode = ltrim((string) config('sms.default_country_code'), '+');
        if (str_starts_with($digits, '0')) $digits = $countryCode . substr($digits, 1);
        elseif (strlen($digits) === 10) $digits = $countryCode . $digits;

        $normalized = '+' . $digits;

        return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) ? $normalized : null;
    }

    public function send(string $to, string $message): bool
    {
        $driver = config('sms.driver');

        if ($driver === 'log') {
            Log::info('SMS reminder simulated by log driver.', ['to' => $to, 'message' => $message]);
            return false;
        }

        if ($driver !== 'twilio') {
            throw new RuntimeException("Unsupported SMS driver [{$driver}].");
        }

        $accountSid = config('sms.twilio.account_sid');
        $authToken = config('sms.twilio.auth_token');
        $from = config('sms.twilio.from');
        if (!$accountSid || !$authToken || !$from) {
            throw new RuntimeException('Twilio SMS credentials are not configured.');
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The SMS provider could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The SMS provider rejected the message: ' . $response->body());
        }

        return true;
    }
}
