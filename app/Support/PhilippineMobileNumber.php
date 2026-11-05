<?php

namespace App\Support;

final class PhilippineMobileNumber
{
    public const REGEX = '/\A(?:09[0-9]{9}|\+639[0-9]{9})\z/';

    public static function normalize(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '09')) {
            return '+63'.substr($phone, 1);
        }

        return $phone;
    }
}
