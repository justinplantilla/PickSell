{{-- Outbound trips still being loaded at this hub: keep loading or cancel. Expects $drafts (with toHub) and $prefix. --}}
<section class="pr-section">
    <h2>Being loaded ({{ $drafts->count() }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Trip</th><th>Going to</th><th class="pr-num">Parcels</th><th>Started</th><th></th></tr></thead>
            <tbody>
            @forelse($drafts as $draft)
                <tr>
                    <td><a href="{{ route($prefix.'.show', $draft) }}">{{ $draft->manifest_no }}</a></td>
                    <td>{{ $draft->toHub->name }}</td>
                    <td class="pr-num">{{ $draft->parcel_count }}</td>
                    <td>{{ $draft->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="pr-inline">
                            <a href="{{ route($prefix.'.show', $draft) }}" class="pr-btn pr-btn-primary pr-btn-sm">Continue loading</a>
                            <form method="POST" action="{{ route($prefix.'.cancel', $draft) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="pr-btn pr-btn-danger pr-btn-sm" data-confirm="Cancel trip {{ $draft->manifest_no }}? Its {{ $draft->parcel_count }} parcels go back to ready to leave.">Cancel</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No trip is being loaded. Start one when parcels are ready to leave.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
