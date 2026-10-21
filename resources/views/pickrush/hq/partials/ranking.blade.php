{{-- Station ranking with a success-rate bar against the SLA target. Expects $ranking and $sla. --}}
<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>#</th><th>Station</th><th>Province</th><th class="pr-num">Delivered</th><th class="pr-num">Returned</th><th class="pr-num">Failed attempts</th><th>Success rate (target {{ $sla }}%)</th></tr></thead>
        <tbody>
        @forelse($ranking as $row)
            @php $rate = $row->success_rate; @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><a href="{{ route('pickrush.hubs.show', $row->hub) }}">{{ $row->hub->name }}</a></td>
                <td>{{ $row->hub->province_name }}</td>
                <td class="pr-num">{{ number_format($row->delivered) }}</td>
                <td class="pr-num">{{ number_format($row->returned) }}</td>
                <td class="pr-num">{{ number_format($row->failed) }}</td>
                <td>
                    @if($rate === null)<span class="pr-muted">No finished parcels</span>
                    @else
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <div @class(['pr-bar', 'is-below' => $rate < $sla && $rate >= $sla - 10, 'is-far-below' => $rate < $sla - 10]) role="img" aria-label="{{ $rate }}% against a {{ $sla }}% target">
                                <span style="width: {{ $rate }}%"></span><i class="pr-bar-target" style="left: {{ $sla }}%"></i>
                            </div>
                            <strong>{{ $rate }}%</strong>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="pr-muted">No deliveries finished in this period yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
