<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleUpdatedNotification extends Notification
{
    use Queueable;

    public const CHANGE_UPDATED = 'updated';

    public const CHANGE_REMOVED_FROM_AREA = 'removed_from_area';

    public const CHANGE_ASSIGNED_TO_AREA = 'assigned_to_area';

    /**
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public Schedule $schedule,
        public string $changeKind = self::CHANGE_UPDATED,
        public ?string $previousArea = null,
        public array $changedFields = []
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $preferences = $notifiable->notification_preferences ?? [];

        if (($notifiable->email_notifications ?? true)
            && ($preferences['schedule'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Cleanify Collection Schedule Updated')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->mailIntroduction())
            ->line('**Service Area:**')
            ->line($this->displayArea())
            ->line('**Collection:**')
            ->line($this->collectionLabel())
            ->line('**Time:**')
            ->line($this->schedule->time_range)
            ->line('**Assigned Truck:**')
            ->line($this->schedule->truck)
            ->line('**Status:**')
            ->line(ucfirst($this->schedule->status));

        if ($this->changeKind === self::CHANGE_REMOVED_FROM_AREA) {
            $mail->line('This schedule is now assigned to '.$this->schedule->area.'.');
        }

        return $mail
            ->action('View Schedule', route('garbage-schedule'))
            ->line('Please check Cleanify for the latest collection schedule.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'schedule_updated',
            'schedule_id' => $this->schedule->id,
            'change_kind' => $this->changeKind,
            'changed_fields' => $this->changedFields,
            'service_zone' => $this->displayArea(),
            'previous_service_zone' => $this->previousArea,
            'new_service_zone' => $this->schedule->area,
            'schedule_type' => $this->schedule->schedule_type,
            'days' => $this->schedule->days,
            'specific_date' => $this->schedule->specific_date?->format('Y-m-d'),
            'time_start' => $this->schedule->time_start?->format('H:i'),
            'time_end' => $this->schedule->time_end?->format('H:i'),
            'time_range' => $this->schedule->time_range,
            'assigned_truck' => $this->schedule->truck,
            'truck' => $this->schedule->truck,
            'status' => $this->schedule->status,
            'schedule_updated_at' => $this->schedule->updated_at?->toIso8601String(),
            'category' => 'schedule',
            'title' => $this->title(),
            'message' => $this->message(),
            'icon' => 'fa-calendar-alt',
            'color' => 'bg-blue-600',
            'url' => '/garbage-schedule',
        ];
    }

    private function collectionLabel(): string
    {
        if ($this->schedule->schedule_type === 'specific_date') {
            return $this->schedule->specific_date?->format('l, F j, Y') ?? 'Date not set';
        }

        return $this->schedule->days ?: 'Days not set';
    }

    private function displayArea(): string
    {
        return $this->changeKind === self::CHANGE_REMOVED_FROM_AREA
            ? ($this->previousArea ?: $this->schedule->area)
            : $this->schedule->area;
    }

    private function title(): string
    {
        return match ($this->changeKind) {
            self::CHANGE_REMOVED_FROM_AREA => 'Collection Schedule Changed for Your Area',
            self::CHANGE_ASSIGNED_TO_AREA => 'Collection Schedule Assigned to Your Area',
            default => 'Collection Schedule Updated',
        };
    }

    private function message(): string
    {
        return match ($this->changeKind) {
            self::CHANGE_REMOVED_FROM_AREA => 'The collection schedule for '.$this->displayArea().' has changed and no longer applies to your service area.',
            self::CHANGE_ASSIGNED_TO_AREA => 'A collection schedule is now assigned to '.$this->schedule->area.': '.$this->collectionSummary().'.',
            default => 'Your garbage collection schedule for '.$this->schedule->area.' has been updated: '.$this->collectionSummary().'.',
        };
    }

    private function mailIntroduction(): string
    {
        return match ($this->changeKind) {
            self::CHANGE_REMOVED_FROM_AREA => 'A collection schedule has changed and no longer applies to your service area.',
            self::CHANGE_ASSIGNED_TO_AREA => 'A collection schedule is now assigned to your service area.',
            default => 'Your collection schedule has been updated.',
        };
    }

    private function collectionSummary(): string
    {
        return $this->collectionLabel().' at '.$this->schedule->time_range;
    }
}
