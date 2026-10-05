@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-compliance.css'])
@endsection
@section('title', 'Seller Compliance')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Seller compliance</span>
            <p class="oversight-description">Highest risk first. Risk is an indicator built from open cases, recent warnings, off-category listings, Admin-archived products and open complaints. Hover a badge for its breakdown.</p>
        </div>
    </div>
    @include('admin.compliance._tabs')
    <form method="GET" class="oversight-filters oversight-note" role="search" aria-label="Filter sellers">
        <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Business, name or email" aria-label="Search sellers">
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            @foreach(['all' => 'Active and suspended', 'approved' => 'Active', 'suspended' => 'Suspended'] as $value => $label)
                <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="flag" class="filter-select" aria-label="Flag" data-submit-on-change>
            @foreach(['all' => 'All sellers', 'open_case' => 'With open cases', 'mismatch' => 'Listings outside registered category', 'warned' => 'Warned in last 90 days'] as $value => $label)
                <option value="{{ $value }}" {{ $flag === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
    </form>

    @if($sellers->isEmpty())
        <div class="oversight-empty"><strong>No sellers match</strong>Try different filters.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Seller</th><th>Registered category</th><th>Off-category listings</th><th>Open cases</th><th>Warnings (90d)</th><th>Status</th><th>Risk</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($sellers as $seller)
                    <tr>
                        <td><strong>{{ $seller->business_name ?? $seller->full_name }}</strong><span class="oversight-meta">{{ $seller->full_name }} · {{ $seller->email }}</span></td>
                        <td>{{ $seller->line_of_business ?? '—' }}</td>
                        <td class="oversight-number">{{ $seller->mismatched_products_count }}</td>
                        <td class="oversight-number">{{ $seller->open_cases_count }}</td>
                        <td class="oversight-number">{{ $seller->recent_warnings_count }}</td>
                        <td><span class="badge badge-{{ $seller->status }}">{{ $seller->status === 'approved' ? 'Active' : ucfirst($seller->status) }}</span></td>
                        <td>@include('admin.compliance._risk', ['risk' => \App\Http\Controllers\AdminComplianceController::riskFor($seller)])</td>
                        <td><a href="{{ route('admin.compliance.seller', $seller) }}" class="btn btn-outline btn-sm">Review<span class="sr-only"> {{ $seller->business_name ?? $seller->full_name }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($sellers->hasPages())<div class="dashboard-pagination">{{ $sellers->links() }}</div>@endif
    @endif
</div>
@endsection
