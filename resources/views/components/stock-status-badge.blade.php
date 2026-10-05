@props(['product' => null, 'status' => null])

@php
    $status ??= $product?->stock_status ?? 'in_stock';

    $label = match ($status) {
        'archived'     => 'Archived',
        'out_of_stock' => 'Out of Stock',
        'low_stock'    => 'Low Stock',
        default        => 'In Stock',
    };
@endphp

<span {{ $attributes->class(['stock-badge', 'stock-badge--' . str_replace('_', '-', $status)]) }}>
    <svg class="stock-badge-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($status)
            @case('archived')
                <path d="M3 4h18v4H3zM5 8v12h14V8M10 12h4"/>
                @break
            @case('out_of_stock')
                <path d="M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86zM15 9l-6 6M9 9l6 6"/>
                @break
            @case('low_stock')
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/>
                @break
            @default
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>
        @endswitch
    </svg>
    <span class="stock-badge-label">{{ $label }}</span>
</span>
