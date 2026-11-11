@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-application-detail.css', 'resources/css/views/admin-logistics-issues.css'])
@endsection
@section('title', 'Off-platform review')

@section('content')
@php
    $name = $seller->business_name ?: $seller->full_name;
    $recommend = [
        'warn' => 'First offence: warn the seller. A warning counts as strike 1.',
        'case' => 'Strike 1 already: open a compliance case.',
        'suspend' => 'Two or more strikes: consider suspending the seller.',
    ][$risk['recommended']];
@endphp
<p class="review-back"><a href="{{ route('admin.off-platform') }}" class="btn btn-outline btn-sm">← Back to off-platform monitoring</a></p>
@if($errors->has('off_platform'))<div class="alert alert-danger" role="alert">{{ $errors->first('off_platform') }}</div>@endif

<div class="review-layout">
    <section class="card" aria-labelledby="seller-heading">
        <div class="card-header">
            <span class="card-title" id="seller-heading">{{ $name }}</span>
            <span class="badge badge-{{ $seller->status === 'approved' ? 'approved' : 'suspended' }}">{{ ucfirst($seller->status) }}</span>
        </div>
        <div class="oversight-pipeline">
            <div class="oversight-stage {{ $risk['level'] === 'high' ? 'is-alert' : '' }}"><strong>{{ $risk['score'] }}</strong><span>Risk score ({{ $risk['level'] }})</span></div>
            <div class="oversight-stage {{ $risk['strikes'] >= 2 ? 'is-alert' : '' }}"><strong>{{ $risk['strikes'] }} / 3</strong><span>Strikes (180 days)</span></div>
            <div class="oversight-stage"><strong>{{ $risk['open'] }}</strong><span>Open signals</span></div>
        </div>
        <div class="card-body issue-links">
            <a href="{{ route('admin.compliance.seller', $seller) }}" class="btn btn-outline btn-sm">Compliance profile</a>
            <a href="{{ route('admin.users.show', $seller) }}" class="btn btn-outline btn-sm">User account</a>
        </div>
    </section>

    @if($canManage && $seller->status === 'approved')
    <section class="card" aria-labelledby="decide-heading">
        <div class="card-header"><span class="card-title" id="decide-heading">Decide</span></div>
        <div class="card-body">
            <p class="alert alert-info">Recommended: {{ $recommend }}</p>
            <form method="POST" action="{{ route('admin.off-platform.act', $seller) }}" class="issue-form">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="action">Action *</label>
                    <select id="action" name="action" class="form-control" required>
                        <option value="warn" @selected($risk['recommended'] === 'warn')>Warn the seller (strike)</option>
                        <option value="case" @selected($risk['recommended'] === 'case')>Open a compliance case (off-platform transaction)</option>
                        <option value="suspend" @selected($risk['recommended'] === 'suspend')>Suspend the seller</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="severity">Case severity (for a case)</label>
                    <select id="severity" name="severity" class="form-control">
                        @foreach($severities as $severity)<option value="{{ $severity }}" @selected($severity === 'high')>{{ ucfirst($severity) }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="message">Message to the seller / reason *</label>
                    <textarea id="message" name="message" class="form-control" rows="4" required minlength="20" maxlength="5000" placeholder="Sharing contact details or arranging an outside courier breaks PickSell's seller terms…">{{ old('message') }}</textarea>
                    @error('message')<div class="account-danger-text">{{ $message }}</div>@enderror
                </div>
                <p class="oversight-meta">All open signals below are closed with this decision.</p>
                <button type="submit" class="btn btn-coral btn-sm" data-confirm="Apply this action to {{ $name }}?">Apply</button>
            </form>
        </div>
    </section>
    @endif

    <section class="card" aria-labelledby="flags-heading">
        <div class="card-header"><span class="card-title" id="flags-heading">Signals</span></div>
        <ol class="issue-history">
            @forelse($flags as $flag)
                <li>
                    <strong>{{ $flag->label() }}</strong>
                    <span class="oversight-meta">{{ \App\Models\OffPlatformFlag::KINDS[$flag->kind] ?? $flag->kind }} · weight {{ $flag->weight }} · {{ $flag->created_at->format('M d, Y h:i A') }}
                        · {{ $flag->status === 'open' ? 'Open' : ($flag->status === 'dismissed' ? 'Dismissed' : (\App\Models\OffPlatformFlag::ACTIONS[$flag->action] ?? $flag->action)) }}@if($flag->reviewer) by {{ $flag->reviewer->full_name }}@endif</span>
                    <p>{{ $flag->evidence }}</p>
                    @if($flag->buyer)<p class="oversight-meta">Buyer: {{ $flag->buyer->full_name }}</p>@endif
                    @if($flag->product)<p class="oversight-meta">Listing: {{ $flag->product->name }} ({{ $flag->product->status }})</p>@endif
                    @if($flag->order)<p class="oversight-meta">Order: <a href="{{ route('admin.orders.show', $flag->order) }}">{{ $flag->order->order_number }}</a> ({{ $flag->order->status }})</p>@endif
                    @if($flag->review_note)<p class="oversight-meta">Note: {{ $flag->review_note }}</p>@endif
                    @if($canManage && $flag->status === 'open')
                        <div class="issue-inline-form">
                            @if($flag->kind === 'product' && $flag->product?->status === 'archived')
                                <form method="POST" action="{{ route('admin.off-platform.restore-listing', $flag) }}">@csrf @method('PATCH')
                                    <button type="submit" class="btn btn-outline btn-sm">Approve listing &amp; restore</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.off-platform.dismiss', $flag) }}">@csrf @method('PATCH')
                                <button type="submit" class="btn btn-outline btn-sm">Dismiss (false alarm)</button></form>
                        </div>
                    @endif
                </li>
            @empty
                <li>No signals for this seller.</li>
            @endforelse
        </ol>
        @if($flags->hasPages())<div class="dashboard-pagination">{{ $flags->links() }}</div>@endif
    </section>
</div>
@endsection
