<?php

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ResendTransport;

test('the configured resend mailer resolves without sending an email', function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 're_test_placeholder');

    /** @var MailManager $manager */
    $manager = app(MailManager::class);
    $manager->purge('resend');

    expect($manager->mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransport::class);
});

test('resend configuration documents the canonical key with legacy fallback', function () {
    $configuration = file_get_contents(config_path('services.php'));

    expect($configuration)
        ->toContain("env('RESEND_API_KEY', env('RESEND_KEY'))");
});
