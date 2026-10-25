<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell Admin — @yield('title')</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @vite('resources/css/views/admin-layout.css')
    @vite('resources/css/components/stock-status-badge.css')
    @vite('resources/js/components/form-behaviors.js')
    @vite('resources/js/components/admin-saved-filters.js')
    @yield('styles')
    @include('partials.pagination-styles')
    @vite('resources/css/views/admin-theme.css')
</head>
<body class="ad-theme" data-confirm-mutations>
    {{-- Apply the saved theme before first paint so dark mode does not flash light. --}}
    <script>try { if (localStorage.getItem('adminDark') === 'true') document.body.classList.add('dark'); } catch (e) {}</script>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img class="sidebar-mark" src="{{ asset('images/transparent logo.png') }}" alt="PickSell">
            <div class="sidebar-logo-text">
                <a href="/admin/dashboard">Pick<span>Sell</span></a>
                <small>Admin Panel</small>
            </div>
            <button type="button" class="hamburger" onclick="toggleSidebar()" aria-label="Collapse sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Admin">
            @foreach(\App\Support\AdminNavigation::groups() as $group)
                @canany(array_column($group['items'], 'permission'))
                    <div class="nav-group-label">{{ $group['label'] }}</div>
                    <hr class="nav-divider">
                    @foreach($group['items'] as $item)
                        @can($item['permission'])
                            @php $isActive = request()->is(...$item['active']); @endphp
                            <a href="{{ route($item['route']) }}" class="nav-item {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\AdminNavigation::icon($item['key']) !!}</svg></span>
                                <span class="nav-label">{{ $item['label'] }}</span>
                                @if($item['key'] === 'registrations' && ($pendingApprovalCount ?? 0) > 0)
                                    <span class="nav-badge" aria-label="{{ $pendingApprovalCount }} pending">{{ $pendingApprovalCount }}</span>
                                @endif
                            </a>
                        @endcan
                    @endforeach
                @endcanany
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <div class="admin-card">
                <div class="admin-info">
                    <a href="/admin/account" class="admin-avatar" title="My Account">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</a>
                    <div class="admin-text">
                        <a href="/admin/account" class="blade-inline-1">
                            <div class="admin-name">{{ auth()->user()->full_name }}</div>
                            <div class="admin-role">Administrator</div>
                        </a>
                    </div>
                    <form method="POST" action="/logout" id="logoutForm">
                        @csrf
                        <button type="button" class="btn-logout" title="Logout" onclick="confirmLogout()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="main" id="main">
        <div class="topbar">
            <div class="topbar-title">@yield('title')</div>
            <div class="topbar-right blade-inline-2">
                @can(\App\Auth\Permission::ADMIN_SEARCH)
                    <form method="GET" action="{{ route('admin.search') }}" class="admin-global-search" role="search">
                        <input id="admin-global-search" type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search admin…" aria-label="Search users, products and orders" minlength="2" maxlength="100">
                        <button type="submit" aria-label="Search">Search</button>
                    </form>
                @endcan
                <button type="button" class="notif-btn" id="notifBtn" onclick="toggleNotif()" aria-label="Notifications"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg><span class="notif-dot" id="notifDot"></span></button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header"><span>Notifications <span id="notifCount" class="blade-inline-3"></span></span><button type="button" class="notif-mark-read" id="markAdminNotificationsRead">Mark all as read</button></div>
                    <div id="notifList"><div class="notif-empty">Loading...</div></div>
                </div>
                <button type="button" class="dm-toggle" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">
                    <svg class="dm-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
                    <svg class="dm-sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
                @include('partials.dashboard-clock')
            </div>
        </div>
        <div class="content">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif
            @unless(request()->routeIs('admin.dashboard'))
                @include('partials.page-intro')
            @endunless
            @yield('content')
        </div>
    </div>
@vite('resources/js/views/admin-layout.js')
@yield('scripts')
<dialog id="logoutModal" class="ad-dialog" aria-labelledby="logoutModalTitle">
    <form method="dialog" class="ad-dialog-panel">
        <span class="ad-dialog-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
        </span>
        <h2 id="logoutModalTitle">Log out of PickSell Admin?</h2>
        <p>Are you sure you want to log out of your admin account?</p>
        <div class="ad-dialog-actions">
            <button type="submit" value="cancel" class="btn btn-outline" data-dialog-cancel>Stay signed in</button>
            <button type="submit" value="confirm" class="btn btn-coral">Yes, log out</button>
        </div>
    </form>
</dialog>
@include('partials.search-suggestions')
</body>
</html>
