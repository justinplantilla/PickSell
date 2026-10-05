@extends('seller.layout')
@section('styles')
@vite(['resources/css/views/seller-dashboard.css', 'resources/css/views/seller-earnings.css'])
@endsection
@section('title', 'Earnings')

@section('content')
<div class="earnings-shell">
    <section class="earnings-intro">
        <div>
            <span class="earnings-eyebrow">Financial visibility</span>
            <h1>Commission &amp; earnings</h1>
            <p>Review completed-order sales, platform commission deductions, and net earnings. This report does not initiate payouts.</p>
        </div>
    </section>

    <section class="earnings-summary" aria-label="Earnings overview">
        <article class="kpi-block earnings-summary-card">
            <div class="kpi-title">Total gross sales</div>
            <strong>₱{{ number_format($totalSales, 2) }}</strong>
            <small>{{ $totalOrders }} completed {{ $totalOrders === 1 ? 'order' : 'orders' }}</small>
        </article>
        <article class="kpi-block earnings-summary-card earnings-summary-card--commission">
            <div class="kpi-title">Total commission deducted</div>
            <strong>-₱{{ number_format($totalCommission, 2) }}</strong>
            <small>{{ number_format($averageCommissionRate, 2) }}% platform rate</small>
        </article>
        <article class="kpi-block earnings-summary-card earnings-summary-card--net">
            <div class="kpi-title">Net earnings</div>
            <strong>₱{{ number_format($totalNetEarnings, 2) }}</strong>
            <small>Gross sales less commission</small>
        </article>
    </section>

    <section class="earnings-panel">
        <div class="earnings-panel-header">
            <div>
                <h2>Commission breakdown</h2>
                <p>Only completed orders are included. Commission uses the current platform rate configured by the administrator.</p>
            </div>
        </div>
        <div class="earnings-filter-row">
            <form method="GET" action="{{ route('seller.earnings') }}" class="earnings-filter-form" data-earnings-filter>
                <label>
                    <span>Preset range</span>
                    <select name="preset" class="form-control">
                        <option value="today" @selected($preset === 'today')>Today</option>
                        <option value="last_7_days" @selected($preset === 'last_7_days')>Last 7 days</option>
                        <option value="last_30_days" @selected($preset === 'last_30_days')>Last 30 days</option>
                        <option value="this_month" @selected($preset === 'this_month')>This month</option>
                        <option value="last_month" @selected($preset === 'last_month')>Last month</option>
                        <option value="custom" @selected($preset === 'custom')>Custom range</option>
                    </select>
                </label>
                <label>
                    <span>From</span>
                    <input type="date" name="from" value="{{ $from }}" class="form-control" @if($preset === 'custom') required @endif>
                </label>
                <label>
                    <span>To</span>
                    <input type="date" name="to" value="{{ $to }}" class="form-control" @if($preset === 'custom') required @endif>
                </label>
                <button type="submit" class="btn btn-coral">Apply filters</button>
            </form>
            <div class="earnings-export-actions">
                <a href="{{ route('seller.earnings.csv', ['preset' => $preset, 'from' => $from, 'to' => $to]) }}" class="btn btn-outline">Export CSV</a>
                <a href="{{ route('seller.earnings.pdf', ['preset' => $preset, 'from' => $from, 'to' => $to]) }}" class="btn btn-outline">Export PDF</a>
            </div>
        </div>

        <div class="earnings-period">Showing {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</div>

        @if($orders->isEmpty())
            <div class="earnings-empty">
                <strong>No completed orders in this period</strong>
                <span>Commission and net earnings will appear here when orders are completed.</span>
            </div>
        @else
            <div class="earnings-table-wrap">
                <table class="earnings-table">
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Transaction date</th>
                            <th scope="col">Sale amount (gross)</th>
                            <th scope="col">Commission deducted</th>
                            <th scope="col">Net earnings</th>
                            <th scope="col">Order status</th>
                            <th scope="col"><span class="earnings-visually-hidden">Commission details</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td><a href="{{ route('seller.orders.show', $order['id']) }}">#{{ $order['order_number'] }}</a></td>
                            <td><time datetime="{{ $order['created_at']->toDateString() }}">{{ $order['created_at']->format('M d, Y') }}</time></td>
                            <td>₱{{ number_format($order['amount'], 2) }}</td>
                            <td class="earnings-amount--deduction">-₱{{ number_format($order['commission'], 2) }} ({{ number_format($order['commission_rate'], 2) }}%)</td>
                            <td class="earnings-amount--net">₱{{ number_format($order['net_earnings'], 2) }}</td>
                            <td><x-order-status-badge :status="$order['status']" /></td>
                            <td><button type="button" class="earnings-disclosure-trigger" aria-expanded="false" aria-controls="earnings-details-{{ $order['id'] }}" aria-label="Why was I charged this? Order #{{ $order['order_number'] }}" data-earnings-disclosure>Why charged?</button></td>
                        </tr>
                        <tr id="earnings-details-{{ $order['id'] }}" class="earnings-details-row" hidden>
                            <td colspan="7">
                                <dl class="earnings-disclosure-card">
                                    <div><dt>Order gross sale amount</dt><dd>₱{{ number_format($order['amount'], 2) }}</dd></div>
                                    <div><dt>Platform commission ({{ number_format($order['commission_rate'], 2) }}%)</dt><dd>-₱{{ number_format($order['commission'], 2) }}</dd></div>
                                    <div class="earnings-order-net"><dt>Net calculated earnings</dt><dd>₱{{ number_format($order['net_earnings'], 2) }}</dd></div>
                                </dl>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-earnings.js')
@endsection
