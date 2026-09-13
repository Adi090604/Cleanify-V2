<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilePhotoController extends Controller
{
    public function store(Request $request, ProfilePhotoService $profilePhotos): JsonResponse
    {
        $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $oldPhotoPath = $user->profile_photo_path;
        $user->profile_photo_path = $profilePhotos->store($request->file('profile_photo'));
        $user->save();
        $profilePhotos->delete($oldPhotoPath);

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function destroy(Request $request, ProfilePhotoService $profilePhotos): JsonResponse
    {
        $user = $request->user();
        $oldPhotoPath = $user->profile_photo_path;
        $user->profile_photo_path = null;
        $user->save();
        $profilePhotos->delete($oldPhotoPath);

        return response()->json([
            'message' => 'Profile photo removed successfully.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }
}
