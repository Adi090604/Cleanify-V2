<?php

return [
    // Use "log" locally so scheduled reminders never send a real SMS by default.
    'driver' => env('SMS_DRIVER', 'log'),
    'reminder_time' => env('SMS_REMINDER_TIME', '08:00'),
    'timezone' => env('SMS_TIMEZONE', 'Asia/Manila'),
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '+63'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
];
