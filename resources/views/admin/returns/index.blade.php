@extends('admin.layout')
@section('title', 'Complaints & Disputes')
@section('styles')
@vite(['resources/css/views/admin-complaints.css', 'resources/css/views/admin-oversight.css'])
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Return disputes</span>
            <p class="return-page-description">Review seller-rejected requests and record a resolution.</p>
        </div>
        <form method="GET" class="filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter return disputes">
                <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Open disputes</option>
                <option value="resolved" {{ $status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All disputes</option>
            </select>
        </form>
    </div>
    @include('admin.returns._care_tabs')
    @if($returnRequests->isEmpty())
        <div class="return-empty-state"><strong>No return disputes</strong><p>Seller rejections requiring Admin review will appear here.</p></div>
    @else
        <div class="return-request-list">
            @foreach($returnRequests as $returnRequest)
                <a href="{{ route('admin.returns.show', $returnRequest) }}" class="return-request-list-row">
                    <div>
                        <strong>{{ $returnRequest->order->order_number }}</strong>
                        <span>{{ $returnRequest->buyer->full_name }} · {{ $returnRequest->seller->business_name ?? $returnRequest->seller->full_name }}</span>
                    </div>
                    <div class="return-request-list-meta">
                        <span class="badge {{ $returnRequest->dispute_status === 'open' ? 'badge-pending' : 'badge-approved' }}">{{ ucfirst($returnRequest->dispute_status) }}</span>
                        <time datetime="{{ $returnRequest->created_at->toDateString() }}">{{ $returnRequest->created_at->format('M d, Y') }}</time>
                    </div>
                </a>
            @endforeach
        </div>
        @if($returnRequests->hasPages())
            <div class="dashboard-pagination">{{ $returnRequests->withQueryString()->links() }}</div>
        @endif
    @endif
</div>
@endsection