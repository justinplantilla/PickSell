<?php

namespace App\Pickrush\Enums;

/**
 * Parcel lifecycle. Every change is a pr_parcel_events row; pr_parcels.current_status is a cached
 * copy of the latest event. Hub statuses repeat at every hub a parcel passes through.
 */
enum ParcelStatus: string
{
    case WaitingPickup = 'waiting_pickup';
    case PickedUp = 'picked_up';
    case AtOriginHub = 'at_origin_hub';
    case InTransitToProvince = 'in_transit_to_province';
    case AtProvinceHub = 'at_province_hub';
    case InTransitToStation = 'in_transit_to_station';
    case ArrivedAtStation = 'arrived_at_station';
    case AssignedToRider = 'assigned_to_rider';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case OnHold = 'on_hold';
    case ReturningToSender = 'returning_to_sender';
    case ReturnedToSender = 'returned_to_sender';
    case Cancelled = 'cancelled';
    case Lost = 'lost';

    /** status => statuses it may move to */
    private const TRANSITIONS = [
        'waiting_pickup' => ['picked_up', 'at_origin_hub', 'cancelled'],
        'picked_up' => ['at_origin_hub', 'on_hold'],
        'at_origin_hub' => ['in_transit_to_province', 'assigned_to_rider', 'on_hold', 'returned_to_sender'],
        'in_transit_to_province' => ['at_province_hub', 'lost'],
        'at_province_hub' => ['in_transit_to_province', 'in_transit_to_station', 'on_hold'],
        'in_transit_to_station' => ['arrived_at_station', 'lost'],
        'arrived_at_station' => ['assigned_to_rider', 'in_transit_to_province', 'returning_to_sender', 'returned_to_sender', 'on_hold'],
        'assigned_to_rider' => ['out_for_delivery', 'arrived_at_station'],
        'out_for_delivery' => ['delivered', 'delivery_failed', 'returned_to_sender'],
        'delivery_failed' => ['assigned_to_rider', 'arrived_at_station', 'returning_to_sender'],
        'on_hold' => ['at_origin_hub', 'at_province_hub', 'arrived_at_station', 'returning_to_sender', 'lost', 'cancelled'],
        'returning_to_sender' => ['in_transit_to_province', 'assigned_to_rider', 'returned_to_sender', 'on_hold'],
        'delivered' => [],
        'returned_to_sender' => [],
        'cancelled' => [],
        'lost' => [],
    ];

    public function canMoveTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value], true);
    }

    public function isTerminal(): bool
    {
        return self::TRANSITIONS[$this->value] === [];
    }

    /** The parcel is sitting inside a hub (can be scanned out, sorted or dispatched). */
    public function isAtHub(): bool
    {
        return in_array($this, [self::AtOriginHub, self::AtProvinceHub, self::ArrivedAtStation, self::DeliveryFailed, self::OnHold, self::ReturningToSender], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::WaitingPickup => 'Waiting for pickup',
            self::PickedUp => 'Picked up',
            self::AtOriginHub => 'At origin hub',
            self::InTransitToProvince => 'In transit to province hub',
            self::AtProvinceHub => 'At province hub',
            self::InTransitToStation => 'In transit to station',
            self::ArrivedAtStation => 'Arrived at station',
            self::AssignedToRider => 'Assigned to rider',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery failed',
            self::OnHold => 'On hold',
            self::ReturningToSender => 'Returning to sender',
            self::ReturnedToSender => 'Returned to sender',
            self::Cancelled => 'Cancelled',
            self::Lost => 'Lost',
        };
    }
}
