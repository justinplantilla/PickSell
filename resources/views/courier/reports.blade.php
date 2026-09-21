@extends('courier.layout')
@vite('resources/css/views/courier-reports.css')
@section('title', 'Reports')
@section('content')
<h1 class="blade-inline-1">Delivery Reports</h1>
<div class="card"><div class="card-body"><form method="GET" class="blade-inline-2"><div class="form-group blade-inline-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div><div class="form-group blade-inline-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div><button class="btn btn-coral" type="submit">Generate</button></form></div></div>
<div class="stats"><div class="stat"><strong>{{ $totalOrders }}</strong><span>Total Transactions</span></div><div class="stat"><strong>{{ $deliveredOrders }}</strong><span>Delivered Parcels</span></div><div class="stat"><strong>₱{{ number_format($earnings, 2) }}</strong><span>Commission</span></div></div>
<div class="card"><div class="card-header"><span class="card-title">Transactions</span></div><div class="blade-inline-5"><table><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->buyer->full_name ?? '—' }}</td><td>₱{{ number_format($order->amount, 2) }}</td><td><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></td><td>{{ $order->created_at->format('M d, Y') }}</td></tr>@empty<tr><td colspan="5" class="blade-inline-6">No transactions found.</td></tr>@endforelse</tbody></table></div></div>
@endsection
