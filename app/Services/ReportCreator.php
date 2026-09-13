<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ReportCreator
{
    public function create(User $user, array $validated, ?UploadedFile $image = null): Report
    {
        $imagePath = $image?->store('reports', 'public');

        if ($image && !$imagePath) {
            throw new RuntimeException('The image could not be saved. Please try another file.');
        }

        return Report::create([
            'user_id' => $user->id,
            'location' => $validated['location'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => $validated['description'],
            'image_path' => $imagePath,
            'status' => 'pending',
            'priority' => 'medium',
        ]);
    }
}
