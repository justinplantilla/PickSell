@props(['status'])

@php
    $label = match ($status) {
        'at_sorting_center' => 'At sorting center',
        'assigned_to_rider' => 'Assigned to rider',
        'delivery_failed' => 'Delivery failed',
        'out_for_delivery' => 'Out for delivery',
        'ready_for_pickup' => 'Ready for pickup',
        'refunded' => 'Refunded',
        default => ucfirst(str_replace('_', ' ', $status)),
    };

    $tone = match ($status) {
        'pending', 'placed', 'confirmed', 'preparing', 'processing', 'ready_for_pickup' => 'processing',
        'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'shipped' => 'shipped',
        'delivered', 'completed' => 'completed',
        'cancelled', 'delivery_failed', 'returned', 'refunded' => 'cancelled',
        default => 'neutral',
    };
@endphp

<span {{ $attributes->class(['badge', 'seller-order-status', 'seller-order-status--' . $tone]) }}>{{ $label }}</span>