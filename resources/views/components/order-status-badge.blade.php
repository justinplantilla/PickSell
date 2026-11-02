@props(['status'])

@php
    $label = match ($status) {
        'at_sorting_center', 'sorted' => 'At sorting center',
        'in_transit' => 'In transit to province hub',
        'assigned_to_rider' => 'Assigned to rider',
        'delivery_failed' => 'Delivery failed',
        'out_for_delivery' => 'Out for delivery',
        'ready_for_pickup' => 'Ready for pickup',
        'refunded' => 'Refunded',
        default => ucfirst(str_replace('_', ' ', $status)),
    };

    $tone = match ($status) {
        'pending', 'placed', 'confirmed', 'preparing', 'processing', 'ready_for_pickup' => 'processing',
        'picked_up', 'at_sorting_center', 'sorted', 'in_transit', 'assigned_to_rider', 'out_for_delivery', 'shipped' => 'shipped',
        'delivered', 'completed' => 'completed',
        'cancelled', 'delivery_failed', 'returned', 'refunded' => 'cancelled',
        default => 'neutral',
    };
@endphp

{{-- badge-{tone} lets the buyer pages (which style badge-processing/shipped/completed/cancelled) reuse it. --}}
<span {{ $attributes->class(['badge', 'badge-' . $tone, 'seller-order-status', 'seller-order-status--' . $tone]) }}>{{ $label }}</span>