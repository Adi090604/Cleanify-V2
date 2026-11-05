<?php

namespace App\Notifications;

use App\Contracts\SendsSmsNotification;
use App\Models\ModerationAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ModerationActionNotification extends Notification implements SendsSmsNotification
{
    use Queueable;

    public function __construct(public ModerationAction $action) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable->notificationPreferenceEnabled('system')) {
            return [];
        }

        $channels = ['database'];

        if ($notifiable->email_notifications ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $details = [['label' => 'Action', 'value' => $this->action->label()]];

        if ($this->action->resident_reason) {
            $details[] = ['label' => 'Reason', 'value' => $this->action->resident_reason];
        }

        if ($this->action->expires_at) {
            $details[] = ['label' => 'Until', 'value' => $this->action->expires_at->format('F d, Y \a\t h:i A')];
        }

        return (new MailMessage)
            ->subject($this->action->label().' - Cleanify')
            ->view('emails.cleanify-notification', [
                'title' => $this->action->label(),
                'greeting' => 'Hello '.$notifiable->name.'!',
                'intro' => [$this->residentMessage()],
                'details' => $details,
                'actionText' => $this->action->action_type === ModerationAction::SUSPENSION ? 'Open Cleanify' : 'View Cleanify',
                'actionUrl' => $this->action->action_type === ModerationAction::SUSPENSION ? route('login') : route('settings'),
                'closing' => ['If you need assistance, please contact a Cleanify administrator.'],
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return match ($this->action->action_type) {
            ModerationAction::WARNING => 'Cleanify: Your account received a warning for violating community guidelines. Please review Cleanify for details.',
            ModerationAction::COMMUNITY_RESTRICTION => 'Cleanify: Community Reports is read-only for your account until '.$this->smsExpiration().'. You can still view reports and use other Cleanify services.',
            ModerationAction::REPORT_SUBMISSION_RESTRICTION => 'Cleanify: New report submission is restricted until '.$this->smsExpiration().'. Other Cleanify services remain available.',
            ModerationAction::SUSPENSION => 'Cleanify: Your account has been suspended following an Admin review. Check your email for available details.',
            default => 'Cleanify: Your account moderation status was updated.',
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'moderation_action_id' => $this->action->id,
            'user_report_id' => $this->action->user_report_id,
            'category' => 'system',
            'title' => $this->action->label(),
            'message' => $this->residentMessage(),
            'expires_at' => $this->action->expires_at?->toIso8601String(),
            'icon' => 'fa-user-shield',
            'color' => $this->action->action_type === ModerationAction::WARNING ? 'bg-amber-600' : 'bg-red-600',
            'url' => '/settings',
        ];
    }

    public function residentMessage(): string
    {
        $message = match ($this->action->action_type) {
            ModerationAction::WARNING => 'Your account received a warning for violating Cleanify community guidelines.',
            ModerationAction::COMMUNITY_RESTRICTION => 'Your Community Reports access is temporarily read-only until '.$this->expiration().'. You can still view reports and use other Cleanify services.',
            ModerationAction::REPORT_SUBMISSION_RESTRICTION => 'New cleanliness report submission is restricted until '.$this->expiration().'. You can still access other Cleanify services.',
            ModerationAction::SUSPENSION => 'Your account has been suspended following an administrator review.',
            default => 'Your Cleanify account moderation status was updated.',
        };

        return $this->action->resident_reason ? $message.' Reason: '.$this->action->resident_reason : $message;
    }

    private function expiration(): string
    {
        return $this->action->expires_at?->format('M d, Y \a\t g:i A') ?? 'further notice';
    }

    private function smsExpiration(): string
    {
        return $this->action->expires_at?->format('M d, Y') ?? 'further notice';
    }
}
