<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Notifications\Notification;

class OrderChangedByAdminNotification extends Notification
{
    public function __construct(public Order $order, public string $fromStatus, public string $reason) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'order',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => "PickSell Admin updated order #{$this->order->order_number} from "
                . OrderLifecycleService::label($this->fromStatus) . ' to ' . OrderLifecycleService::label($this->order->status)
                . ". Reason: {$this->reason}",
        ];
    }
}
