<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photoUrl = null;
        if ($this->profile_photo_path && Storage::disk('public')->exists($this->profile_photo_path)) {
            $photoUrl = $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->profile_photo_path, '/');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => (bool) $this->is_admin,
            'profile_photo_url' => $photoUrl,
        ];
    }
}
