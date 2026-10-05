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
    @yield('styles')
    @include('partials.pagination-styles')
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-text">
                <a href="/admin/dashboard">Pick<span>Sell</span></a>
                <small>Admin Panel</small>
            </div>
            <button class="hamburger" onclick="toggleSidebar()">
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
                <button class="notif-btn" id="notifBtn" onclick="toggleNotif()"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg><span class="notif-dot" id="notifDot"></span></button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header"><span>Notifications <span id="notifCount" class="blade-inline-3"></span></span><button type="button" class="notif-mark-read" id="markAdminNotificationsRead">Mark all as read</button></div>
                    <div id="notifList"><div class="notif-empty">Loading...</div></div>
                </div>
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
            @yield('content')
        </div>
    </div>
@vite('resources/js/views/admin-layout.js')
<!-- Dark Mode Toggle -->
<button class="dm-toggle" data-theme-toggle title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>
@yield('scripts')
<div id="logoutModal" class="admin-logout-modal">
    <div class="admin-logout-dialog">
        <div class="blade-inline-6"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#E8472A" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></div>
            <div class="admin-logout-title">Log Out?</div>
            <div class="admin-logout-message">Are you sure you want to log out of your admin account?</div>
            <div class="admin-logout-actions">
                <button onclick="document.getElementById('logoutModal').style.display='none'" class="admin-logout-cancel">Cancel</button>
                <button onclick="document.getElementById('logoutForm').submit()" class="admin-logout-confirm">Yes, Log Out</button>
            </div>
    </div>
</div>
@include('partials.search-suggestions')
</body>
</html>
