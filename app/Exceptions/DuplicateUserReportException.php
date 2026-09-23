<?php

namespace App\Exceptions;

use App\Models\UserReport;
use RuntimeException;

class DuplicateUserReportException extends RuntimeException
{
    public function __construct(
        public UserReport $userReport,
        public bool $postSpecific
    ) {
        parent::__construct($postSpecific
            ? 'You have already reported this Community Report. Please wait for admin review.'
            : 'You have already reported this user. Please wait for admin review.');
    }
}
