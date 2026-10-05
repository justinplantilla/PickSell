@extends('admin.layout')
@section('title', 'Logistics')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Logistics pipeline</span></div>
    @include('admin.logistics._tabs')
    <div class="oversight-pipeline">
        @foreach($pipeline as $status => $count)
            @php $link = in_array($status, \App\Http\Controllers\AdminLogisticsController::SORTING_STATUSES, true) ? route('admin.logistics.sorting', ['status' => $status]) : route('admin.logistics.riders', ['status' => $status]); @endphp
            <a href="{{ $link }}" class="oversight-stage {{ $status === 'delivery_failed' && $count > 0 ? 'is-alert' : '' }}">
                <strong>{{ number_format($count) }}</strong>
                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="stat-grid">
    <a href="{{ route('admin.logistics.sorting', ['stale' => 1]) }}" class="stat-card">
        <div class="stat-card-num">{{ number_format($staleCount) }}</div>
        <div class="stat-card-label">Parcels idle in sorting over {{ \App\Http\Controllers\AdminLogisticsController::STALE_AFTER_HOURS }}h</div>
    </a>
    <a href="{{ route('admin.logistics.sorting', ['status' => 'sorted']) }}" class="stat-card">
        <div class="stat-card-num">{{ number_format($awaitingRider) }}</div>
        <div class="stat-card-label">Sorted parcels awaiting a rider</div>
    </a>
    <div class="stat-card">
        <div class="stat-card-num">{{ number_format($branches->where('status', 'active')->count()) }}</div>
        <div class="stat-card-label">Active branches</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Branches</span></div>
    @if($branches->isEmpty())
        <div class="oversight-empty"><strong>No logistics branches</strong>Branches created in the Logistics portal appear here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Branch</th><th>Coverage</th><th>Operator</th><th>Status</th><th>Active riders</th><th>Outbound parcels</th><th>Inbound parcels</th></tr></thead>
                <tbody>
                @foreach($branches as $branch)
                    <tr>
                        <td><strong>{{ $branch->name }}</strong>@if($branch->address)<span class="oversight-meta">{{ $branch->address }}</span>@endif</td>
                        <td>{{ $branch->municipality?->name }}@if($branch->municipality)<span class="oversight-meta">{{ $branch->municipality->province }}</span>@endif</td>
                        <td>{{ $branch->logistics?->business_name ?? $branch->logistics?->full_name ?? '—' }}</td>
                        <td><span class="badge {{ $branch->status === 'active' ? 'badge-approved' : 'badge-deactivated' }}">{{ ucfirst($branch->status) }}</span></td>
                        <td class="oversight-number">{{ $branch->active_riders_count }}</td>
                        <td class="oversight-number">{{ number_format($outbound[$branch->id] ?? 0) }}</td>
                        <td class="oversight-number">{{ number_format($inbound[$branch->id] ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
