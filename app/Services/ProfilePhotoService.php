<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfilePhotoService
{
    public function store(UploadedFile $photo): string
    {
        $path = $photo->store('profile-photos', 'public');

        if (!$path) {
            throw new RuntimeException('The profile photo could not be saved. Please try another image.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
