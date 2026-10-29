<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceAreaRequestStatusNotification extends Notification implements SendsSmsNotification
{
    use Queueable;

    public const EVENT_APPROVED = 'approved';

    public const EVENT_REJECTED = 'rejected';

    public const EVENT_ZONE_CREATED = 'zone_created';

    public function __construct(
        public ServiceAreaRequest $serviceAreaRequest,
        public string $eventType,
        public ?ServiceZone $serviceZone = null,
        public bool $requesterAssigned = false
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
            && ($preferences['system'] ?? true)) {
            $channels[] = 'mail';
        }

        if (($notifiable->sms_notifications ?? false)
            && ($preferences['system'] ?? true)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->mailSubject())
            ->view('emails.cleanify-notification', [
                'title' => $this->title(),
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$this->message()],
                'actionText' => 'View Service Area Request',
                'actionUrl' => route('settings'),
                'closing' => ['You can review the latest status in Cleanify Settings.'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $requestedArea = $this->requestedAreaName();

        return match ($this->eventType) {
            self::EVENT_APPROVED => "Cleanify: Your service area request for {$requestedArea} was approved. Check Cleanify for details.",
            self::EVENT_REJECTED => "Cleanify: Your service area request for {$requestedArea} was not approved. Check Cleanify for details.",
            self::EVENT_ZONE_CREATED => $this->zoneCreatedSms(),
            default => 'Cleanify: Your service area request was updated. Check Cleanify for details.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'service_area_request',
            'event_type' => $this->eventType,
            'service_area_request_id' => $this->serviceAreaRequest->id,
            'service_zone_id' => $this->serviceZone?->id,
            'category' => 'system',
            'title' => $this->title(),
            'message' => $this->message(),
            'icon' => 'fa-map-marker-alt',
            'color' => $this->color(),
            'url' => '/settings',
        ];
    }

    private function title(): string
    {
        return match ($this->eventType) {
            self::EVENT_APPROVED => 'Service Area Request Approved',
            self::EVENT_REJECTED => 'Service Area Request Not Approved',
            self::EVENT_ZONE_CREATED => 'Service Area Added',
            default => 'Service Area Request Updated',
        };
    }

    private function message(): string
    {
        return match ($this->eventType) {
            self::EVENT_APPROVED => 'Your service area request has been approved for service-area setup. A collection schedule has not been created by this approval.',
            self::EVENT_REJECTED => 'Your service area request was not approved. You may submit a new request if your area details change.',
            self::EVENT_ZONE_CREATED => $this->zoneCreatedMessage(),
            default => 'The status of your service area request has changed.',
        };
    }

    private function zoneCreatedMessage(): string
    {
        $zoneName = $this->serviceZoneName();

        if ($this->requesterAssigned) {
            return $zoneName.' has been added and is now your Cleanify service area. Collection scheduling is managed separately.';
        }

        return 'An official Cleanify service area has been created for your approved request: '.$zoneName.'. Collection scheduling is managed separately.';
    }

    private function zoneCreatedSms(): string
    {
        $area = $this->serviceZone
            ? $this->formatArea($this->serviceZone->name, $this->serviceZone->barangay)
            : $this->requestedAreaName();

        if ($this->requesterAssigned) {
            return "Cleanify: Your area, {$area}, has been added and is now your collection service area.";
        }

        return "Cleanify: Your requested area, {$area}, has been added as an official collection service area. Check Cleanify for details.";
    }

    private function requestedAreaName(): string
    {
        return $this->formatArea(
            $this->serviceAreaRequest->area_name,
            $this->serviceAreaRequest->barangay
        );
    }

    private function formatArea(string $area, ?string $barangay): string
    {
        return $barangay ? $area.', '.$barangay : $area;
    }

    private function mailSubject(): string
    {
        return match ($this->eventType) {
            self::EVENT_APPROVED => 'Cleanify Service Area Request Approved',
            self::EVENT_REJECTED => 'Cleanify Service Area Request Update',
            self::EVENT_ZONE_CREATED => 'Cleanify Service Area Added',
            default => 'Cleanify Service Area Request Updated',
        };
    }

    private function color(): string
    {
        return match ($this->eventType) {
            self::EVENT_REJECTED => 'bg-red-600',
            self::EVENT_ZONE_CREATED => 'bg-blue-600',
            default => 'bg-green-600',
        };
    }

    private function serviceZoneName(): string
    {
        return $this->serviceZone?->display_name ?? 'an official Cleanify service area';
    }
}
