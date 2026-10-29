<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SmsNotificationService
{
    public function normalizePhoneNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits) {
            return null;
        }

        $countryCode = ltrim((string) config('sms.default_country_code'), '+');
        if (str_starts_with($digits, '0')) {
            $digits = $countryCode.substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $digits = $countryCode.$digits;
        }

        $normalized = '+'.$digits;

        return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) ? $normalized : null;
    }

    public function send(string $to, string $message): bool
    {
        $recipient = $this->normalizePhoneNumber($to);
        if (! $recipient) {
            throw new RuntimeException('The SMS recipient phone number is invalid.');
        }

        $driver = strtolower(trim((string) config('sms.driver', 'log')));

        if ($driver === 'log') {
            Log::info('SMS reminder simulated by log driver.', ['to' => $recipient, 'message' => $message]);

            return false;
        }

        return match ($driver) {
            'textbee' => $this->sendWithTextBee($recipient, $message),
            'twilio' => $this->sendWithTwilio($recipient, $message),
            default => throw new RuntimeException("Unsupported SMS driver [{$driver}]."),
        };
    }

    private function sendWithTextBee(string $to, string $message): bool
    {
        $apiKey = trim((string) config('sms.textbee.api_key'));
        $baseUrl = rtrim(trim((string) config('sms.textbee.base_url')), '/');

        if ($apiKey === '' || $baseUrl === '') {
            Log::warning('TextBee SMS is not configured.', [
                'provider' => 'textbee',
                'reason' => 'missing_configuration',
            ]);

            throw new RuntimeException('TextBee SMS configuration is incomplete.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->timeout(15)
                ->post($baseUrl.'/gateway/send-sms', [
                    'recipients' => [$to],
                    'message' => $message,
                ]);
        } catch (ConnectionException) {
            Log::error('TextBee SMS provider could not be reached.', [
                'provider' => 'textbee',
                'reason' => 'connection_failure',
            ]);

            throw new RuntimeException('The TextBee SMS provider could not be reached.');
        } catch (Throwable $exception) {
            Log::error('TextBee SMS request failed before a response was received.', [
                'provider' => 'textbee',
                'reason' => 'client_failure',
                'exception_class' => $exception::class,
            ]);

            throw new RuntimeException('The TextBee SMS request could not be completed.');
        }

        if ($response->failed()) {
            $this->throwTextBeeFailure($response);
        }

        $batchId = data_get($response->json(), 'data.smsBatchId');
        Log::info('TextBee accepted an SMS for sending.', array_filter([
            'provider' => 'textbee',
            'sms_batch_id' => is_string($batchId) ? $batchId : null,
        ]));

        return true;
    }

    private function throwTextBeeFailure(Response $response): never
    {
        $status = $response->status();

        Log::warning('TextBee rejected an SMS request.', [
            'provider' => 'textbee',
            'status' => $status,
        ]);

        $message = match ($status) {
            400 => 'TextBee rejected the SMS request (HTTP 400).',
            401 => 'TextBee authentication failed (HTTP 401).',
            404 => 'The TextBee gateway device was not found (HTTP 404).',
            429 => 'The TextBee SMS plan or rate limit was reached (HTTP 429).',
            default => $status >= 500
                ? "TextBee is temporarily unavailable (HTTP {$status})."
                : "TextBee rejected the SMS request (HTTP {$status}).",
        };

        throw new RuntimeException($message);
    }

    private function sendWithTwilio(string $to, string $message): bool
    {
        $accountSid = config('sms.twilio.account_sid');
        $authToken = config('sms.twilio.auth_token');
        $from = config('sms.twilio.from');
        if (! $accountSid || ! $authToken || ! $from) {
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
            throw new RuntimeException('The SMS provider rejected the message.');
        }

        return true;
    }
}
