{{-- Pickrush hub-by-hub tracking. Expects $tracking from PicksellBridge::tracking() (null when not shipped yet). --}}
@if($tracking)
    @once
        @vite('resources/css/components/pickrush-tracking.css')
    @endonce
    <details class="pickrush-tracking" {{ $open ?? false ? 'open' : '' }}>
        <summary>
            <span><strong>Pickrush</strong> · {{ $tracking['tracking_no'] }}</span>
            <span class="pickrush-tracking-latest">{{ $tracking['events'][0]['headline'] ?? $tracking['status'] }}</span>
        </summary>
        <ol class="pickrush-tracking-list">
            @foreach($tracking['events'] as $event)
                <li @class(['is-current' => $loop->first])>
                    <span class="pickrush-tracking-dot" aria-hidden="true"></span>
                    <span class="pickrush-tracking-text">{{ $event['headline'] }}</span>
                    <time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('M d, Y h:i A') }}</time>
                </li>
            @endforeach
        </ol>
    </details>
@endif
