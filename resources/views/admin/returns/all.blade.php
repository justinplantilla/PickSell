@extends('admin.layout')
@section('title', 'Returns')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Return requests</span>
            <p class="oversight-description">Every return across the marketplace. Admin decides only escalated disputes.</p>
        </div>
        <form method="GET" class="oversight-filters" role="search">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Order number" aria-label="Search returns by order number">
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
        </form>
    </div>
    <nav class="oversight-tabs" aria-label="Return status">
        <a href="{{ route('admin.returns', array_filter(['search' => $search])) }}" class="oversight-tab {{ $status === 'all' ? 'is-active' : '' }}" @if($status === 'all') aria-current="page" @endif>All <span class="oversight-tab-count">{{ number_format($statusCounts->sum()) }}</span></a>
        @foreach(\App\Models\ReturnRequest::STATUSES as $option)
            <a href="{{ route('admin.returns', array_filter(['status' => $option, 'search' => $search])) }}" class="oversight-tab {{ $status === $option ? 'is-active' : '' }}" @if($status === $option) aria-current="page" @endif>{{ ucfirst(str_replace('_', ' ', $option)) }} <span class="oversight-tab-count">{{ number_format($statusCounts[$option] ?? 0) }}</span></a>
        @endforeach
    </nav>

    @if($returnRequests->isEmpty())
        <div class="oversight-empty"><strong>No return requests</strong>Try another status or search.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Order</th><th>Buyer</th><th>Seller</th><th>Reason</th><th>Status</th><th>Dispute</th><th>Requested</th></tr></thead>
                <tbody>
                @foreach($returnRequests as $returnRequest)
                    <tr>
                        <td><a href="{{ route('admin.returns.show', $returnRequest) }}"><strong>{{ $returnRequest->order?->order_number ?? '#' . $returnRequest->order_id }}</strong></a><span class="oversight-meta">{{ $returnRequest->order?->product_name }}</span></td>
                        <td>{{ $returnRequest->buyer?->full_name ?? '—' }}</td>
                        <td>{{ $returnRequest->seller?->business_name ?? $returnRequest->seller?->full_name ?? '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $returnRequest->reason)) }}</td>
                        <td><x-return-request-status-badge :status="$returnRequest->status" /></td>
                        <td>
                            @if($returnRequest->dispute_status === 'not_open')
                                <span class="oversight-meta">—</span>
                            @else
                                <span class="badge {{ $returnRequest->dispute_status === 'open' ? 'badge-pending' : 'badge-approved' }}">{{ ucfirst($returnRequest->dispute_status) }}</span>
                            @endif
                        </td>
                        <td><time datetime="{{ $returnRequest->created_at->toIso8601String() }}">{{ $returnRequest->created_at->format('M d, Y') }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($returnRequests->hasPages())<div class="dashboard-pagination">{{ $returnRequests->links() }}</div>@endif
    @endif
</div>
@endsection
