@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-logistics-issues.css'])
@endsection
@section('title', 'Off-Platform Monitoring')

@section('content')
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Off-platform monitoring</span>
            <p class="oversight-description">Signs that a sale is being taken outside PickSell: contact details or outside couriers in chat (masked before the other side sees them) and listings (held until you approve), orders cancelled right after such a chat, waybills never handed to the carrier, high cancellation rates, and buyer reports. Nothing is penalised automatically: you decide.</p>
        </div>
    </div>

    <nav class="oversight-tabs" aria-label="View">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.off-platform', $key === 'queue' ? [] : ['tab' => $key]) }}" class="oversight-tab {{ $tab === $key ? 'is-active' : '' }}" @if($tab === $key) aria-current="page" @endif>
                {{ $label }}@if($key === 'queue') <span class="oversight-tab-count">{{ number_format($openCount) }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if($tab === 'sellers')
        @if($sellers->isEmpty())
            <div class="oversight-empty"><strong>No seller has signals</strong>In the last {{ \App\Models\OffPlatformFlag::RISK_DAYS }} days.</div>
        @else
            <div class="oversight-table-wrap">
                <table class="list-table">
                    <thead><tr><th>Seller</th><th>Risk score</th><th>Signals ({{ \App\Models\OffPlatformFlag::RISK_DAYS }}d)</th><th>Open</th><th>Latest</th><th class="list-chevron"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach($sellers as $row)
                        <tr class="list-row" data-row-href="{{ route('admin.off-platform.seller', $row->seller_id) }}">
                            <td class="list-cell-main" data-label="Seller"><a href="{{ route('admin.off-platform.seller', $row->seller_id) }}" class="list-title" data-row-link>{{ $row->seller?->business_name ?: $row->seller?->full_name }}</a><span class="oversight-meta">{{ $row->seller?->email }}</span></td>
                            <td data-label="Risk score"><span class="badge badge-{{ $row->score >= 12 ? 'disapproved' : ($row->score >= 5 ? 'pending' : 'approved') }}">{{ $row->score }}</span></td>
                            <td data-label="Signals">{{ $row->flags }}</td>
                            <td data-label="Open">{{ $row->open_flags }}</td>
                            <td data-label="Latest">{{ \Illuminate\Support\Carbon::parse($row->last_flag)->diffForHumans() }}</td>
                            <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <form method="GET" action="{{ route('admin.off-platform') }}" class="list-toolbar issues-toolbar" aria-label="Filter">
            @if($tab !== 'queue')<input type="hidden" name="tab" value="{{ $tab }}">@endif
            <select name="kind" class="form-control" aria-label="Where" onchange="this.form.submit()">
                <option value="">Everywhere</option>
                @foreach(\App\Models\OffPlatformFlag::KINDS as $key => $label)<option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>@endforeach
            </select>
            <button type="submit" class="sr-only">Filter</button>
        </form>
        @if($flags->isEmpty())
            <div class="oversight-empty"><strong>Nothing here</strong>{{ $tab === 'queue' ? 'No signal is waiting for review.' : 'No reviewed signals yet.' }}</div>
        @else
            <div class="oversight-table-wrap">
                <table class="list-table">
                    <thead><tr><th>Signal</th><th>Seller</th><th>Evidence</th><th>When</th>@if($tab === 'reviewed')<th>Outcome</th>@endif<th class="list-chevron"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach($flags as $flag)
                        <tr class="list-row" data-row-href="{{ route('admin.off-platform.seller', $flag->seller_id) }}">
                            <td class="list-cell-main" data-label="Signal">
                                <a href="{{ route('admin.off-platform.seller', $flag->seller_id) }}" class="list-title" data-row-link>{{ $flag->label() }}</a>
                                <span class="oversight-meta">{{ \App\Models\OffPlatformFlag::KINDS[$flag->kind] ?? $flag->kind }} · weight {{ $flag->weight }}</span>
                            </td>
                            <td data-label="Seller">{{ $flag->seller?->business_name ?: $flag->seller?->full_name }}</td>
                            <td data-label="Evidence">{{ \Illuminate\Support\Str::limit($flag->evidence, 140) }}
                                @if($flag->product)<span class="oversight-meta">Listing: {{ $flag->product->name }}</span>@endif
                                @if($flag->order)<span class="oversight-meta">Order {{ $flag->order->order_number }}</span>@endif</td>
                            <td data-label="When"><time datetime="{{ $flag->created_at->toIso8601String() }}">{{ $flag->created_at->diffForHumans() }}</time></td>
                            @if($tab === 'reviewed')<td data-label="Outcome">{{ $flag->status === 'dismissed' ? 'Dismissed' : (\App\Models\OffPlatformFlag::ACTIONS[$flag->action] ?? $flag->action) }}</td>@endif
                            <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($flags->hasPages())<div class="dashboard-pagination">{{ $flags->links() }}</div>@endif
        @endif
    @endif
</div>
@endsection

@section('scripts')
@vite('resources/js/components/admin-row-links.js')
@endsection
