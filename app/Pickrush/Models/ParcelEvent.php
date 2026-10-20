<?php

namespace App\Pickrush\Models;

use App\Pickrush\Enums\ParcelStatus;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only scan history: the audit trail and the tracking timeline. */
class ParcelEvent extends Model
{
    protected $table = 'pr_parcel_events';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ParcelStatus::class,
        'meta' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Parcel events are append-only.'));
        static::deleting(fn () => throw new LogicException('Parcel events are append-only.'));
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class, 'hub_id');
    }

    public function toHub()
    {
        return $this->belongsTo(Hub::class, 'to_hub_id');
    }

    /** Buyer-facing sentence, naming the hub so the buyer knows where the parcel is. */
    public function headline(): string
    {
        $hub = $this->hub?->name;
        $to = $this->toHub?->name;

        return match ($this->status) {
            ParcelStatus::WaitingPickup => 'Shipment created, waiting for pickup',
            ParcelStatus::PickedUp => 'Picked up from the seller',
            ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation => 'Arrived at '.($hub ?? 'hub'),
            ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation => 'Departed '.($hub ?? 'hub').($to ? ' → '.$to : ''),
            ParcelStatus::AssignedToRider => 'Rider assigned for delivery',
            ParcelStatus::OutForDelivery => 'Out for delivery',
            ParcelStatus::Delivered => 'Delivered',
            ParcelStatus::DeliveryFailed => 'Delivery attempt unsuccessful',
            ParcelStatus::OnHold => 'On hold at '.($hub ?? 'hub'),
            ParcelStatus::ReturningToSender => 'Returning to the seller',
            ParcelStatus::ReturnedToSender => 'Returned to the seller',
            ParcelStatus::Cancelled => 'Shipment cancelled',
            ParcelStatus::Lost => 'Reported lost; our team is investigating',
        };
    }
}
