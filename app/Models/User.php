<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'is_admin',
        'banned_at',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'service_area',
        'notification_preferences',
        'show_email',
        'location_sharing',
        'profile_visibility',
        'tracker_refresh_interval',
        'language',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'banned_at' => 'datetime',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'notification_preferences' => 'array',
            'show_email' => 'boolean',
            'location_sharing' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Check if the user is banned.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Get the first letter of the user's name for avatar.
     */
    public function getAvatarInitial(): string
    {
        return strtoupper(substr($this->name, 0, 1));
    }

    /**
     * Get a consistent color based on the user's name.
     * Returns an array with background color classes.
     */
    public function getAvatarColor(): array
    {
        // Predefined color palette
        $colors = [
            ['bg-red-500', 'bg-red-600'],
            ['bg-orange-500', 'bg-orange-600'],
            ['bg-amber-500', 'bg-amber-600'],
            ['bg-yellow-500', 'bg-yellow-600'],
            ['bg-lime-500', 'bg-lime-600'],
            ['bg-green-500', 'bg-green-600'],
            ['bg-emerald-500', 'bg-emerald-600'],
            ['bg-teal-500', 'bg-teal-600'],
            ['bg-cyan-500', 'bg-cyan-600'],
            ['bg-sky-500', 'bg-sky-600'],
            ['bg-blue-500', 'bg-blue-600'],
            ['bg-indigo-500', 'bg-indigo-600'],
            ['bg-violet-500', 'bg-violet-600'],
            ['bg-purple-500', 'bg-purple-600'],
            ['bg-fuchsia-500', 'bg-fuchsia-600'],
            ['bg-pink-500', 'bg-pink-600'],
            ['bg-rose-500', 'bg-rose-600'],
        ];

        // Use the first letter to determine color index
        $firstLetter = strtoupper(substr($this->name, 0, 1));
        $charCode = ord($firstLetter);
        
        // Map A-Z to 0-25, then modulo by color count
        $colorIndex = ($charCode - 65) % count($colors);
        
        return $colors[$colorIndex];
    }

    /**
     * Get the avatar background gradient classes.
     */
    public function getAvatarBgClasses(): string
    {
        $colors = $this->getAvatarColor();
        return "bg-gradient-to-br {$colors[0]} {$colors[1]}";
    }

    /**
     * Get the reports created by this user.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

}
