<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell Admin — @yield('title')</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @vite('resources/css/views/admin-layout.css')
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
        <nav class="sidebar-nav">
            <div class="nav-group-label">Overview</div>
            <hr class="nav-divider">
            <a href="/admin/dashboard" class="nav-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
                <span class="nav-label">Dashboard</span>
                @if(isset($stats) && $stats['pending'] > 0)
                    <span class="nav-badge">{{ $stats['pending'] }}</span>
                @endif
            </a>

            <div class="nav-group-label">User Management</div>
            <hr class="nav-divider">
            <a href="/admin/registrations" class="nav-item {{ request()->is('admin/registrations*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/></svg></span>
                <span class="nav-label">Registrations</span>
            </a>
            <a href="/admin/users" class="nav-item {{ request()->is('admin/users*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
                <span class="nav-label">User Accounts</span>
            </a>
            <a href="/admin/products" class="nav-item {{ request()->is('admin/products*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 1.36 12.48.52 11.3 0 10 0 7.42 0 5.36 2.06 5.36 4.64c0 .48.11.92.18 1.36H3v14h18V6h-1zM12 2c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C9.72 3 10.72 2 12 2zM5 8h14v10H5V8z"/></svg></span>
                <span class="nav-label">Products</span>
            </a>

            <div class="nav-group-label">Operations</div>
            <hr class="nav-divider">
            <a href="/admin/compliance" class="nav-item {{ request()->is('admin/compliance*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg></span>
                <span class="nav-label">Seller Compliance</span>
            </a>
            <a href="/admin/complaints" class="nav-item {{ request()->is('admin/complaints*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></span>
                <span class="nav-label">Complaints</span>
            </a>
            <a href="/admin/commission" class="nav-item {{ request()->is('admin/commission*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></span>
                <span class="nav-label">Commission</span>
            </a>
            <a href="/admin/logistics" class="nav-item {{ request()->is('admin/logistics*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-5l-3-4zM6 18.5A1.5 1.5 0 1 1 7.5 17 1.5 1.5 0 0 1 6 18.5zM17 9.5h2.5l1.96 2.5H17v-2.5zM19.5 18.5A1.5 1.5 0 1 1 21 17a1.5 1.5 0 0 1-1.5 1.5z"/></svg></span>
                <span class="nav-label">Sorting Center</span>
            </a>

            <div class="nav-group-label">Analytics</div>
            <hr class="nav-divider">
            <a href="/admin/reports" class="nav-item {{ request()->is('admin/reports*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></span>
                <span class="nav-label">Reports</span>
            </a>

            <div class="nav-group-label">System</div>
            <hr class="nav-divider">
            <a href="/admin/settings" class="nav-item {{ request()->is('admin/settings*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a7.01 7.01 0 0 0-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.47.47 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.37 1.04.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.57 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg></span>
                <span class="nav-label">Platform Settings</span>
            </a>
            <a href="/admin/chat" class="nav-item {{ request()->is('admin/chat*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg></span>
                <span class="nav-label">Chat / Messaging</span>
            </a>
            <a href="/admin/account" class="nav-item {{ request()->is('admin/account*') ? 'active' : '' }}">
                <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></span>
                <span class="nav-label">My Account</span>
            </a>
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
                    <div class="notif-header">Notifications <span id="notifCount" class="blade-inline-3"></span></div>
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
@vite('resources/js/views/admin-layout.js')
<div id="logoutModal" class="blade-inline-4">
    <div class="blade-inline-5">
        <div class="blade-inline-6"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#E8472A" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></div>
        <div class="blade-inline-7">Log Out?</div>
        <div class="blade-inline-8">Are you sure you want to log out of your admin account?</div>
        <div class="blade-inline-9">
            <button onclick="document.getElementById('logoutModal').style.display='none'" class="blade-inline-10">Cancel</button>
            <button onclick="document.getElementById('logoutForm').submit()" class="blade-inline-11">Yes, Log Out</button>
        </div>
    </div>
</div>
@include('partials.search-suggestions')
</body>
</html>
