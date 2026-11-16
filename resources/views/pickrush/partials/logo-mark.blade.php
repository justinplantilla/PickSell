{{--
    PickSell Logistics logo mark, drawn from public/images/psl-logo.svg (transparent background).
    tone: dark / light keep the brand orange; coral (on the coral brand surface) turns it white.
    Optional x / y place it inside a parent SVG.
--}}
@php
    $tone = $tone ?? 'dark';
    $size = $size ?? 40;
    $pslPaths = preg_replace('/^.*?<svg[^>]*>|<\/svg>\s*$/s', '', file_get_contents(public_path('images/psl-logo.svg')));
    $paths = $tone === 'coral' ? str_replace('#E96C36', '#ffffff', $pslPaths) : $pslPaths;
@endphp
<svg @isset($class) class="{{ $class }}" @endisset @isset($x) x="{{ $x }}" y="{{ $y }}" @endisset viewBox="70 78 340 340" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false">{!! $paths !!}</svg>
