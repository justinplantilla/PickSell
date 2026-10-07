@props(['status'])

@php
    [$label, $badgeClass] = match ($status) {
        'requested' => ['Requested', 'badge-pending'],
        'awaiting_item' => ['Approved · Awaiting Parcel', 'badge-processing'],
        'received' => ['Received', 'badge-shipped'],
        'inspected' => ['Inspected', 'badge-shipped'],
        'approved_for_refund' => ['Refund requested', 'badge-pending'],
        'refund_due' => ['Refund Ready · Refund due', 'badge-pending'],
        'completed' => ['Closed', 'badge-approved'],
        'rejected' => ['Rejected', 'badge-cancelled'],
        default => [ucfirst(str_replace('_', ' ', $status)), 'badge-deactivated'],
    };
@endphp

<span {{ $attributes->class(['badge', $badgeClass]) }}>{{ $label }}</span>