@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-logistics-issues.css'])
@endsection
@section('title', 'Logistics Issues')

@section('content')
@php
    $base = array_filter(['company' => $companyId, 'type' => $type, 'search' => $search ?: null]);
@endphp
<p class="review-back"><a href="{{ route('admin.logistics.index') }}" class="btn btn-outline btn-sm">← Back to logistics</a></p>

<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Logistics issues</span>
            <p class="oversight-description">Problems with the registered logistics companies: exceptions their hubs raise, lost and returned parcels, and what buyers and sellers report. Older than {{ \App\Models\LogisticsIssue::OVERDUE_HOURS }} hours unresolved is overdue.</p>
        </div>
    </div>

    <nav class="oversight-tabs" aria-label="Issue status">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.logistics.issues', $base + ($key === 'open' ? [] : ['tab' => $key])) }}" class="oversight-tab {{ $tab === $key ? 'is-active' : '' }} {{ $key === 'overdue' && $counts[$key] > 0 ? 'is-alert' : '' }}" @if($tab === $key) aria-current="page" @endif>
                {{ $label }} <span class="oversight-tab-count">{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.logistics.issues') }}" class="list-toolbar issues-toolbar" role="search" aria-label="Filter issues">
        @if($tab !== 'open')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search issues</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Order #, tracking # or details…">
        </label>
        <select name="company" class="form-control" aria-label="Company" onchange="this.form.submit()">
            <option value="">All companies</option>
            @foreach($companies as $company)<option value="{{ $company->id }}" @selected($companyId === $company->id)>{{ $company->name }}</option>@endforeach
        </select>
        <select name="type" class="form-control" aria-label="Type" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach(\App\Models\LogisticsIssue::TYPES as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach
        </select>
        <button type="submit" class="sr-only">Filter</button>
    </form>

    @if($issues->isEmpty())
        <div class="oversight-empty"><strong>No issues here</strong>{{ $tab === 'open' ? 'Nothing needs action right now.' : 'Try another tab or filter.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table">
                <thead><tr><th>Issue</th><th>Company</th><th>Order / parcel</th><th>Opened</th><th>Status</th><th class="list-chevron"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                @foreach($issues as $issue)
                    <tr class="list-row" data-row-href="{{ route('admin.logistics.issues.show', $issue) }}">
                        <td class="list-cell-main" data-label="Issue">
                            <a href="{{ route('admin.logistics.issues.show', $issue) }}" class="list-title" data-row-link>#{{ $issue->id }} {{ $issue->label() }}</a>
                            <span class="oversight-meta">{{ ['exception' => 'Raised by the company', 'parcel' => 'From the parcel status', 'report' => 'Reported by the '.$issue->reporter_role][$issue->source] ?? $issue->source }}</span>
                        </td>
                        <td data-label="Company">{{ $issue->company?->name }}</td>
                        <td data-label="Order / parcel">
                            {{ $issue->order?->order_number ?? '—' }}
                            <span class="oversight-meta">{{ $issue->parcel?->tracking_no }}</span>
                        </td>
                        <td data-label="Opened">
                            <time datetime="{{ $issue->created_at->toIso8601String() }}">{{ $issue->created_at->diffForHumans() }}</time>
                            @if($issue->isOverdue())<span class="badge badge-disapproved">Overdue</span>@endif
                        </td>
                        <td class="list-status" data-label="Status">
                            <span class="badge badge-{{ ['open' => 'pending', 'escalated' => 'suspended', 'resolved' => 'approved'][$issue->status] }}">{{ \App\Models\LogisticsIssue::STATUSES[$issue->status] }}</span>
                            @if($issue->claim)<span class="oversight-meta">Claim ₱{{ number_format((float) $issue->claim->amount, 2) }}{{ $issue->claim->status === 'waived' ? ' (waived)' : '' }}</span>@endif
                        </td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($issues->hasPages())<div class="dashboard-pagination">{{ $issues->links() }}</div>@endif
    @endif
</div>
@endsection

@section('scripts')
@vite('resources/js/components/admin-row-links.js')
@endsection
