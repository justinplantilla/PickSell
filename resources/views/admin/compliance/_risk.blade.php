{{-- Risk indicator: label + icon, never colour alone. $risk = ComplianceService::risk(...) --}}
@php
    $labels = ['none' => 'No flags', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
    $tooltip = collect($risk['factors'])->map(fn ($value, $factor) => "{$factor}: {$value}")->join('; ') ?: 'No compliance flags';
@endphp
<span class="risk-badge risk-{{ $risk['level'] }}" title="{{ $tooltip }}">
    @if(in_array($risk['level'], ['medium', 'high'], true))<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>@endif
    {{ $labels[$risk['level']] }}<span class="sr-only"> risk. {{ $tooltip }}</span>
</span>
