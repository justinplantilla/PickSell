<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class CourierAssignedNotification extends Notification
{
    public function __construct(private Order $order) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'event' => 'logistics.rider_assigned',
            'title' => 'Parcel assigned to you',
            'message' => "Order {$this->order->order_number} has been assigned to you.",
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'url' => route('courier.orders', [], false),
        ];
    }
}
