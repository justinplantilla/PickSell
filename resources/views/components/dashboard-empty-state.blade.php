@props(['icon', 'title', 'description'])

<div {{ $attributes->class(['dashboard-empty-state']) }}>
    @if($icon !== 'none')
    <span class="dashboard-empty-icon" aria-hidden="true">
        @if($icon === 'orders')
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 7h15l7 7v27H15z"/><path d="M30 7v8h7M20 23h12M20 29h12M20 35h7"/><path d="M10 12v30h24"/></svg>
        @elseif($icon === 'catalog')
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m24 5 17 9v20l-17 9-17-9V14z"/><path d="m7 14 17 9 17-9M24 23v20M15 10l17 10"/></svg>
        @elseif($icon === 'analytics')
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 41h35M11 37V25h8v12M23 37V16h8v21M35 37V7h8v30"/><path d="m10 18 9-7 9 2 12-9"/></svg>
        @else
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="24" cy="24" r="18"/><path d="m15 24 6 6 12-13"/></svg>
        @endif
    </span>
    @endif
    <div>
        <strong>{{ $title }}</strong>
        <p>{{ $description }}</p>
    </div>
</div>