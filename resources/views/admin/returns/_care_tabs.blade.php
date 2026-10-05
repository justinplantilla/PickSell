<nav class="oversight-tabs" aria-label="Customer care">
    @foreach([
        ['admin.complaints', 'Complaints', \App\Auth\Permission::COMPLAINTS_VIEW],
        ['admin.disputes', 'Return disputes', \App\Auth\Permission::RETURNS_VIEW],
    ] as [$routeName, $label, $permission])
        @can($permission)
            <a href="{{ route($routeName) }}" class="oversight-tab {{ request()->routeIs($routeName) ? 'is-active' : '' }}" @if(request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
        @endcan
    @endforeach
</nav>
