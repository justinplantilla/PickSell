<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell — @yield('title')</title>
    @vite('resources/css/views/buyer-layout.css')
    @vite('resources/js/components/form-behaviors.js')
    @yield('styles')
    @include('partials.pagination-styles')
</head>
<body>
    <nav class="navbar">
        <a href="/buyer/shop" class="navbar-brand"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>
        <form class="navbar-search" method="GET" action="/buyer/shop">
            <input type="text" name="search" data-product-search value="{{ request('search') }}" placeholder="Search products...">
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </form>
        <div class="navbar-right">
            @include('partials.dashboard-clock')
            <div class="nav-notification-wrap">
                <button class="nav-icon-btn notif-btn" id="buyerNotifBtn" type="button" onclick="toggleBuyerNotif()" aria-label="Buyer notifications">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    <span class="notif-dot" id="buyerNotifDot"></span>
                </button>
                <div class="notif-dropdown" id="buyerNotifDropdown">
                    <div class="notif-header"><span>Notifications <span id="buyerNotifCount"></span></span><button type="button" class="notif-mark-read" id="markBuyerNotificationsRead">Mark all as read</button></div>
                    <div id="buyerNotifList"><div class="notif-empty">Loading...</div></div>
                </div>
            </div>
            <a href="/buyer/chat" class="nav-icon-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                <span>Messages</span>
            </a>
            <a href="/buyer/cart" class="nav-icon-btn">
                <div class="cart-badge-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                    @if(isset($cartCount) && $cartCount > 0)
                    <span class="cart-badge">{{ $cartCount }}</span>
                    @endif
                </div>
                <span>Cart</span>
            </a>
            <div class="nav-user" onclick="toggleUserMenu()">
                <div class="nav-user-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
                <span>{{ auth()->user()->first_name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                <div class="user-dropdown" id="userDropdown">
                    <a href="/buyer/orders">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        My Orders
                    </a>
                    <a href="/buyer/account">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                        My Account
                    </a>
                    <hr>
                    <form method="POST" action="/logout" data-logout-form data-confirm-message="Are you sure you want to log out?">
                        @csrf
                        <button type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    @yield('catbar')

    <div class="page-content">
        @php
            $announcements = \App\Models\Announcement::where('active', true)
                ->where(fn($q) => $q->where('audience', 'all')->orWhere('audience', 'buyer'))
                ->latest()->get();
        @endphp
        <div class="announcement-stack" id="announcementStack">
        @foreach($announcements as $ann)
            @php
                $announcementText = strtolower($ann->title . ' ' . $ann->message);
                $announcementType = str_contains($announcementText, 'urgent') || str_contains($announcementText, 'outage') || str_contains($announcementText, 'emergency') ? 'announcement-urgent' : (str_contains($announcementText, 'maintenance') || str_contains($announcementText, 'maintainance') ? 'announcement-maintenance' : '');
                $announcementLabel = $announcementType === 'announcement-urgent' ? 'Urgent notice' : ($announcementType === 'announcement-maintenance' ? 'Maintenance notice' : 'Announcement');
            @endphp
            <div class="announcement-banner {{ $announcementType }}" data-announcement-id="{{ $ann->id }}">
                <span class="announcement-icon" aria-hidden="true">
                    @if($announcementType === 'announcement-maintenance')
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.4-6.8C10.4 1.3 7.6.8 5.3 2.1L9.9 6.7 6.7 9.9 2.1 5.3C.8 7.6 1.3 10.4 3.1 12.2c1.8 1.8 4.5 2.3 6.8 1.4l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.4-.4.4-1 0-1.4z"/></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                    @endif
                </span>
                <div class="announcement-copy"><span class="announcement-label">{{ $announcementLabel }}</span><strong>{{ $ann->title }}</strong>{{ $ann->message }}</div>
                <button class="announcement-dismiss" type="button" aria-label="Dismiss announcement" title="Dismiss" onclick="dismissAnnouncement({{ $ann->id }}, this)">×</button>
            </div>
        @endforeach
        </div>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </div>

    @include('partials.footer', ['buyerFooter' => true])

<button class="dm-toggle" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

@vite('resources/js/views/buyer-layout.js')
@yield('scripts')
@include('partials.search-suggestions')
</body>
</html>
