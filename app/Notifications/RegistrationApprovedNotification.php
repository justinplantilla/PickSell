<?php

namespace App\Notifications;

use App\Mail\RegistrationApprovedMail;
use Illuminate\Notifications\Notification;

class RegistrationApprovedNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): RegistrationApprovedMail
    {
        return (new RegistrationApprovedMail($notifiable))->to($notifiable->email);
    }

    public function toDatabase($notifiable): array
    {
        return ['message' => 'Your PickSell registration has been approved. Welcome aboard!'];
    }
}
