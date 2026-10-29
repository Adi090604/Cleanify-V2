<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\Schedule;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleCreatedNotification extends Notification implements SendsSmsNotification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Schedule $schedule
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable->notificationPreferenceEnabled('schedule_reminders', 'schedule')) {
            return [];
        }

        $channels = ['database'];

        if ($notifiable->email_notifications ?? true) {
            $channels[] = 'mail';
        }

        if ($notifiable->sms_notifications ?? false) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Garbage Collection Schedule - Cleanify')
            ->view('emails.cleanify-notification', [
                'title' => 'New Garbage Collection Schedule',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => ['A new garbage collection schedule has been created for your area.'],
                'details' => [
                    ['label' => 'Area', 'value' => $this->schedule->area],
                    ['label' => 'Collection', 'value' => $this->schedule->collection_label],
                    ['label' => 'Time', 'value' => $this->schedule->time_range],
                    ['label' => 'Truck', 'value' => $this->schedule->truck],
                    ['label' => 'Status', 'value' => ucfirst($this->schedule->status)],
                ],
                'actionText' => 'View Schedule',
                'actionUrl' => route('garbage-schedule'),
                'closing' => ['Please prepare your garbage for collection on the scheduled days.'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return "Cleanify: New collection schedule for {$this->schedule->area}: {$this->schedule->collection_label}, {$this->schedule->time_range}. Check Cleanify for details.";
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $daysOrDate = $this->schedule->collection_label;

        return [
            'schedule_id' => $this->schedule->id,
            'area' => $this->schedule->area,
            'status' => $this->schedule->status,
            'schedule_type' => $this->schedule->schedule_type,
            'days' => $this->schedule->days,
            'specific_date' => $this->schedule->specific_date?->format('Y-m-d'),
            'recurrence_start_date' => $this->schedule->recurrence_start_date?->format('Y-m-d'),
            'recurrence_interval_days' => $this->schedule->recurrence_interval_days,
            'time_start' => $this->schedule->time_start,
            'time_end' => $this->schedule->time_end,
            'truck' => $this->schedule->truck,
            'category' => 'schedule',
            'title' => 'New garbage collection schedule for '.$this->schedule->area,
            'message' => 'Schedule: '.$daysOrDate.' at '.$this->schedule->time_range,
            'icon' => 'fa-calendar-alt',
            'color' => 'bg-blue-600',
            'url' => '/garbage-schedule',
        ];
    }
}
