@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css'])
@endsection

@section('scripts')
@vite('resources/js/components/admin-row-links.js')
@endsection
@section('title', 'Logistics Registrations')

@section('content')
@php
    $tabQuery = fn (string $key) => array_filter(['status' => $key === 'pending' ? null : $key, 'search' => $search ?: null]);
@endphp
<p class="review-back"><a href="{{ route('admin.logistics.index') }}" class="btn btn-outline btn-sm">← Back to logistics</a></p>

<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Logistics registrations</span>
            <p class="oversight-description">{{ $status === 'pending' ? 'Oldest registrations first. Open a company to check its documents and decide.' : 'Companies that registered on the logistics portal.' }}</p>
        </div>
    </div>

    <nav class="oversight-tabs" aria-label="Company status">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('admin.logistics.companies', $tabQuery($key)) }}" class="oversight-tab {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                {{ $label }} <span class="oversight-tab-count">{{ number_format($statusCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.logistics.companies') }}" class="list-toolbar" role="search" aria-label="Search companies">
        @if($status !== 'pending')<input type="hidden" name="status" value="{{ $status }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search companies</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Company, owner or email…">
        </label>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($companies->isEmpty())
        <div class="oversight-empty"><strong>No companies found</strong>{{ $status === 'pending' && $search === '' ? 'No registration is waiting for review.' : 'Try another tab or search.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table">
                <thead>
                    <tr><th>Company</th><th>Owner</th><th>{{ $status === 'pending' ? 'Waiting' : 'Submitted' }}</th><th>Status</th><th class="list-chevron"><span class="sr-only">Open</span></th></tr>
                </thead>
                <tbody>
                @foreach($companies as $company)
                    <tr class="list-row" data-row-href="{{ route('admin.logistics.companies.show', $company) }}">
                        <td class="list-cell-main" data-label="Company">
                            <a href="{{ route('admin.logistics.companies.show', $company) }}" class="list-title" data-row-link>{{ $company->name }}</a>
                            <span class="oversight-meta">{{ $company->tracking_prefix ? 'Tracking prefix '.$company->tracking_prefix : 'No tracking prefix yet' }}@if($company->is_default) · Default carrier @endif</span>
                        </td>
                        <td data-label="Owner">
                            {{ $company->owner?->full_name ?? '—' }}
                            <span class="oversight-meta">{{ $company->owner?->email }}</span>
                        </td>
                        <td data-label="Submitted">
                            @if($company->submitted_at)
                                <time datetime="{{ $company->submitted_at->toIso8601String() }}">{{ $company->submitted_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) }}</time>
                                <span class="oversight-meta">{{ $company->submitted_at->format('M d, Y') }}</span>
                            @else
                                <span class="oversight-meta">Added by PickSell</span>
                            @endif
                        </td>
                        <td class="list-status" data-label="Status"><span class="badge badge-{{ $company->status === 'rejected' ? 'disapproved' : $company->status }}">{{ ucfirst($company->status) }}</span></td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($companies->hasPages())<div class="dashboard-pagination">{{ $companies->links() }}</div>@endif
    @endif
</div>
@endsection

@section('scripts')
@vite('resources/js/components/admin-row-links.js')
@endsection
