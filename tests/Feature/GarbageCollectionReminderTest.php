<?php

use App\Mail\GarbageCollectionReminder;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('schedule reminder email includes the canonical collection time range', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Alex',
        'service_area' => 'Barangay One',
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);

    $schedule = Schedule::create([
        'area' => 'Barangay One',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-09-16',
        'days' => null,
        'time_start' => '08:00',
        'time_end' => '09:00',
        'truck' => 'Truck 1',
        'status' => 'active',
    ]);

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-09-16'])
        ->assertSuccessful();

    $message = "Hello Alex,\n\nThis is a reminder that your garbage collection is scheduled for tomorrow in Barangay One.\n\nCollection Time: 8:00 AM - 9:00 AM\n\nPlease prepare your waste for collection.\n\nThank you,\nCleanify";

    $this->assertDatabaseHas('email_notifications', [
        'user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'subject' => 'Cleanify Garbage Collection Reminder',
        'message' => $message,
    ]);

    Mail::assertSent(GarbageCollectionReminder::class, function (GarbageCollectionReminder $mail) {
        return $mail->envelope()->subject === 'Cleanify Garbage Collection Reminder'
            && str_contains($mail->render(), 'Collection Time: 8:00 AM - 9:00 AM');
    });
});
