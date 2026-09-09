<?php

namespace App\Mail;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GarbageCollectionReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Schedule $schedule,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cleanify Garbage Collection Reminder');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.garbage-collection-reminder');
    }
}
