<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PickSell Logistics - @yield('title')</title>
    @vite('resources/css/views/logistics-layout.css')
    @yield('styles')
    @vite('resources/js/components/form-behaviors.js')
    @include('partials.pagination-styles')
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-text"><a class="brand" href="{{ route('logistics.dashboard') }}">Pick<span>Sell</span></a><div class="sidebar-subtitle">Logistics Panel</div></div><button class="hamburger" type="button" onclick="toggleSidebar()" title="Collapse sidebar"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button></div>
    <nav class="sidebar-nav">
        <a class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}" href="{{ route('logistics.dashboard') }}"><span class="nav-icon">▦</span><span class="nav-text">Dashboard</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.applications') ? 'active' : '' }}" href="{{ route('logistics.applications') }}"><span class="nav-icon">♙</span><span class="nav-text">Rider Management</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.parcels') ? 'active' : '' }}" href="{{ route('logistics.parcels') }}"><span class="nav-icon">▣</span><span class="nav-text">Sorting Center</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.tracking') ? 'active' : '' }}" href="{{ route('logistics.tracking') }}"><span class="nav-icon">◷</span><span class="nav-text">Delivery Tracking</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.reports.deliveries') ? 'active' : '' }}" href="{{ route('logistics.reports.deliveries') }}"><span class="nav-icon">▤</span><span class="nav-text">Delivery Reports</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.messages') ? 'active' : '' }}" href="{{ route('logistics.messages') }}"><span class="nav-icon">✉</span><span class="nav-text">Messages</span></a>
        <a class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}" href="{{ route('logistics.account') }}"><span class="nav-icon">◎</span><span class="nav-text">My Account</span></a>
    </nav>
    <div class="sidebar-footer"><div class="staff-card"><div class="staff-info"><div class="staff-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div><div class="staff-text"><div class="staff-name">{{ auth()->user()->full_name }}</div><div class="staff-role">Logistics Staff</div></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-logout" type="submit" title="Log out"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 0-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></button></form></div></div></div>
</aside>
<div class="main" id="main"><header class="topbar"><div class="topbar-title">@yield('title')</div><div class="topbar-tools">@include('partials.dashboard-clock')<button class="dm-toggle" type="button" data-theme-toggle title="Toggle dark mode">◐</button></div></header><main class="page">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif @yield('content')</main></div>
@vite('resources/js/views/logistics-layout.js')
@yield('scripts')
</body>
</html>
