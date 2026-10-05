<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductModerationLog;
use Illuminate\Notifications\Notification;

/** Tells a seller what an Admin did to one of their products, and why. */
class ProductModeratedNotification extends Notification
{
    public function __construct(public Product $product, public ProductModerationLog $log) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = match ($this->log->action) {
            ProductModerationLog::ARCHIVED => "Your product '{$this->product->name}' was archived by PickSell Admin and is hidden from buyers.",
            ProductModerationLog::RESTORED => "Your product '{$this->product->name}' was restored by PickSell Admin and is visible to buyers again.",
            ProductModerationLog::FEATURED => "Your product '{$this->product->name}' is now a Featured Product.",
            ProductModerationLog::UNFEATURED => "Your product '{$this->product->name}' was removed from Featured Products.",
            default => "Your product '{$this->product->name}' was updated by PickSell Admin.",
        };

        return array_filter([
            'product_id' => $this->product->id,
            'action' => $this->log->action,
            'message' => $this->log->reason ? "{$message} Reason: {$this->log->reason}" : $message,
            'reason' => $this->log->reason,
        ]);
    }
}
