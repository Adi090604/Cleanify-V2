<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsNotification extends Model
{
    protected $fillable = [
        'user_id', 'schedule_id', 'collection_date', 'reminder_type',
        'phone_number_snapshot', 'message', 'scheduled_for', 'sent_at',
        'status', 'error_message',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
