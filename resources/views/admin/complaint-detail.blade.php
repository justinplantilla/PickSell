@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-complaint-detail.css')
@endsection
@section('title', 'Review Complaint #'.$complaint->id)

@section('content')
<div class="blade-inline-1">
    <a href="{{ route('admin.complaints') }}" class="btn btn-outline btn-sm">← Back to Complaints</a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Complaint Details</span></div>
        <div class="card-body">
            <table class="blade-inline-2">
                <tr><td class="blade-inline-3">Filed By</td><td>{{ $complaint->filer->full_name ?? '—' }} ({{ ucfirst($complaint->filer->role ?? '') }})</td></tr>
                <tr><td class="blade-inline-4">Against</td><td>{{ $complaint->against->full_name ?? 'N/A' }} @if($complaint->against) ({{ ucfirst($complaint->against->role) }}) @endif</td></tr>
                @if($complaint->order)
                    <tr>
                        <td class="blade-inline-5">Order</td>
                        <td>
                            @can(\App\Auth\Permission::ORDERS_VIEW)
                                <a href="{{ route('admin.orders.show', $complaint->order) }}">{{ $complaint->order->order_number }}</a>
                            @else
                                {{ $complaint->order->order_number }}
                            @endcan
                            · {{ $complaint->order->product_name }}
                            · {{ ucfirst(str_replace('_', ' ', $complaint->order->status)) }}
                        </td>
                    </tr>
                    <tr><td class="blade-inline-5">Buyer</td><td>{{ $complaint->order->buyer->full_name ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-5">Seller</td><td>{{ $complaint->order->seller->full_name ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-5">Courier</td><td>{{ $complaint->order->courier->full_name ?? 'Not assigned' }}</td></tr>
                @else
                    <tr><td class="blade-inline-5">Order</td><td>No order linked</td></tr>
                @endif
                <tr><td class="blade-inline-6">Subject</td><td>{{ $complaint->subject }}</td></tr>
                <tr><td class="blade-inline-7">Filed On</td><td>{{ $complaint->created_at->format('M d, Y h:i A') }}</td></tr>
                <tr><td class="blade-inline-7">Status</td>
                    <td>
                        @php $badgeMap = ['open'=>'badge-pending','under_review'=>'badge-pending','resolved'=>'badge-approved','dismissed'=>'badge-deactivated']; @endphp
                        <span class="badge {{ $badgeMap[$complaint->status] ?? 'badge-pending' }}">{{ ucfirst(str_replace('_',' ',$complaint->status)) }}</span>
                    </td>
                </tr>
            </table>
            <div class="blade-inline-8">
                <div class="blade-inline-9">Details</div>
                <div class="blade-inline-10">{{ $complaint->details }}</div>
            </div>
            @if($complaint->evidence_path)
                <div class="blade-inline-11">
                    <div class="blade-inline-12">Evidence</div>
                    <a href="{{ asset('storage/'.$complaint->evidence_path) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">📎 View Evidence</a>
                </div>
            @endif
        </div>
    </div>

    <div>
        @if(in_array($complaint->status, ['open', 'under_review'], true))
            <div class="card">
                <div class="card-header"><span class="card-title">Investigation</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}">
                        @csrf @method('PATCH')
                        <div class="form-group">
                            <label class="form-label" for="complaint-status">Investigation Status</label>
                            <select id="complaint-status" name="status" class="form-control">
                                <option value="open" @selected($complaint->status === 'open')>Open</option>
                                <option value="under_review" @selected($complaint->status === 'under_review')>Under Review</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="complaint-admin-notes">Investigation Notes</label>
                            <textarea id="complaint-admin-notes" name="admin_notes" class="form-control" rows="4" maxlength="2000">{{ $complaint->admin_notes }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-outline">Save Investigation</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">Resolution</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.complaints.resolve', $complaint) }}">
                        @csrf @method('PATCH')
                        <div class="form-group">
                            <label class="form-label" for="complaint-resolution-status">Outcome Status</label>
                            <select id="complaint-resolution-status" name="status" class="form-control">
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="complaint-resolution-type">Resolution Outcome</label>
                            <select id="complaint-resolution-type" name="resolution_type" class="form-control" required>
                                <option value="no_financial_action">No Financial Action</option>
                                <option value="return" @disabled(!$complaint->order)>Return</option>
                                <option value="refund" @disabled(!$complaint->order)>Refund</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="complaint-refund-amount">Refund Amount</label>
                            <input id="complaint-refund-amount" type="number" name="refund_amount" class="form-control" min="0.01" max="{{ $complaint->order?->amount }}" step="0.01" placeholder="Required for refund">
                            @if($complaint->order)
                                <small>Order amount: {{ number_format((float) $complaint->order->amount, 2) }}</small>
                            @else
                                <small>Link an order before selecting a return or refund.</small>
                            @endif
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="complaint-resolution-notes">Resolution Notes</label>
                            <textarea id="complaint-resolution-notes" name="resolution_notes" class="form-control" rows="5" maxlength="2000" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-coral">Save Resolution</button>
                    </form>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header"><span class="card-title">Final Resolution</span></div>
                <div class="card-body">
                    <p><strong>Outcome:</strong> {{ ucfirst(str_replace('_', ' ', $complaint->resolution_type ?? 'no financial action')) }}</p>
                    <p><strong>Resolved:</strong> {{ $complaint->resolved_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                    <div class="blade-inline-10">{{ $complaint->resolution_notes ?? $complaint->admin_notes ?? 'No resolution notes provided.' }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
