<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class SellerDeliveryReceived extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => 'Delivery for order #' . $this->order->order_number . ' has been received and confirmed.',
        ];
    }
}