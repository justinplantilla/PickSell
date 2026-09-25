<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PickSell Courier - @yield('title')</title>
    @vite('resources/css/views/courier-layout.css')
    @vite('resources/js/components/form-behaviors.js')
    @include('partials.pagination-styles')
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('courier.orders') }}">Pick<span>Sell</span> <small class="blade-inline-1">COURIER</small></a>
    @include('partials.dashboard-clock')
    <nav>
        <a class="{{ request()->routeIs('courier.orders') ? 'active' : '' }}" href="{{ route('courier.orders') }}">Delivery Tasks</a>
        <a class="{{ request()->routeIs('courier.reports') ? 'active' : '' }}" href="{{ route('courier.reports') }}">Reports</a>
        <a class="{{ request()->routeIs('courier.chat') ? 'active' : '' }}" href="{{ route('courier.chat') }}">Chat</a>
        <a class="{{ request()->routeIs('courier.account') ? 'active' : '' }}" href="{{ route('courier.account') }}">Account</a>
        <form method="POST" action="{{ route('logout') }}" class="blade-inline-2">@csrf<button class="btn btn-outline" type="submit">Log out</button></form>
    </nav>
</header>
<main class="page">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
@yield('scripts')
</body>
</html>
