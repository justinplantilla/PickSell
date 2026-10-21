@php
    [$class, $label] = match ($status) {
        'active' => ['pr-badge-ok', 'Active'],
        'pending', 'under_review' => ['pr-badge-warn', 'Application'],
        'suspended' => ['pr-badge-bad', 'Suspended'],
        'rejected' => ['pr-badge-bad', 'Rejected'],
        default => ['', ucfirst($status)],
    };
@endphp
<span class="pr-badge {{ $class }}">{{ $label }}</span>
