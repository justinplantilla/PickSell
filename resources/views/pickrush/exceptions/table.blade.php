<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Type</th><th>Parcel</th><th>Details</th><th>{{ $showAgainst ? 'Against' : 'Found at' }}</th><th>{{ $resolvable ? 'Resolve' : 'Outcome' }}</th></tr></thead>
        <tbody>
        @forelse($rows as $ex)
            <tr>
                <td><span class="pr-badge pr-badge-warn">{{ $ex->label() }}</span><div class="pr-muted">{{ $ex->created_at->diffForHumans() }}</div></td>
                <td>@if($ex->parcel)<a href="{{ route('pickrush.parcels.show', $ex->parcel) }}">{{ $ex->parcel->tracking_no }}</a><div class="pr-muted">{{ $ex->parcel->current_status->label() }}</div>@else — @endif</td>
                <td>{{ $ex->note }}@if($ex->manifest)<div class="pr-muted">Trip {{ $ex->manifest->manifest_no }}</div>@endif</td>
                <td>{{ $showAgainst ? ($ex->againstHub?->name ?? '—') : $ex->hub?->name }}</td>
                <td>
                    @if($resolvable)
                        <form method="POST" action="{{ route('pickrush.exceptions.resolve', $ex) }}" class="pr-inline">
                            @csrf
                            <select name="action" class="pr-select" aria-label="Resolution">
                                @foreach($actions[$ex->type] ?? [] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            @if($ex->type === 'unlabeled')<input name="tracking_no" class="pr-input" placeholder="Tracking no. (if identified)" aria-label="Tracking number" style="width:150px">@endif
                            <input name="note" class="pr-input" placeholder="Note" required aria-label="Note" style="width:160px">
                            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Apply</button>
                        </form>
                    @elseif($ex->status === 'resolved')
                        {{ str_replace('_', ' ', $ex->resolution) }}<div class="pr-muted">{{ $ex->resolution_note }}</div>
                    @else
                        <span class="pr-muted">Open at {{ $ex->hub?->name }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
