@extends('admin.layout')
@section('styles')
@vite('resources/css/components/review-moderation.css')
@endsection
@section('title', 'Seller Performance')

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Seller performance · last {{ \App\Services\SellerPerformance::WINDOW_DAYS }} days</span></div>
    <div class="card-body">
        <p class="moderation-muted">Preferred Seller: service rating {{ number_format(\App\Services\SellerPerformance::MIN_RATING, 1) }}+, {{ \App\Services\SellerPerformance::MIN_COMPLETED }}+ completed orders and at most {{ (int) \App\Services\SellerPerformance::MAX_CANCEL_RATE }}% cancelled. The badge shows on their store and products. Figures refresh hourly.</p>
        <nav class="moderation-tabs" aria-label="Seller filters">
            @foreach(['all' => 'All sellers', 'warnings' => 'Need attention', 'preferred' => 'Preferred'] as $key => $label)
            <a href="{{ route('admin.seller-performance', ['filter' => $key]) }}" class="{{ $filter === $key ? 'is-active' : '' }}" @if($filter === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Seller</th><th>Service rating</th><th>Completed</th><th>Cancelled</th><th>Hands over in</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                <tr>
                    <td><a href="{{ route('admin.users.show', $row['seller']) }}">{{ $row['seller']->business_name ?? $row['seller']->full_name }}</a></td>
                    <td>{{ $row['rating'] !== null ? '★ '.number_format($row['rating'], 1).' ('.$row['ratings'].')' : '—' }}</td>
                    <td>{{ $row['completed'] }} of {{ $row['orders'] }}</td>
                    <td>{{ $row['cancel_rate'] !== null ? $row['cancel_rate'].'%' : '—' }}</td>
                    <td>{{ $row['ship_hours'] !== null ? ($row['ship_hours'] < 24 ? $row['ship_hours'].' h' : round($row['ship_hours'] / 24, 1).' days') : '—' }}</td>
                    <td>
                        @if($row['preferred'])<span class="badge badge-approved">Preferred</span>@endif
                        @foreach($row['warnings'] as $warning)<span class="moderation-flag" style="display:block">⚠ {{ $warning }}</span>@endforeach
                        @if(! $row['preferred'] && ! $row['warnings'])<span class="moderation-muted">OK</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="moderation-muted">No sellers here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
