<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/** A parcel the buyer did not receive is coming back to the seller or is held for them; a strike; a shop hold. */
class SellerReturnUpdate extends Notification
{
    public function __construct(public ?Order $order, public string $message) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return array_filter([
            'order_id' => $this->order?->id,
            'order_number' => $this->order?->order_number,
            'message' => $this->message,
        ]);
    }
}
