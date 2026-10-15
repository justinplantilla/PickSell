<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($stationHub = request()->attributes->get('station_hub'))
    @php($provinceHub = request()->attributes->get('province_hub'))
    <title>@yield('title') · PickSell Logistics</title>
    @vite('resources/css/views/logistics-layout.css')
    @yield('styles')
    @vite('resources/js/components/form-behaviors.js')
    @include('partials.pagination-styles')
</head>
<body data-confirm-mutations>
<aside class="sidebar">
    <div class="sidebar-logo"><img class="sidebar-mark" src="{{ asset('images/transparent logo.png') }}" alt="PickSell"><div class="sidebar-logo-text"><a class="brand" href="/logistics/dashboard">Pick<span>Sell</span></a><div class="sidebar-subtitle">{{ $provinceHub ? 'Province Hub' : ($stationHub ? 'Delivery Station' : 'Logistics Panel') }}</div></div><button class="hamburger" type="button" onclick="toggleSidebar()" title="Collapse sidebar"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button></div>
    <nav class="sidebar-nav">
        @if($provinceHub)
            <div class="nav-label nav-label--hub" title="{{ $provinceHub->name }}">{{ $provinceHub->name }}</div>
            <a class="nav-item {{ request()->routeIs('province-hub.dashboard') ? 'active' : '' }}" href="/logistics/dashboard"><span class="nav-icon">▦</span><span class="nav-text">Province Dashboard</span></a>
            <a class="nav-item {{ request()->routeIs('province-hub.parcels') ? 'active' : '' }}" href="/logistics/parcels"><span class="nav-icon">▣</span><span class="nav-text">Sorting Center</span></a>
            <a class="nav-item {{ request()->routeIs('province-hub.stations') ? 'active' : '' }}" href="/logistics/stations"><span class="nav-icon">⌂</span><span class="nav-text">Station Hubs</span></a>
            <div class="nav-label">Account</div>
            <a class="nav-item {{ request()->routeIs('*.account') ? 'active' : '' }}" href="/logistics/account"><span class="nav-icon">◎</span><span class="nav-text">My Account</span></a>
        @elseif($stationHub)
            <div class="nav-label nav-label--hub" title="{{ $stationHub->name }}">{{ $stationHub->name }}</div>
            <a class="nav-item {{ request()->routeIs('station-hub.dashboard') ? 'active' : '' }}" href="/logistics/dashboard"><span class="nav-icon">▦</span><span class="nav-text">Station Dashboard</span></a>
            <a class="nav-item {{ request()->routeIs('station-hub.parcels') ? 'active' : '' }}" href="/logistics/parcels"><span class="nav-icon">▣</span><span class="nav-text">Station Operations</span></a>
            <a class="nav-item {{ request()->routeIs('station-hub.riders') ? 'active' : '' }}" href="/logistics/riders"><span class="nav-icon">♙</span><span class="nav-text">Riders</span></a>
            @php($pendingRiderApplications = $stationHub->pendingCourierApplications()->count())
            <a class="nav-item {{ request()->routeIs('station-hub.applications') ? 'active' : '' }}" href="/logistics/applications"><span class="nav-icon">＋</span><span class="nav-text">Rider Applications</span>@if($pendingRiderApplications > 0)<span class="nav-count" aria-label="{{ $pendingRiderApplications }} pending">{{ $pendingRiderApplications }}</span>@endif</a>
            <div class="nav-label">Account</div>
            <a class="nav-item {{ request()->routeIs('*.account') ? 'active' : '' }}" href="/logistics/account"><span class="nav-icon">◎</span><span class="nav-text">My Account</span></a>
        @else
            <a class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}" href="/logistics/dashboard"><span class="nav-icon">▦</span><span class="nav-text">Company Dashboard</span></a>
            <a class="nav-item {{ request()->routeIs('logistics.branches*') ? 'active' : '' }}" href="/logistics/branches"><span class="nav-icon">⌂</span><span class="nav-text">Province Hubs</span></a>
            <div class="nav-label">Monitoring</div>
            <a class="nav-item {{ request()->routeIs('logistics.reports.deliveries') ? 'active' : '' }}" href="/logistics/reports/deliveries"><span class="nav-icon">▤</span><span class="nav-text">Delivery Reports</span></a>
            <a class="nav-item {{ request()->routeIs('logistics.complaints') ? 'active' : '' }}" href="/logistics/complaints"><span class="nav-icon">⚑</span><span class="nav-text">Complaints</span></a>
            <a class="nav-item {{ request()->routeIs('logistics.messages') ? 'active' : '' }}" href="/logistics/messages"><span class="nav-icon">✉</span><span class="nav-text">Messages</span></a>
            <a class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}" href="/logistics/account"><span class="nav-icon">◎</span><span class="nav-text">My Account</span></a>
        @endif
    </nav>
    <div class="sidebar-footer"><div class="staff-card"><div class="staff-info"><div class="staff-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div><div class="staff-text"><div class="staff-name">{{ auth()->user()->full_name }}</div><div class="staff-role">{{ $provinceHub ? 'Province Hub Operator' : ($stationHub ? 'Station Hub Operator' : 'Company Logistics') }}</div></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-logout" type="submit" title="Log out"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 0-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></button></form></div></div></div>
</aside>
<div class="main" id="main">
    <header class="topbar">
        <div class="topbar-title">@yield('title')</div>
        <div class="topbar-tools">
            @if($provinceHub)
                <span class="hub-context" title="You are working in this province hub's portal">Province Hub · {{ $provinceHub->name }}</span>
            @elseif($stationHub)
                <span class="hub-context" title="You are working in this station hub's portal">Station · {{ $stationHub->name }} <small>under {{ $stationHub->parent?->name }}</small></span>
            @endif
            @include('partials.dashboard-clock')
            <button class="dm-toggle" type="button" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
        </div>
    </header>
    <main class="page">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-error" role="alert">
                @if($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <strong>Please fix the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @endif
            </div>
        @endif
        @unless(View::hasSection('no_intro'))@include('partials.page-intro')@endunless
        @yield('content')
    </main>
</div>
@vite('resources/js/views/logistics-layout.js')
@yield('scripts')
</body>
</html>
