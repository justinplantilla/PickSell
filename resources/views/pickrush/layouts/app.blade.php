<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Dashboard') | Pickrush Logistics</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @php($staff = \App\Pickrush\Support\Actor::staff())
    {{-- Every portal (HQ, province, station) uses the dark console theme; the hub class adds hub-only pieces. --}}
    @php($isStation = (bool) $staff->hub?->isStation())
    @php($isProvince = (bool) $staff->hub?->isProvince())
    @php($hqTheme = $staff->isCompany() || $isStation || $isProvince)
    @vite($hqTheme ? ['resources/css/pickrush/app.css', 'resources/css/pickrush/hq.css', 'resources/js/pickrush/portal.js'] : ['resources/css/pickrush/app.css', 'resources/js/pickrush/portal.js'])
</head>
<body @class(['pr-hq' => $hqTheme, 'pr-station' => $isStation, 'pr-province' => $isProvince])>
<div class="pr-shell">
    @if($hqTheme)
        @include('pickrush.partials.hq-sidebar')
    @else
        <aside class="pr-sidebar">
            <a href="{{ route('pickrush.dashboard') }}" class="pr-brand">@include('pickrush.partials.logo-mark', ['class' => 'pr-brand-logo', 'size' => 32]) Pickrush</a>
            <nav class="pr-nav" aria-label="Main">
                @foreach(\App\Pickrush\Support\Navigation::for($staff) as $item)
                    <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="pr-who">
                <strong>{{ $staff->full_name }}</strong>
                {{ $staff->role->label() }}@if($staff->hub) · {{ $staff->hub->name }}@else · HQ @endif
                <a href="{{ route('pickrush.account') }}" style="color:#dfe4ee;display:block;margin-top:.4rem">My account</a>
                <form method="POST" action="{{ route('pickrush.logout') }}" data-confirm-logout>
                    @csrf
                    <button type="submit" class="pr-btn pr-btn-ghost">Sign out</button>
                </form>
            </div>
        </aside>
    @endif
    <main class="pr-main">
        @if(session('success'))<div class="pr-alert pr-alert-ok" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="pr-alert pr-alert-bad" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>

<dialog class="pr-dialog" id="logoutDialog" aria-labelledby="logoutDialogTitle">
    <form method="dialog" class="pr-dialog-panel">
        <span class="pr-dialog-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
        </span>
        <h2 id="logoutDialogTitle">Sign out of Pickrush?</h2>
        <p>You will need your email and password to sign in again.</p>
        <div class="pr-dialog-actions">
            <button type="submit" value="cancel" class="pr-btn pr-btn-outline" data-dialog-cancel>Stay signed in</button>
            <button type="submit" value="confirm" class="pr-btn pr-btn-primary">Sign out</button>
        </div>
    </form>
</dialog>

</body>
</html>
