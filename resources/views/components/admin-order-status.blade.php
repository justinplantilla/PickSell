@props(['status'])

@php
    $badge = match ($status) {
        'placed', 'confirmed', 'preparing', 'ready_for_pickup' => 'badge-pending',
        'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery' => 'badge-processing',
        'delivered', 'completed' => 'badge-approved',
        'delivery_failed', 'returned', 'cancelled' => 'badge-cancelled',
        default => 'badge-deactivated',
    };
@endphp

<span {{ $attributes->class(['badge', $badge]) }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
