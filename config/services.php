<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        // RESEND_API_KEY is Laravel's documented name. Keep RESEND_KEY as a
        // compatibility fallback for existing deployment environments.
        'key' => env('RESEND_API_KEY', env('RESEND_KEY')),
    ],

    'gmail' => [
        'client_id' => env('GMAIL_OAUTH_CLIENT_ID'),
        'client_secret' => env('GMAIL_OAUTH_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_OAUTH_REFRESH_TOKEN'),
        'sender_email' => env('GMAIL_SENDER_EMAIL'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
