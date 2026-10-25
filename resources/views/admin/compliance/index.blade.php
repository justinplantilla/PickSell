@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-compliance.css'])
@endsection
@section('title', 'Seller Compliance')

@section('content')
@php
    $canExport = auth()->user()->can(\App\Auth\Permission::REPORTS_EXPORT);
    $canViewProducts = auth()->user()->can(\App\Auth\Permission::PRODUCTS_VIEW);
    $riskLabels = \App\Http\Controllers\AdminComplianceController::RISK_LABELS;
    $riskQuery = fn (string $level) => array_filter(['risk' => $risk === $level ? null : $level] + request()->except(['risk', 'page']), fn ($v) => $v !== null && $v !== '');
@endphp
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Seller compliance</span>
            <p class="oversight-description">Highest risk first. Risk is an indicator built from open cases, recent warnings, off-category listings, Admin-archived products and open complaints. Click a seller for the full breakdown.</p>
        </div>
        @if($canExport && $matchingCount > 0)
            <a href="{{ route('admin.compliance.export', request()->except('page')) }}" class="btn btn-outline btn-sm list-export">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
                Export {{ number_format($matchingCount) }}
            </a>
        @endif
    </div>

    <nav class="compliance-summary" aria-label="Sellers by risk">
        @foreach($riskSummary as $level => $count)
            <a href="{{ route('admin.compliance', $riskQuery($level)) }}" class="compliance-summary-card is-{{ $level }} {{ $risk === $level ? 'is-active' : '' }}" @if($risk === $level) aria-current="true" @endif>
                <span class="compliance-summary-label">{{ $riskLabels[$level] }}@if($level !== 'none') risk @endif</span>
                <strong>{{ number_format($count) }}</strong>
                <span class="compliance-summary-hint">{{ $risk === $level ? 'Showing · click to clear' : ($count === 1 ? 'seller' : 'sellers') }}</span>
            </a>
        @endforeach
        <a href="{{ route('admin.compliance.cases') }}" class="compliance-summary-card is-cases">
            <span class="compliance-summary-label">Open cases</span>
            <strong>{{ number_format($openCaseCount) }}</strong>
            <span class="compliance-summary-hint">View cases →</span>
        </a>
    </nav>

    @include('admin.compliance._tabs')

    <form method="GET" action="{{ route('admin.compliance') }}" class="list-toolbar" role="search" aria-label="Filter sellers">
        @if($risk !== 'all')<input type="hidden" name="risk" value="{{ $risk }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search sellers</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Business, name or email…">
        </label>
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            <option value="all">Active and suspended</option>
            @foreach(\App\Http\Controllers\AdminComplianceController::STATUS_FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <details class="list-popover">
            <summary class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                More filters @if($moreFiltersCount)<span class="list-count-pill">{{ $moreFiltersCount }}</span>@endif
            </summary>
            <div class="list-popover-panel">
                <label class="list-field">
                    <span>Flag</span>
                    <select name="flag" class="filter-select">
                        <option value="all">All sellers</option>
                        @foreach(\App\Http\Controllers\AdminComplianceController::FLAG_FILTERS as $value => $label)
                            <option value="{{ $value }}" @selected($flag === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="list-popover-actions">
                    <button type="submit" class="btn btn-coral btn-sm">Apply filters</button>
                </div>
            </div>
        </details>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($chips)
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="list-chip">{{ $chip['label'] }} <span aria-hidden="true">✕</span><span class="sr-only">(remove filter)</span></a>
            @endforeach
            <a href="{{ route('admin.compliance') }}" class="list-chips-clear">Clear all</a>
        </div>
    @endif

    @if($sellers->isEmpty())
        <div class="oversight-empty"><strong>No sellers match</strong>Try different filters.</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table compliance-table" data-list-table>
                <thead>
                    <tr>
                        <th>Risk</th>
                        <th>Seller</th>
                        <th class="list-number"><abbr title="Active listings outside the registered category">Off-cat.</abbr></th>
                        <th class="list-number"><abbr title="Open compliance cases">Cases</abbr></th>
                        <th class="list-number"><abbr title="Warnings issued in the last 90 days">Warn 90d</abbr></th>
                        <th>Status</th>
                        <th class="list-chevron"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($sellers as $seller)
                    @php
                        $sellerRisk = \App\Http\Controllers\AdminComplianceController::riskFor($seller);
                        $reasons = array_values(array_filter([
                            $seller->open_cases_count ? trans_choice(':count open case|:count open cases', $seller->open_cases_count) : null,
                            $seller->recent_warnings_count ? trans_choice(':count warning|:count warnings', $seller->recent_warnings_count) : null,
                            $seller->mismatched_products_count ? $seller->mismatched_products_count.' off-category' : null,
                            $seller->held_products_count ? $seller->held_products_count.' archived by Admin' : null,
                            $seller->open_complaints_count ? trans_choice(':count open complaint|:count open complaints', $seller->open_complaints_count) : null,
                        ]));
                        $name = $seller->business_name ?? $seller->full_name;
                    @endphp
                    <tr class="list-row" data-list-row data-preview-url="{{ route('admin.compliance.seller.preview', $seller) }}">
                        <td class="list-status list-cell-aside compliance-risk-cell" data-label="Risk">
                            @include('admin.compliance._risk', ['risk' => $sellerRisk])
                            @if($reasons)<span class="oversight-meta">{{ implode(' · ', array_slice($reasons, 0, 2)) }}@if(count($reasons) > 2) · +{{ count($reasons) - 2 }}@endif</span>@endif
                        </td>
                        <td class="list-cell-main" data-label="Seller">
                            <a href="{{ route('admin.compliance.seller', $seller) }}" class="list-title" data-row-link>{{ $name }}</a>
                            <span class="oversight-meta">{{ $seller->full_name }} · {{ $seller->email }}</span>
                            <span class="oversight-meta compliance-line">{{ $seller->line_of_business ?? 'No registered category' }}</span>
                        </td>
                        @foreach([
                            ['mismatched_products_count', 'Off-category listings'],
                            ['open_cases_count', 'Open cases'],
                            ['recent_warnings_count', 'Warnings (90d)'],
                        ] as [$column, $label])
                            @php $value = (int) $seller->{$column}; @endphp
                            <td class="list-number list-cell-stat compliance-signal {{ $value ? 'is-flagged' : 'is-zero' }}" data-label="{{ $label }}">
                                @if($value && $column === 'mismatched_products_count' && $canViewProducts)
                                    <a href="{{ route('admin.products', ['seller' => $seller->id]) }}" class="list-line" title="View {{ $name }}'s products">{{ number_format($value) }}</a>
                                @elseif($value)
                                    <span class="list-line">{{ number_format($value) }}</span>
                                @else
                                    <span class="list-line" aria-label="0">·</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="list-status list-cell-sub" data-label="Status">
                            <span class="badge badge-{{ $seller->status }}">{{ $seller->status === 'approved' ? 'Active' : ucfirst($seller->status) }}</span>
                        </td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($sellers->hasPages())<div class="dashboard-pagination">{{ $sellers->links() }}</div>@endif
    @endif
</div>

<div class="list-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="list-drawer" data-list-drawer hidden aria-labelledby="list-drawer-title" role="dialog" aria-modal="true">
    <button type="button" class="list-drawer-close" data-drawer-close aria-label="Close seller preview">&times;</button>
    <div data-drawer-body><p class="oversight-empty">Loading…</p></div>
</aside>
@endsection

@section('scripts')
@vite('resources/js/components/admin-list.js')
@endsection
