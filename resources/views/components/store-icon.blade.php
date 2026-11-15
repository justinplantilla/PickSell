@props(['name'])
@php
    $paths = [
        'star' => 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9Z',
        'people' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'calendar' => 'M4 5h16v16H4ZM16 3v4M8 3v4M4 11h16M8 15h2M14 15h2',
        'box' => 'm12 3 9 5v9l-9 5-9-5V8Zm0 10 9-5M12 13 3 8M12 13v9M7.5 5.5l9 5',
        'check' => 'M9 12l2 2 4-4M12 3l8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6Z',
        'chat' => 'M4 4h16v12H9l-5 4ZM8 8h8M8 12h5',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'store-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['box'] }}"/></svg>
