<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceAreaRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'area_name' => $this->area_name,
            'barangay' => $this->barangay,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'details' => $this->details,
            'status' => $this->status,
            'service_zone' => $this->whenLoaded('serviceZone', fn () => $this->serviceZone ? [
                'id' => $this->serviceZone->id,
                'name' => $this->serviceZone->name,
                'display_name' => $this->serviceZone->display_name,
                'status' => $this->serviceZone->status,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
