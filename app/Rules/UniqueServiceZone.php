<?php

namespace App\Rules;

use App\Models\ServiceZone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueServiceZone implements ValidationRule
{
    public function __construct(
        private readonly mixed $barangay,
        private readonly int|string|null $exceptId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! is_string($this->barangay) || trim($this->barangay) === '') {
            return;
        }

        if (ServiceZone::duplicateExists($value, $this->barangay, $this->exceptId)) {
            $fail('This service area already exists in the selected Barangay.');
        }
    }
}
