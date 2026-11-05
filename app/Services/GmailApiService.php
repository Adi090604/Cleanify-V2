<?php

namespace App\Services;

use App\Exceptions\GmailDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;

class GmailApiService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const SEND_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function send(string $recipient, string $subject, string $html, string $text): void
    {
        $configuration = $this->configuration();
        $rawMessage = $this->encodeMimeMessage(
            $configuration['sender_email'],
            $recipient,
            $subject,
            $html,
            $text
        );

        $accessToken = $this->refreshAccessToken($configuration);
        $response = $this->sendRawMessage($accessToken, $rawMessage);

        if ($response->status() === 401) {
            $accessToken = $this->refreshAccessToken($configuration);
            $response = $this->sendRawMessage($accessToken, $rawMessage);
        }

        if (! $response->successful()) {
            throw GmailDeliveryException::delivery();
        }
    }

    /**
     * @return array{client_id: string, client_secret: string, refresh_token: string, sender_email: string}
     */
    private function configuration(): array
    {
        $configuration = config('services.gmail', []);
        $values = [
            'client_id' => $configuration['client_id'] ?? null,
            'client_secret' => $configuration['client_secret'] ?? null,
            'refresh_token' => $configuration['refresh_token'] ?? null,
            'sender_email' => $configuration['sender_email'] ?? null,
        ];

        if (collect($values)->contains(fn ($value): bool => ! is_string($value) || trim($value) === '')
            || filter_var($values['sender_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw GmailDeliveryException::configuration();
        }

        return $values;
    }

    /**
     * @param  array{client_id: string, client_secret: string, refresh_token: string, sender_email: string}  $configuration
     */
    private function refreshAccessToken(array $configuration): string
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post(self::TOKEN_ENDPOINT, [
                    'client_id' => $configuration['client_id'],
                    'client_secret' => $configuration['client_secret'],
                    'refresh_token' => $configuration['refresh_token'],
                    'grant_type' => 'refresh_token',
                ]);
        } catch (ConnectionException) {
            throw GmailDeliveryException::connection();
        }

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw GmailDeliveryException::authentication();
        }

        return $accessToken;
    }

    private function sendRawMessage(string $accessToken, string $rawMessage): Response
    {
        try {
            return Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post(self::SEND_ENDPOINT, ['raw' => $rawMessage]);
        } catch (ConnectionException) {
            throw GmailDeliveryException::connection();
        }
    }

    private function encodeMimeMessage(
        string $sender,
        string $recipient,
        string $subject,
        string $html,
        string $text
    ): string {
        $message = (new Email)
            ->from($sender)
            ->to($recipient)
            ->subject($subject)
            ->text($text)
            ->html($html);

        return rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
    }
}
