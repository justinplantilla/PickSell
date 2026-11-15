{{-- What earlier renewals replaced, newest first. --}}
@if($renewals->isNotEmpty())
    <details style="margin-top:.4rem">
        <summary class="pr-muted">History ({{ $renewals->count() }})</summary>
        <ul class="pr-muted" style="margin:.3rem 0 0;padding-left:1rem">
            @foreach($renewals as $renewal)
                @php
                    $old = $renewal->previous;
                    $expiry = $old['registration_expiry'] ?? $old['license_expiry'] ?? null;
                    $number = $renewal->document === 'or_cr' ? 'OR '.($old['or_no'] ?? '—') : 'License '.($old['license_no'] ?? '—');
                @endphp
                <li>{{ $renewal->created_at->format('M d, Y') }} · {{ $renewal->renewer?->full_name ?? 'staff' }} replaced {{ $number }}{{ $expiry ? ', valid until '.\Illuminate\Support\Carbon::parse($expiry)->format('M d, Y') : '' }}</li>
            @endforeach
        </ul>
    </details>
@endif
