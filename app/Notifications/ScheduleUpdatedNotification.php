<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\Schedule;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleUpdatedNotification extends Notification implements SendsSmsNotification
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

    public function toMail(object $notifiable): MailMessage
    {
        $additional = [];

        if ($this->changeKind === self::CHANGE_REMOVED_FROM_AREA) {
            $additional[] = 'This schedule is now assigned to '.$this->schedule->area.'.';
        }

        return (new MailMessage)
            ->subject('Cleanify Collection Schedule Updated')
            ->view('emails.cleanify-notification', [
                'title' => 'Collection Schedule Updated',
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$this->mailIntroduction()],
                'details' => [
                    ['label' => 'Service Area', 'value' => $this->displayArea()],
                    ['label' => 'Collection', 'value' => $this->collectionLabel()],
                    ['label' => 'Time', 'value' => $this->schedule->time_range],
                    ['label' => 'Assigned Truck', 'value' => $this->schedule->truck],
                    ['label' => 'Status', 'value' => ucfirst($this->schedule->status)],
                ],
                'additional' => $additional,
                'actionText' => 'View Schedule',
                'actionUrl' => route('garbage-schedule'),
                'closing' => ['Please check Cleanify for the latest collection schedule.'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return match ($this->changeKind) {
            self::CHANGE_REMOVED_FROM_AREA => 'Cleanify: A collection schedule no longer applies to '.$this->displayArea().'. Check Cleanify for the latest schedule.',
            self::CHANGE_ASSIGNED_TO_AREA => 'Cleanify: A collection schedule is now assigned to '.$this->schedule->area.': '.$this->collectionSummary().'. Check Cleanify for details.',
            default => 'Cleanify: Your garbage collection schedule for '.$this->schedule->area.' was updated: '.$this->collectionSummary().'. Check Cleanify for details.',
        };
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
            'recurrence_start_date' => $this->schedule->recurrence_start_date?->format('Y-m-d'),
            'recurrence_interval_days' => $this->schedule->recurrence_interval_days,
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
        return $this->schedule->collection_label;
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
