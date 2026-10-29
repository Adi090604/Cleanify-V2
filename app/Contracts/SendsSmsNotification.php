<?php

namespace App\Contracts;

interface SendsSmsNotification
{
    public function toSms(object $notifiable): string;
}
