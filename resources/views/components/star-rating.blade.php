@props(['value' => 0, 'max' => 5])
{{-- Five stars shaded to the exact rating: 3.5 fills three and a half stars. --}}
@php
    $value = max(0, min($max, (float) $value));
    $fill = $max > 0 ? round($value / $max * 100, 2) : 0;
    $stars = str_repeat('★', $max);
@endphp
@once
<style>
    /* Doubled class keeps page styles like `.x span { color }` from recoloring the layers; no letter-spacing so the fill % lines up with the glyphs. */
    .star-rating-meter.star-rating-meter { position:relative; display:inline-block; line-height:1; letter-spacing:0; white-space:nowrap; vertical-align:middle; }
    .star-rating-meter .star-rating-meter-base { color:#d0d5dd; letter-spacing:0; }
    .star-rating-meter .star-rating-meter-fill { position:absolute; inset:0 auto 0 0; overflow:hidden; color:#f59e0b; letter-spacing:0; }
    body.dark .star-rating-meter .star-rating-meter-base { color:#3a3a3a; }
</style>
@endonce
<span {{ $attributes->merge(['class' => 'star-rating-meter']) }} role="img" aria-label="{{ rtrim(rtrim(number_format($value, 1), '0'), '.') }} out of {{ $max }} stars"><span class="star-rating-meter-base" aria-hidden="true">{{ $stars }}</span><span class="star-rating-meter-fill" style="width:{{ $fill }}%" aria-hidden="true">{{ $stars }}</span></span>
