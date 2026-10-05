@props(['status'])

@php
    [$label, $badgeClass] = match ($status) {
        'requested' => ['Requested', 'badge-pending'],
        'awaiting_item' => ['Awaiting item', 'badge-processing'],
        'received' => ['Received', 'badge-shipped'],
        'refund_due' => ['Refund due', 'badge-pending'],
        'completed' => ['Completed', 'badge-approved'],
        'rejected' => ['Rejected', 'badge-cancelled'],
        default => [ucfirst(str_replace('_', ' ', $status)), 'badge-deactivated'],
    };
@endphp

<span {{ $attributes->class(['badge', $badgeClass]) }}>{{ $label }}</span>