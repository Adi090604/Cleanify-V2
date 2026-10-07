<?php

namespace App\Validation;

final class ServiceZoneRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function create(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:service_zones,name'],
            'barangay' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
