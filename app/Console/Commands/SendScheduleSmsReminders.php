<?php

namespace App\Console\Commands;

use App\Models\SmsNotification;
use App\Models\EmailNotification;
use App\Models\User;
use App\Mail\GarbageCollectionReminder;
use App\Services\ScheduleReminderService;
use App\Services\SmsNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduleSmsReminders extends Command
{
    protected $signature = 'sms:send-schedule-reminders {--date= : Collection date (Y-m-d), for safe verification} {--dry-run : List eligible reminders without logging or sending}';
    protected $description = 'Send one-day-before garbage collection SMS reminders.';

    public function handle(ScheduleReminderService $scheduleReminders, SmsNotificationService $sms): int
    {
        $timezone = config('sms.timezone');
        $collectionDate = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'), $timezone)->startOfDay()
            : now($timezone)->addDay()->startOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $eligibleSms = 0;
        $eligibleEmail = 0;

        foreach ($scheduleReminders->schedulesForDate($collectionDate) as $schedule) {
            User::where('is_admin', false)
                ->where('service_area', $schedule->area)
                ->where('sms_notifications', true)
                ->cursor()
                ->each(function (User $user) use ($collectionDate, $schedule, $sms, $dryRun, &$eligibleSms) {
                    if (($user->notification_preferences['schedule_reminders'] ?? true) !== true) return;

                    // Read and normalize the profile phone at dispatch time; Schedule never stores it.
                    $phone = $sms->normalizePhoneNumber($user->phone);
                    if (!$phone) {
                        Log::warning('Schedule SMS reminder skipped because the user phone is missing or invalid.', ['user_id' => $user->id, 'schedule_id' => $schedule->id]);
                        return;
                    }

                    $message = "Cleanify Reminder: Your garbage collection is scheduled for tomorrow in {$schedule->area}. Please prepare your waste for collection. Thank you.";
                    ++$eligibleSms;
                    if ($dryRun) {
                        $this->line("Would remind user {$user->id} for schedule {$schedule->id} at {$phone}.");
                        return;
                    }

                    try {
                        $notification = SmsNotification::create([
                            'user_id' => $user->id,
                            'schedule_id' => $schedule->id,
                            'collection_date' => $collectionDate,
                            'reminder_type' => 'one_day_before',
                            'phone_number_snapshot' => $phone,
                            'message' => $message,
                            'scheduled_for' => now(),
                            'status' => 'pending',
                        ]);
                    } catch (QueryException $exception) {
                        // The unique key guarantees repeated scheduler runs do not send duplicates.
                        return;
                    }

                    try {
                        $sent = $sms->send($phone, $message);
                        $notification->update($sent ? ['status' => 'sent', 'sent_at' => now()] : ['status' => 'simulated']);
                    } catch (\Throwable $exception) {
                        $notification->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
                        Log::error('Schedule SMS reminder failed.', ['sms_notification_id' => $notification->id, 'exception' => $exception]);
                    }
                });

            User::where('is_admin', false)
                ->where('service_area', $schedule->area)
                ->where('email_notifications', true)
                ->cursor()
                ->each(function (User $user) use ($collectionDate, $schedule, $dryRun, &$eligibleEmail) {
                    if (($user->notification_preferences['schedule_reminders'] ?? true) !== true) return;

                    $email = filter_var($user->email, FILTER_VALIDATE_EMAIL);
                    if (!$email) {
                        Log::warning('Schedule email reminder skipped because the user email is missing or invalid.', ['user_id' => $user->id, 'schedule_id' => $schedule->id]);
                        return;
                    }

                    $subject = 'Cleanify Garbage Collection Reminder';
                    $message = "Hello {$user->name},\n\nThis is a reminder that your garbage collection is scheduled for tomorrow in {$schedule->area}.\n\nPlease prepare your waste for collection.\n\nThank you,\nCleanify";
                    ++$eligibleEmail;
                    if ($dryRun) {
                        $this->line("Would email user {$user->id} for schedule {$schedule->id} at {$email}.");
                        return;
                    }

                    try {
                        $notification = EmailNotification::create([
                            'user_id' => $user->id,
                            'schedule_id' => $schedule->id,
                            'collection_date' => $collectionDate,
                            'reminder_type' => 'one_day_before',
                            'email_address' => $email,
                            'subject' => $subject,
                            'message' => $message,
                            'scheduled_for' => now(),
                            'status' => 'pending',
                        ]);
                    } catch (QueryException $exception) {
                        // The channel-specific unique key prevents repeated scheduler runs from emailing twice.
                        return;
                    }

                    try {
                        Mail::to($email)->send(new GarbageCollectionReminder($user, $schedule));
                        $notification->update(['status' => 'sent', 'sent_at' => now()]);
                    } catch (\Throwable $exception) {
                        $notification->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
                        Log::error('Schedule email reminder failed.', ['email_notification_id' => $notification->id, 'exception' => $exception]);
                    }
                });
        }

        $this->info("{$eligibleSms} eligible schedule SMS reminder(s) and {$eligibleEmail} eligible schedule email reminder(s) processed" . ($dryRun ? ' (dry run).' : '.'));

        return self::SUCCESS;
    }
}
