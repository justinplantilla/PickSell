<?php

namespace App\Notifications;

use App\Mail\RegistrationDisapprovedMail;
use Illuminate\Notifications\Notification;

class RegistrationDisapprovedNotification extends Notification
{
    public function __construct(public string $reason) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): RegistrationDisapprovedMail
    {
        return (new RegistrationDisapprovedMail($notifiable, $this->reason))->to($notifiable->email);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message' => 'Your PickSell registration was not approved.',
            'reason' => $this->reason,
        ];
    }
}
