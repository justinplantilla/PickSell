@extends('layouts.app')
@section('title', 'Dashboard')

@section('styles')
<style>
    .dashboard { max-width: 900px; margin: 3rem auto; padding: 0 1.5rem; }
    .dash-header { margin-bottom: 2rem; }
    .dash-header h1 { font-size: 1.8rem; font-weight: 800; }
    .dash-header p { color: #777; margin-top: 0.3rem; }
    .accent { color: var(--coral); }
    .dash-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.2rem; margin-bottom: 2.5rem; }
    .dash-card { background: #fff; border: 1px solid #e8e2d8; border-radius: 12px; padding: 1.5rem; }
    .dash-card-num { font-size: 2rem; font-weight: 800; color: var(--coral); }
    .dash-card-label { font-size: 0.85rem; color: #888; margin-top: 0.2rem; }
    .logout-form { display: inline; }
    .btn-logout { background: transparent; border: 1.5px solid #ddd6c8; color: var(--charcoal); padding: 0.5rem 1.2rem; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-logout:hover { border-color: var(--coral); color: var(--coral); }
</style>
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
