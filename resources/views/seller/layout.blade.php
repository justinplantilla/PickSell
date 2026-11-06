<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell Seller — @yield('title')</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@8.3.1/dist/apexcharts.min.js"></script>
    @vite('resources/css/views/seller-layout.css')
    @vite('resources/css/components/stock-status-badge.css')
    @vite('resources/js/components/form-behaviors.js')
    @yield('styles')
    @include('partials.pagination-styles')
    {{-- Console theme shared with Admin and the Pickrush HQ, then seller-only pieces; both load last. --}}
    @vite(['resources/css/views/admin-theme.css', 'resources/css/views/seller-theme.css'])
</head>
<body class="ad-theme sl-theme @yield('body-class')" data-confirm-mutations>
    @if(session()->pull('picksell_welcome'))
        @include('partials.welcome-splash', ['brand' => 'picksell', 'name' => auth()->user()->first_name, 'role' => 'Seller'])
    @endif
    {{-- Apply the saved theme before first paint so dark mode does not flash light. --}}
    <script>try { if (localStorage.getItem('sellerDark') === 'true') document.body.classList.add('dark'); } catch (e) {}</script>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img class="sidebar-mark" src="{{ asset('images/transparent logo.png') }}" alt="PickSell">
            <div class="sidebar-logo-text">
                <a href="/seller/dashboard">Pick<span>Sell</span></a>
                <small>Seller Panel</small>
            </div>
            <button type="button" class="hamburger" data-sidebar-toggle aria-label="Collapse sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Seller">
            <div class="nav-group-label">Overview</div>
            <hr class="nav-divider">
            <a href="/seller/dashboard" class="nav-item {{ request()->is('seller/dashboard') ? 'active' : '' }}" title="Dashboard">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>

            <div class="nav-group-label">Store</div>
            <hr class="nav-divider">
            <a href="/seller/inventory" class="nav-item {{ request()->is('seller/inventory*') ? 'active' : '' }}" title="Inventory">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 0c-1.3 0-2.48.52-3.36 1.36C9.12.52 7.94 0 6.64 0 4.06 0 2 2.06 2 4.64c0 .48.11.92.18 1.36H0v14c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6.64-4c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C11.08 3 12.08 2 13.36 2zM6.64 2c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C4.36 3 5.36 2 6.64 2zM20 18H4V8h16v10z"/></svg></span>
                <span class="nav-label">Inventory</span>
            </a>
            <a href="/seller/orders" class="nav-item {{ request()->is('seller/orders*') ? 'active' : '' }}" title="Orders">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg></span>
                <span class="nav-label">Orders</span>
            </a>
            <a href="{{ route('seller.vouchers') }}" class="nav-item {{ request()->is('seller/vouchers*') ? 'active' : '' }}" title="Vouchers">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 1 0 4v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4Zm-7 7h-2v-2h2v2Zm0-4h-2v-2h2v2Zm0-4h-2V7h2v2Z"/></svg></span>
                <span class="nav-label">Vouchers</span>
            </a>
            <a href="{{ route('seller.returns', ['status' => 'requested']) }}" class="nav-item {{ request()->is('seller/returns*') ? 'active' : '' }}" title="Returns">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 5V2L7 7l5 5V8c3.31 0 6 2.69 6 6a6 6 0 0 1-10.24 4.24l-1.42 1.42A8 8 0 0 0 20 14c0-4.42-3.58-8-8-8z"/></svg></span>
                <span class="nav-label">Returns</span>
                <span class="nav-badge" id="sellerReturnsBadge" data-count-url="{{ route('seller.returns.count') }}" aria-live="polite" aria-label="{{ $pendingSellerReturnCount }} return requests awaiting response" @if($pendingSellerReturnCount === 0) hidden @endif>{{ $pendingSellerReturnCount }}</span>
            </a>

            <div class="nav-group-label">Analytics</div>
            <hr class="nav-divider">
            <a href="/seller/reports" class="nav-item {{ request()->is('seller/reports*') ? 'active' : '' }}" title="Reports">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></span>
                <span class="nav-label">Reports</span>
            </a>
            <a href="{{ route('seller.earnings') }}" class="nav-item {{ request()->is('seller/earnings*') ? 'active' : '' }}" title="Earnings">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15.9V19h-2v-1.1c-1.3-.2-2.3-.9-2.7-2.1l1.8-.7c.3.8.9 1.2 1.9 1.2 1 0 1.6-.4 1.6-1 0-.5-.3-.8-1.8-1.2-1.8-.5-3.1-1.1-3.1-2.8 0-1.3.9-2.3 2.3-2.6V7h2v1.1c1.1.2 1.9.8 2.3 1.8l-1.7.8c-.3-.7-.8-1-1.6-1-.9 0-1.4.4-1.4 1 0 .6.5.8 1.9 1.2 1.7.5 3 1.1 3 2.8 0 1.5-1 2.5-2.5 2.8z"/></svg></span>
                <span class="nav-label">Earnings</span>
            </a>
            <a href="{{ route('seller.analytics') }}" class="nav-item {{ request()->is('seller/analytics*') ? 'active' : '' }}" title="Product Performance">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M3.5 18.49 9.5 12.48l4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg></span>
                <span class="nav-label">Product Performance</span>
            </a>
            <a href="{{ route('seller.reviews') }}" class="nav-item {{ request()->is('seller/reviews*') ? 'active' : '' }}" title="Reviews">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"/></svg></span>
                <span class="nav-label">Reviews</span>
            </a>

            <div class="nav-group-label">System</div>
            <hr class="nav-divider">
            <a href="/seller/chat" class="nav-item {{ request()->is('seller/chat*') ? 'active' : '' }}" title="Chat / Messaging">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg></span>
                <span class="nav-label">Chat / Messaging</span>
            </a>
            <a href="/seller/account" class="nav-item {{ request()->is('seller/account*') ? 'active' : '' }}" title="My Account">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></span>
                <span class="nav-label">My Account</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="admin-card">
                <div class="admin-info">
                    <a href="/seller/account" class="admin-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</a>
                    <div class="admin-text">
                        <a href="/seller/account" class="seller-profile-link">
                            <div class="admin-name">{{ auth()->user()->full_name }}</div>
                            <div class="admin-role">{{ auth()->user()->business_name ?? 'Seller' }}</div>
                        </a>
                    </div>
                    <form method="POST" action="/logout" id="logoutForm" data-logout-form data-confirm-message="Are you sure you want to log out?">
                        @csrf
                        <button type="button" class="btn-logout" title="Logout" aria-label="Log out" data-logout>
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
                <button class="notif-btn" id="notifBtn" type="button" aria-label="Seller notifications">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    <span class="notif-dot" id="sellerNotifDot"></span>
                </button>
                <div class="notif-dropdown" id="sellerNotifDropdown">
                    <div class="notif-header"><span>Notifications <span id="sellerNotifCount"></span></span><button type="button" class="notif-mark-read" id="markSellerNotificationsRead">Mark all as read</button></div>
                    <div id="sellerNotifList"><div class="notif-empty">Loading...</div></div>
                </div>
                <button type="button" class="dm-toggle" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">
                    <svg class="dm-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
                    <svg class="dm-sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
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
            <div class="seller-announcement" data-announcement-id="{{ $ann->id }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#d97706" viewBox="0 0 24 24" class="announcement-icon"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                <div class="announcement-copy"><strong>{{ $ann->title }}</strong> — {{ $ann->message }}</div>
                <button type="button" aria-label="Close announcement" title="Close" data-dismiss-announcement class="announcement-dismiss">&times;</button>
            </div>
            @endforeach
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif
            @include('partials.page-intro')
            @yield('content')
        </div>
    </div>

@vite('resources/js/views/seller-layout.js')

<dialog id="logoutModal" class="ad-dialog" aria-labelledby="logoutModalTitle">
    <form method="dialog" class="ad-dialog-panel">
        <span class="ad-dialog-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
        </span>
        <h2 id="logoutModalTitle">Log out of PickSell Seller?</h2>
        <p>Are you sure you want to log out of your seller account?</p>
        <div class="ad-dialog-actions">
            <button type="submit" value="cancel" class="btn btn-outline" data-dialog-cancel>Stay signed in</button>
            <button type="submit" value="confirm" class="btn btn-coral">Yes, log out</button>
        </div>
    </form>
</dialog>
@yield('scripts')
@include('partials.search-suggestions')
</body>
</html>
