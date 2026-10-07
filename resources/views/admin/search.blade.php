@extends('admin.layout')
@section('title', 'Search')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<p class="oversight-description">Showing up to 10 matches per section for “{{ $term }}”. Results only include sections you are allowed to view.</p>

@can(\App\Auth\Permission::USERS_VIEW)
<section class="card">
    <div class="card-header"><span class="card-title">Users ({{ $users->count() }})</span></div>
    @forelse($users as $user)
        <p class="oversight-note"><a href="{{ route('admin.users.show', $user) }}">{{ $user->full_name }}</a> · {{ $user->email }} · {{ ucfirst($user->role) }}</p>
    @empty
        <p class="oversight-note">No matching users.</p>
    @endforelse
</section>
@endcan

@can(\App\Auth\Permission::PRODUCTS_VIEW)
<section class="card">
    <div class="card-header"><span class="card-title">Products ({{ $products->count() }})</span></div>
    @forelse($products as $product)
        <p class="oversight-note"><a href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a> · {{ $product->seller?->business_name ?? $product->seller?->full_name ?? 'Seller unavailable' }} · {{ $product->category ?? 'Uncategorized' }}</p>
    @empty
        <p class="oversight-note">No matching products.</p>
    @endforelse
</section>
@endcan

@can(\App\Auth\Permission::ORDERS_VIEW)
<section class="card">
    <div class="card-header"><span class="card-title">Orders ({{ $orders->count() }})</span></div>
    @forelse($orders as $order)
        <p class="oversight-note"><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a> · {{ $order->product_name }} · {{ \App\Services\Orders\OrderLifecycleService::label($order->status) }}</p>
    @empty
        <p class="oversight-note">No matching orders.</p>
    @endforelse
</section>
@endcan
@endsection
