@extends('layouts.app')
@section('title', 'Dashboard')

@section('styles')
@vite('resources/css/views/dashboard.css')
@endsection

@section('content')
<div class="dashboard">
    <div class="dash-header">
        <h1>Hello, <span class="accent">{{ Auth::user()->name }}</span> 👋</h1>
        <p>Welcome to your PickSell dashboard.</p>
    </div>

    <div class="dash-cards">
        <div class="dash-card"><div class="dash-card-num">0</div><div class="dash-card-label">Active Listings</div></div>
        <div class="dash-card"><div class="dash-card-num">0</div><div class="dash-card-label">Orders</div></div>
        <div class="dash-card"><div class="dash-card-num">₱0</div><div class="dash-card-label">Total Earnings</div></div>
        <div class="dash-card"><div class="dash-card-num">0</div><div class="dash-card-label">Saved Items</div></div>
    </div>

    <form class="logout-form" method="POST" action="/logout">
        @csrf
        <button type="submit" class="btn-logout">Log Out</button>
    </form>
</div>
@endsection
