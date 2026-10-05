<nav class="oversight-tabs" aria-label="Operations">
    @foreach([
        'admin.logistics' => 'Logistics overview',
        'admin.logistics.sorting' => 'Sorting center',
        'admin.logistics.riders' => 'Rider assignment',
    ] as $routeName => $label)
        <a href="{{ route($routeName) }}" class="oversight-tab {{ request()->routeIs($routeName) ? 'is-active' : '' }}" @if(request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<p class="oversight-description oversight-note">Read-only supervision. Scanning, sorting and rider assignment are performed in the Logistics portal.</p>
