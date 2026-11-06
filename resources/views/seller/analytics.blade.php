@extends('seller.layout')
@section('styles')
@vite(['resources/css/views/seller-dashboard.css', 'resources/css/views/seller-earnings.css'])
@endsection
@section('title', 'Product Performance')

@section('content')
<div class="earnings-shell">
    <section class="earnings-intro">
        <div>
            <span class="earnings-eyebrow">Analytics</span>
            <h1>Product performance</h1>
            <p>Views and add-to-carts from buyers on the website and app, and sales from orders that were not cancelled.</p>
        </div>
        <nav class="analytics-ranges" aria-label="Date range">
            @foreach(\App\Http\Controllers\SellerController::ANALYTICS_RANGES as $value => $label)
            <a href="{{ route('seller.analytics', ['days' => $value]) }}" class="btn btn-sm {{ $days === $value ? 'btn-coral' : 'btn-outline' }}" @if($days === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section class="earnings-summary analytics-summary" aria-label="Totals">
        <article class="kpi-block earnings-summary-card"><div class="kpi-title">Views</div><strong>{{ number_format($totals['views']) }}</strong></article>
        <article class="kpi-block earnings-summary-card"><div class="kpi-title">Added to cart</div><strong>{{ number_format($totals['cart_adds']) }}</strong></article>
        <article class="kpi-block earnings-summary-card"><div class="kpi-title">Units sold</div><strong>{{ number_format($totals['units']) }}</strong></article>
        <article class="kpi-block earnings-summary-card"><div class="kpi-title">Sales</div><strong>₱{{ number_format($totals['revenue'], 2) }}</strong></article>
    </section>

    <section class="earnings-panel analytics-panel">
        @if($rows->isEmpty())
        <p class="review-muted">No products yet.</p>
        @else
        <div class="analytics-table-wrap">
            <table class="analytics-table">
                <thead><tr><th>Product</th><th>Views</th><th>Added to cart</th><th>Orders</th><th>Units sold</th><th>Sales</th><th title="Orders per 100 views">View to order</th></tr></thead>
                <tbody>
                @foreach($rows as $row)
                <tr>
                    <td><strong>{{ $row['product']->name }}</strong>@if($row['product']->status !== 'active') <span class="review-muted">· {{ $row['product']->status }}</span>@endif</td>
                    <td>{{ number_format($row['views']) }}</td>
                    <td>{{ number_format($row['cart_adds']) }}</td>
                    <td>{{ number_format($row['orders']) }}</td>
                    <td>{{ number_format($row['units']) }}</td>
                    <td>₱{{ number_format($row['revenue'], 2) }}</td>
                    <td>{{ $row['conversion'] === null ? '—' : $row['conversion'].'%' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>
</div>
@endsection
