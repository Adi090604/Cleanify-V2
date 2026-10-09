<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleCreatedNotification extends Notification
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

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Garbage Collection Schedule - Cleanify')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('A new garbage collection schedule has been created for your area.')
            ->line('**Schedule Details:**')
            ->line('📍 Area: '.$this->schedule->area)
            ->line('📅 Days: '.($this->schedule->schedule_type === 'specific_date' ? $this->schedule->specific_date?->format('M d, Y') : $this->schedule->days))
            ->line('⏰ Time: '.$this->schedule->time_range)
            ->line('🚛 Truck: '.$this->schedule->truck)
            ->line('✅ Status: '.ucfirst($this->schedule->status))
            ->action('View Schedule', route('garbage-schedule'))
            ->line('Please prepare your garbage for collection on the scheduled days.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $daysOrDate = $this->schedule->schedule_type === 'specific_date'
            ? $this->schedule->specific_date?->format('M d, Y')
            : $this->schedule->days;

        return [
            'schedule_id' => $this->schedule->id,
            'area' => $this->schedule->area,
            'status' => $this->schedule->status,
            'schedule_type' => $this->schedule->schedule_type,
            'days' => $this->schedule->days,
            'specific_date' => $this->schedule->specific_date?->format('Y-m-d'),
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
