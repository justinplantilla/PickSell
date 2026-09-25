@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-logistics.css')
@endsection
@section('title', 'Sorting Center / Logistics')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Sorting Center / Logistics</span>
            <div class="blade-inline-1">Receive, scan, sort, and assign prepared parcels to riders.</div>
        </div>
        <form method="GET">
            <select name="status" class="filter-select" data-submit-on-change>
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Parcels</option>
                <option value="shipped" {{ $status === 'shipped' ? 'selected' : '' }}>At Sorting Center / In Transit</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Delivered</option>
            </select>
        </form>
    </div>
    <div class="blade-inline-2">
        <strong>Process:</strong> Receive parcel → Scan waybill → Read address → Determine area → Sort parcel → Identify rider → Assign parcel.
    </div>
    <div class="blade-inline-3">
        <table>
            <thead>
                <tr><th>Parcel / Waybill</th><th>Delivery Address / Area</th><th>Seller</th><th>Status</th><th>Assigned Rider</th><th>Action</th></tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $area = $order->buyer ? trim(collect([$order->buyer->municipality, $order->buyer->province])->filter()->join(', ')) : 'Unspecified area';
                @endphp
                <tr>
                    <td>
                        <strong>{{ $order->order_number }}</strong>
                        <div class="blade-inline-4">{{ $order->waybill_number ?? 'Awaiting scan / waybill' }}</div>
                    </td>
                    <td>
                        <div>{{ $area }}</div>
                        <div class="blade-inline-5">{{ $order->buyer->barangay ?? 'Address unavailable' }}</div>
                    </td>
                    <td>{{ $order->seller->business_name ?? $order->seller->full_name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                        <div class="blade-inline-6">{{ $order->tracking_status ?? 'Received from seller' }}</div>
                    </td>
                    <td>
                        @if($order->courier)
                            <strong>{{ $order->courier->full_name }}</strong>
                            <div class="blade-inline-7">{{ $order->courier->delivery_area ?? 'Area not set' }}</div>
                        @else
                            <span class="blade-inline-8">Unassigned</span>
                        @endif
                    </td>
                    <td>
                        @if($order->status !== 'completed')
                        <div class="blade-inline-9">
                        @if($order->tracking_status !== 'Received and scanned at sorting center' && !$order->courier_id)
                        <form method="POST" action="{{ route('admin.logistics.scan', $order) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline btn-sm" type="submit">Mark Scanned</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('admin.logistics.assign', $order) }}" class="blade-inline-10">
                            @csrf @method('PATCH')
                            <select name="courier_id" class="filter-select" required>
                                <option value="">Assign rider</option>
                                @foreach($couriers as $courier)
                                <option value="{{ $courier->id }}" {{ $order->courier_id === $courier->id ? 'selected' : '' }}>
                                    {{ $courier->full_name }} — {{ $courier->delivery_area ?? 'Area not set' }}
                                </option>
                                @endforeach
                            </select>
                            <button class="btn btn-coral btn-sm" type="submit">Assign</button>
                        </form>
                            </div>
                        @else
                            <span class="blade-inline-11">Completed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="blade-inline-12">No parcels found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="dashboard-pagination">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Approved Rider Areas</span></div>
    <div class="card-body">
        @forelse($couriers->groupBy(fn($courier) => $courier->delivery_area ?: 'Area not set') as $area => $areaCouriers)
            <div class="blade-inline-13">
                <strong>{{ $area }}</strong>
                <span class="blade-inline-14">{{ $areaCouriers->pluck('full_name')->join(', ') }}</span>
            </div>
        @empty
            <span class="blade-inline-15">No approved riders yet.</span>
        @endforelse
    </div>
</div>
@endsection
