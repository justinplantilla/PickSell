<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell Seller — @yield('title')</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @vite('resources/css/views/seller-layout.css')
    @vite('resources/js/components/form-behaviors.js')
    @yield('styles')
    @include('partials.pagination-styles')
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-text">
                <a href="/seller/dashboard">Pick<span>Sell</span></a>
                <small>Seller Panel</small>
            </div>
            <button class="hamburger" onclick="toggleSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-group-label">Overview</div>
            <hr class="nav-divider">
            <a href="/seller/dashboard" class="nav-item {{ request()->is('seller/dashboard') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>

            <div class="nav-group-label">Store</div>
            <hr class="nav-divider">
            <a href="/seller/inventory" class="nav-item {{ request()->is('seller/inventory*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 0c-1.3 0-2.48.52-3.36 1.36C9.12.52 7.94 0 6.64 0 4.06 0 2 2.06 2 4.64c0 .48.11.92.18 1.36H0v14c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6.64-4c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C11.08 3 12.08 2 13.36 2zM6.64 2c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C4.36 3 5.36 2 6.64 2zM20 18H4V8h16v10z"/></svg></span>
                <span class="nav-label">Inventory</span>
            </a>
            <a href="/seller/orders" class="nav-item {{ request()->is('seller/orders*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg></span>
                <span class="nav-label">Orders</span>
            </a>

            <div class="nav-group-label">Analytics</div>
            <hr class="nav-divider">
            <a href="/seller/reports" class="nav-item {{ request()->is('seller/reports*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></span>
                <span class="nav-label">Reports</span>
            </a>

            <div class="nav-group-label">System</div>
            <hr class="nav-divider">
            <a href="/seller/chat" class="nav-item {{ request()->is('seller/chat*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg></span>
                <span class="nav-label">Chat / Messaging</span>
            </a>
            <a href="/seller/account" class="nav-item {{ request()->is('seller/account*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></span>
                <span class="nav-label">My Account</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="admin-card">
                <div class="admin-info">
                    <a href="/seller/account" class="admin-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</a>
                    <div class="admin-text">
                        <a href="/seller/account" class="blade-inline-1">
                            <div class="admin-name">{{ auth()->user()->full_name }}</div>
                            <div class="admin-role">{{ auth()->user()->business_name ?? 'Seller' }}</div>
                        </a>
                    </div>
                    <form method="POST" action="/logout" id="logoutForm">
                        @csrf
                        <button type="button" class="btn-logout" title="Logout" onclick="document.getElementById('logoutModal').style.display='flex'">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="main" id="main">
        <div class="topbar">
            <div class="topbar-title">@yield('title')</div>
            <div class="topbar-right">
                @include('partials.dashboard-clock')
            </div>
        </div>
        <div class="content">
            @php
                $announcements = \App\Models\Announcement::where('active', true)
                    ->where(fn($q) => $q->where('audience', 'all')->orWhere('audience', 'seller'))
                    ->latest()->get();
            @endphp
            @foreach($announcements as $ann)
            <div class="seller-announcement" data-announcement-id="{{ $ann->id }}" class="blade-inline-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#d97706" viewBox="0 0 24 24" class="blade-inline-3"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                <div class="blade-inline-4"><strong>{{ $ann->title }}</strong> — {{ $ann->message }}</div>
                <button type="button" aria-label="Close announcement" title="Close" onclick="dismissSellerAnnouncement(this)" class="blade-inline-5">&times;</button>
            </div>
            @endforeach
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </div>

@vite('resources/js/views/seller-layout.js')
@vite('resources/js/views/seller-layout.js')

<button class="dm-toggle" data-theme-toggle title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

<!-- Logout Modal -->
<div id="logoutModal" class="blade-inline-6">
    <div class="blade-inline-7">
        <div class="blade-inline-8"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#E8472A" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></div>
        <div class="blade-inline-9">Log Out?</div>
        <div class="blade-inline-10">Are you sure you want to log out of your seller account?</div>
        <div class="blade-inline-11">
            <button onclick="document.getElementById('logoutModal').style.display='none'" class="blade-inline-12">Cancel</button>
            <button onclick="document.getElementById('logoutForm').submit()" class="blade-inline-13">Yes, Log Out</button>
        </div>
    </div>
</div>
@yield('scripts')
@include('partials.search-suggestions')
</body>
</html>
