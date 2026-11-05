<?php

namespace App\Exceptions;

use RuntimeException;

class GmailDeliveryException extends RuntimeException
{
    public static function configuration(): self
    {
        return new self('Password reset email delivery is not configured.');
    }

    public static function authentication(): self
    {
        return new self('Gmail authorization failed.');
    }

    public static function delivery(): self
    {
        return new self('Gmail rejected the password reset email.');
    }

    public static function connection(): self
    {
        return new self('Gmail is temporarily unavailable.');
    }
}
