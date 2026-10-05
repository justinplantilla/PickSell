@extends('seller.layout')
@section('title', 'Return Requests')
@section('styles')
@vite('resources/css/views/seller-orders.css')
@endsection

@section('content')
<div class="card return-page-card">
    <div class="card-header">
        <div>
            <span class="card-title">Return & refund requests</span>
            <p class="return-page-description">Review buyer requests and track each return through refund completion.</p>
        </div>
        <form method="GET" class="filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter return requests by status">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All requests</option>
                @foreach(\App\Models\ReturnRequest::STATUSES as $returnStatus)
                    <option value="{{ $returnStatus }}" {{ $status === $returnStatus ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $returnStatus)) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(!$returnTableReady)
        <div class="alert alert-warning" role="status">Return/refund requests are temporarily unavailable while the system update is being completed. Please try again later.</div>
    @elseif($returnRequests->isEmpty())
        <div class="return-empty-state">
            <strong>No return requests</strong>
            <p>Buyer-submitted requests will appear here for review.</p>
        </div>
    @else
        <div class="return-request-list">
            @foreach($returnRequests as $returnRequest)
                <a href="{{ route('seller.returns.show', $returnRequest) }}" class="return-request-list-row">
                    <div>
                        <strong>{{ $returnRequest->order->order_number }}</strong>
                        <span>{{ $returnRequest->order->product_name }} · {{ $returnRequest->buyer->full_name }}</span>
                    </div>
                    <div class="return-request-list-meta">
                        <x-return-request-status-badge :status="$returnRequest->status" />
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