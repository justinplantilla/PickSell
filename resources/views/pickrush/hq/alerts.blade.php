@extends('pickrush.layouts.app')
@section('title', 'Alerts')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Alerts</h1>
        <p>What needs attention across the network, most urgent first.</p>
    </div>
</div>

@forelse($alerts as $alert)
    <section @class(['pr-alert', 'pr-section', 'pr-alert-bad' => $alert['level'] === 'bad', 'pr-alert-warn' => $alert['level'] === 'warn', 'pr-alert-ok' => $alert['level'] === 'info'])>
        <strong>{{ $alert['title'] }} ({{ $alert['items']->count() }})</strong> · {{ $alert['detail'] }}
        <ul class="pr-alert-list">
            @foreach($alert['items']->take(20) as $item)
                <li><span>@if($item['url'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif</span><span class="pr-muted">{{ $item['meta'] }}</span></li>
            @endforeach
        </ul>
        @if($alert['items']->count() > 20)<div class="pr-muted">…and {{ $alert['items']->count() - 20 }} more.</div>@endif
    </section>
@empty
    <div class="pr-alert pr-alert-ok">Nothing needs attention right now.</div>
@endforelse
@endsection
