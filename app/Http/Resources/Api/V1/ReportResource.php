<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'Cleanify User',
                'initial' => $this->user?->getAvatarInitial() ?? 'C',
            ],
            'description' => $this->description,
            'location' => $this->location,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'image_url' => $this->image_path && Storage::disk('public')->exists($this->image_path)
                ? $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->image_path, '/')
                : null,
            'status' => $this->status,
            'priority' => $this->priority,
            'created_at' => $this->created_at?->toIso8601String(),
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'followers_count' => $this->followers_count,
            'is_liked' => $this->likes->isNotEmpty(),
        ];
    }
}
