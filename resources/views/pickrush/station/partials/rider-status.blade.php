@php
    [$class, $label] = match ($status) {
        'active' => ['pr-badge-ok', 'Active'],
        'pending' => ['pr-badge-warn', 'Pending verification'],
        'under_review' => ['pr-badge-brand', 'Verified, to approve'],
        'suspended' => ['pr-badge-bad', 'Suspended'],
        'rejected' => ['pr-badge-bad', 'Rejected'],
        default => ['', ucfirst($status)],
    };
@endphp
<span class="pr-badge {{ $class }}">{{ $label }}</span>
