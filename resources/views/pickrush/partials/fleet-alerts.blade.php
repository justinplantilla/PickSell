{{-- Overdue trips and papers to renew for this hub (App\Pickrush\Support\FleetAlerts). --}}
@foreach(\App\Pickrush\Support\FleetAlerts::forHub($hub) as $alert)
    <section @class(['pr-alert', 'pr-section', 'pr-alert-bad' => $alert['level'] === 'bad', 'pr-alert-warn' => $alert['level'] === 'warn']) role="status">
        <strong>{{ $alert['title'] }} ({{ $alert['items']->count() }})</strong>
        <ul class="pr-alert-list">
            @foreach($alert['items']->take(10) as $item)
                <li><span><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></span><span class="pr-muted">{{ $item['meta'] }}</span></li>
            @endforeach
        </ul>
    </section>
@endforeach
