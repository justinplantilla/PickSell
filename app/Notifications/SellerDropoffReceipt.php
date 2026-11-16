<?php

namespace App\Notifications;

use App\Pickrush\Models\DropoffBatch;
use Illuminate\Notifications\Notification;

/** The station confirmed a bulk drop-off: what it received and what is still missing. */
class SellerDropoffReceipt extends Notification
{
    public function __construct(public DropoffBatch $batch) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $received = $this->batch->parcels->filter(fn ($p) => DropoffBatch::arrived($p))->count();
        $missing = $this->batch->parcel_count - $received;

        return [
            'type' => 'order',
            'icon' => 'package',
            'title' => 'Drop-off received',
            'dropoff_batch_id' => $this->batch->id,
            'url' => route('seller.dropoffs.show', $this->batch, false),
            'message' => ($this->batch->originHub?->name ?? 'The station')." received {$received} of {$this->batch->parcel_count} parcels on drop-off {$this->batch->batch_no}"
                .' ('.$this->batch->received_at?->format('M d, h:i A').', '.($this->batch->receiver?->full_name ?? 'station staff').').'
                .($missing ? " {$missing} not dropped off yet: bring them before their deadline or those orders are cancelled." : ''),
        ];
    }
}
