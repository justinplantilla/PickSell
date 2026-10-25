{{--
    Pickrush logo mark: an R built like the PickSell P (coral bowl, faceted bone stem, slashes), its leg striding forward.
    tone: dark (on charcoal/navy), coral (on the coral brand surface), light (on white cards).
    Optional x / y place it inside a parent SVG.
--}}
@php
    $tone = $tone ?? 'dark';
    $size = $size ?? 40;
    $colors = [
        'dark' => ['stem' => '#f6f3ee', 'facet' => '#d9d3c9', 'slash' => '#f6f3ee'],
        'coral' => ['stem' => '#f6f3ee', 'facet' => '#c9c2b6', 'slash' => '#f6f3ee'],
        'light' => ['stem' => '#1f2328', 'facet' => '#3a434f', 'slash' => '#1f2328'],
    ][$tone];
    $bowl = 'prk-bowl-'.$tone;
@endphp
<svg @isset($class) class="{{ $class }}" @endisset @isset($x) x="{{ $x }}" y="{{ $y }}" @endisset viewBox="0 0 120 120" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="{{ $bowl }}" x1="0" y1="0" x2="1" y2="1">
            @if($tone === 'coral')
                <stop offset="0" stop-color="#4a5562"/><stop offset="1" stop-color="#1f2328"/>
            @else
                <stop offset="0" stop-color="#ff8a6e"/><stop offset=".55" stop-color="#E8472A"/><stop offset="1" stop-color="#c93a20"/>
            @endif
        </linearGradient>
    </defs>
    <path d="M18 22 h12 l9 8 h-12 z M18 35 h12 l9 8 h-12 z" fill="{{ $colors['slash'] }}"/>
    <path d="M40 16 H74 A26 26 0 0 1 74 68 H40 Z M56 31 H72 A11 11 0 0 1 72 53 H56 Z" fill="url(#{{ $bowl }})" fill-rule="evenodd"/>
    <path d="M62 62 H78 L100 106 H84 Z" fill="url(#{{ $bowl }})"/>
    <path d="M40 68 H56 V106 L40 98 Z" fill="{{ $colors['stem'] }}"/>
    <path d="M40 68 H56 L40 80 Z" fill="{{ $colors['facet'] }}"/>
    <path d="M40 86 L56 90" stroke="{{ $colors['facet'] }}" stroke-width="2"/>
</svg>
