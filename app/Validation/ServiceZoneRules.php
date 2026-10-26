<?php

namespace App\Validation;

use App\Rules\UniqueServiceZone;

final class ServiceZoneRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function create(mixed $barangay = null, int|string|null $exceptId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueServiceZone($barangay, $exceptId)],
            'barangay' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
