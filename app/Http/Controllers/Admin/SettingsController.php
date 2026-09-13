<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the admin settings page.
     */
    public function index(): View
    {
        return view('admin.settings', [
            'activePage' => 'settings',
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update the admin's profile information.
     */
    public function updateProfile(Request $request, ProfilePhotoService $profilePhotos): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ]);

        $user = auth()->user();
        $oldPhotoPath = $user->profile_photo_path;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($request->hasFile('profile_photo')) {
            $user->profile_photo_path = $profilePhotos->store($request->file('profile_photo'));
        } elseif ($request->boolean('remove_profile_photo')) {
            $user->profile_photo_path = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($oldPhotoPath !== $user->profile_photo_path) {
            $profilePhotos->delete($oldPhotoPath);
        }

        return redirect()->route('admin.settings')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Update the admin's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.settings')
            ->with('success', 'Password updated successfully!');
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
        ]);

        // Convert checkbox values (1/0 or null) to boolean
        $preferences = [
            'email_notifications' => $request->has('email_notifications'),
            'sms_notifications' => $request->has('sms_notifications'),
            'push_notifications' => $request->has('push_notifications'),
        ];

        auth()->user()->update($preferences);

        return redirect()->route('admin.settings')
            ->with('success', 'Notification preferences updated successfully!');
    }

}
