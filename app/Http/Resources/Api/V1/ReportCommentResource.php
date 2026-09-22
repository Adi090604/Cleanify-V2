<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profilePhotoUrl = null;

        if ($this->user?->profile_photo_path && Storage::disk('public')->exists($this->user->profile_photo_path)) {
            $profilePhotoUrl = $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->user->profile_photo_path, '/');
        }

        return [
            'id' => $this->id,
            'author' => $this->user?->name ?? 'Cleanify User',
            'author_initial' => $this->user?->getAvatarInitial() ?? '?',
            'profile_photo_url' => $profilePhotoUrl,
            'comment' => $this->comment,
            'timestamp' => $this->created_at?->diffForHumans() ?? 'Just now',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
