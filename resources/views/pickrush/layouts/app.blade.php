<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Dashboard') | Pickrush Logistics</title>
    @vite('resources/css/pickrush/app.css')
</head>
<body>
@php($staff = \App\Pickrush\Support\Actor::staff())
<div class="pr-shell">
    <aside class="pr-sidebar">
        <a href="{{ route('pickrush.dashboard') }}" class="pr-brand"><span class="pr-brand-mark" aria-hidden="true">PR</span> Pickrush</a>
        <nav class="pr-nav" aria-label="Main">
            @foreach(\App\Pickrush\Support\Navigation::for($staff) as $item)
                <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <div class="pr-who">
            <strong>{{ $staff->full_name }}</strong>
            {{ $staff->role->label() }}@if($staff->hub) · {{ $staff->hub->name }}@else · HQ @endif
            <a href="{{ route('pickrush.account') }}" style="color:#dfe4ee;display:block;margin-top:.4rem">My account</a>
            <form method="POST" action="{{ route('pickrush.logout') }}">
                @csrf
                <button type="submit" class="pr-btn pr-btn-ghost">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="pr-main">
        @if(session('success'))<div class="pr-alert pr-alert-ok" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="pr-alert pr-alert-bad" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
