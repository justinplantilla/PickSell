<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class BuyerOrderUpdate extends Notification
{
    public function __construct(public Order $order, public string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => $this->message,
            'status' => $this->order->status,
        ];
    }
}
