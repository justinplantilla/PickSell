@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-compliance.css'])
@endsection
@section('title', 'Compliance Cases')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Compliance cases</span>
            <p class="oversight-description">Most severe first. Open a case from a seller’s compliance page.</p>
        </div>
    </div>
    @include('admin.compliance._tabs')
    <form method="GET" class="oversight-filters oversight-note" aria-label="Filter cases">
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            @foreach(['active' => 'Open and investigating', 'open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All cases'] as $value => $label)
                <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="severity" class="filter-select" aria-label="Severity" data-submit-on-change>
            <option value="all">Any severity</option>
            @foreach(array_keys(\App\Models\ComplianceCase::SEVERITIES) as $value)
                <option value="{{ $value }}" {{ $severity === $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
            @endforeach
        </select>
        <select name="type" class="filter-select" aria-label="Type" data-submit-on-change>
            <option value="all">Any type</option>
            @foreach(\App\Models\ComplianceCase::TYPES as $value => $label)
                <option value="{{ $value }}" {{ $type === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    @if($cases->isEmpty())
        <div class="oversight-empty"><strong>No cases</strong>Nothing matches these filters.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Case</th><th>Seller</th><th>Type</th><th>Severity</th><th>Status</th><th>Opened</th></tr></thead>
                <tbody>
                @foreach($cases as $case)
                    <tr>
                        <td><a href="{{ route('admin.compliance.cases.show', $case) }}"><strong>#{{ $case->id }}</strong></a>@if($case->product)<span class="oversight-meta">{{ $case->product->name }}</span>@endif</td>
                        <td>{{ $case->seller?->business_name ?? $case->seller?->full_name }}</td>
                        <td>{{ $case->type_label }}</td>
                        <td><span class="severity-badge severity-{{ $case->severity }}">{{ ucfirst($case->severity) }}</span></td>
                        <td><span class="badge {{ $case->isOpen() ? 'badge-pending' : 'badge-approved' }}">{{ ucfirst($case->status) }}</span></td>
                        <td>{{ $case->created_at->format('M d, Y') }}<span class="oversight-meta">by {{ $case->opener?->full_name ?? 'Admin' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($cases->hasPages())<div class="dashboard-pagination">{{ $cases->links() }}</div>@endif
    @endif
</div>
@endsection
