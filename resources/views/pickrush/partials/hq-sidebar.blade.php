{{--
    HQ sidebar: the same links Navigation::for() returns, grouped and given icons. Any link not listed in $groups
    lands in "More", so a new menu item can never disappear from the sidebar.
--}}
@php
    $groups = [
        'Overview' => ['pickrush.hq.dashboard', 'pickrush.hq.alerts', 'pickrush.hq.reports'],
        'Operations' => ['pickrush.parcels.index', 'pickrush.hq.address-fix', 'pickrush.exceptions.index', 'pickrush.hubs.index'],
        'Money & admin' => ['pickrush.hq.finance', 'pickrush.hq.staff.index', 'pickrush.hq.settings', 'pickrush.hq.audit'],
    ];
    $icons = [
        'pickrush.hq.dashboard' => 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
        'pickrush.hq.alerts' => 'M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 0 0 4 0',
        'pickrush.hq.reports' => 'M3 3v18h18M7 15l4-4 3 3 5-6',
        'pickrush.parcels.index' => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8',
        'pickrush.hq.address-fix' => 'M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11zM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
        'pickrush.exceptions.index' => 'M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
        'pickrush.hubs.index' => 'M3 21V9l9-6 9 6v12M9 21v-6h6v6',
        'pickrush.hq.finance' => 'M2 6h20v13H2zM12 10a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'pickrush.hq.staff.index' => 'M9 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM2 21a7 7 0 0 1 14 0M17 11l2 2 4-4',
        'pickrush.hq.settings' => 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
        'pickrush.hq.audit' => 'M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8zM14 3v5h5M9 13h6M9 17h4',
    ];
    $items = collect(\App\Pickrush\Support\Navigation::for($staff))->keyBy('route');
    $grouped = collect($groups)->map(fn ($routes) => $items->only($routes)->sortBy(fn ($item, $route) => array_search($route, $routes)))->filter->isNotEmpty();
    $rest = $items->except(collect($groups)->flatten()->all());
    if ($rest->isNotEmpty()) {
        $grouped['More'] = $rest;
    }
    $initials = collect(explode(' ', $staff->full_name))->filter()->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->implode('');
@endphp
<aside class="pr-sidebar">
    <a href="{{ route('pickrush.dashboard') }}" class="pr-brand">
        @include('pickrush.partials.logo-mark', ['class' => 'pr-brand-logo', 'size' => 34])
        <span class="pr-brand-text">Pickrush<small>HQ console</small></span>
    </a>
    <nav class="pr-nav" aria-label="Main">
        @foreach($grouped as $title => $links)
            <div class="pr-nav-group">{{ $title }}</div>
            @foreach($links as $route => $item)
                <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['active'])]) @if(request()->routeIs($item['active'])) aria-current="page" @endif>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$route] ?? 'M12 5v14M5 12h14' }}"/></svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
    <div class="pr-who">
        <span class="pr-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="pr-who-text">
            <strong>{{ $staff->full_name }}</strong>
            <span>{{ $staff->role->label() }}@if($staff->hub) · {{ $staff->hub->name }}@else · HQ @endif</span>
            <a href="{{ route('pickrush.account') }}">My account</a>
        </span>
        <form method="POST" action="{{ route('pickrush.logout') }}" data-confirm-logout>
            @csrf
            <button type="submit" class="pr-signout" aria-label="Sign out" title="Sign out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            </button>
        </form>
    </div>
</aside>
