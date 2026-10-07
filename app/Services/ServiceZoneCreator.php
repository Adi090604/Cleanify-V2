<?php

namespace App\Services;

use App\Models\ServiceZone;
use Illuminate\Support\Arr;

class ServiceZoneCreator
{
    public function create(array $attributes): ServiceZone
    {
        return ServiceZone::create(Arr::only($attributes, [
            'name',
            'barangay',
            'status',
            'latitude',
            'longitude',
        ]));
    }
}
