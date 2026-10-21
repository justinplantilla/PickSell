<nav class="pr-tabs" aria-label="Period">
    @foreach($periods as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['period' => $key, 'page' => null]) }}" @class(['is-active' => $period === $key])>{{ $label }}</a>
    @endforeach
</nav>
