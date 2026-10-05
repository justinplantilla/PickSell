@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-dashboard.css'])
@endsection
@section('title', 'Dashboard')

@section('content')
@if($kpis)
<section class="command-kpis" aria-label="Key indicators">
    @foreach($kpis as $kpi)
        <a href="{{ $kpi['url'] }}" class="stat-card command-kpi {{ ($kpi['alert'] ?? false) ? 'is-alert' : '' }}" data-kpi="{{ $kpi['key'] }}">
            <span class="stat-card-label command-kpi-label">{{ $kpi['label'] }}</span>
            <span class="stat-card-num oversight-number">{{ number_format($kpi['value']) }}</span>
            <span class="command-kpi-detail">
                @if($kpi['alert'] ?? false)<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>@endif
                {{ $kpi['detail'] }}
            </span>
        </a>
    @endforeach
</section>
@endif

@if($lifecycle)
<section class="card" aria-labelledby="lifecycle-heading">
    <div class="card-header">
        <span class="card-title" id="lifecycle-heading">Marketplace lifecycle</span>
        <span class="oversight-description">Orders currently at each stage</span>
    </div>
    <ol class="command-lifecycle">
        @foreach($lifecycle as $stage)
            <li>
                <a href="{{ $stage['url'] }}" class="command-stage">
                    <strong class="oversight-number">{{ number_format($stage['count']) }}</strong>
                    <span>{{ $stage['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</section>
@endif

@if($pendingActions || $exceptions)
<div class="command-panels">
    @if($pendingActions)
    <section class="card" aria-labelledby="pending-heading">
        <div class="card-header"><span class="card-title" id="pending-heading">Pending actions</span></div>
        <ul class="command-list">
            @foreach($pendingActions as $action)
                <li class="command-row">
                    <span class="command-count oversight-number {{ $action['count'] ? '' : 'is-zero' }}">{{ number_format($action['count']) }}</span>
                    <span class="command-row-copy"><strong>{{ $action['label'] }}</strong></span>
                    @if($action['count'])
                        <a href="{{ $action['url'] }}" class="btn btn-coral btn-sm">{{ $action['cta'] }}</a>
                    @else
                        <span class="command-done">Clear</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    @if($exceptions)
    <section class="card" id="exceptions" aria-labelledby="exceptions-heading">
        <div class="card-header"><span class="card-title" id="exceptions-heading">Operational exceptions</span></div>
        <ul class="command-list">
            @foreach($exceptions as $exception)
                <li class="command-row {{ $exception['count'] ? 'is-exception' : '' }}">
                    <span class="command-count oversight-number {{ $exception['count'] ? '' : 'is-zero' }}">{{ number_format($exception['count']) }}</span>
                    <span class="command-row-copy">
                        <strong>
                            @if($exception['count'])<svg class="command-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>@endif
                            {{ $exception['label'] }}
                        </strong>
                        <span class="oversight-meta">{{ $exception['detail'] }}</span>
                    </span>
                    @if($exception['count'])
                        <a href="{{ $exception['url'] }}" class="btn btn-outline btn-sm">View</a>
                    @else
                        <span class="command-done">None</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @endif
</div>
@endif

@if($finance)
<section class="card" aria-labelledby="finance-heading">
    <div class="card-header">
        <span class="card-title" id="finance-heading">Finance snapshot</span>
        <span class="oversight-description">Completed orders · {{ rtrim(rtrim(number_format($finance['month']['commission_rate'], 2), '0'), '.') }}% commission</span>
    </div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th scope="col">Period</th><th scope="col">Gross sales</th><th scope="col">Platform commission</th><th scope="col">Net to sellers</th><th scope="col">Orders</th></tr></thead>
            <tbody>
                @foreach(['today' => 'Today', 'month' => 'This month'] as $key => $label)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        <td class="oversight-number">₱{{ number_format($finance[$key]['gross_sales'], 2) }}</td>
                        <td class="oversight-number">₱{{ number_format($finance[$key]['commission'], 2) }}</td>
                        <td class="oversight-number">₱{{ number_format($finance[$key]['net_to_sellers'], 2) }}</td>
                        <td class="oversight-number">{{ number_format($finance[$key]['completed_orders']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="command-links">
        @can(\App\Auth\Permission::REPORTS_VIEW)<a href="{{ route('admin.reports') }}">Financial reports</a>@endcan
        @can(\App\Auth\Permission::COMMISSION_VIEW)<a href="{{ route('admin.commission') }}">Commission</a>@endcan
    </div>
</section>
@endif

@if($pendingApplications->isNotEmpty())
<section class="card" aria-labelledby="applications-heading">
    <div class="card-header">
        <span class="card-title" id="applications-heading">Oldest pending applications</span>
        <a href="{{ route('admin.registrations', ['status' => 'pending']) }}" class="btn btn-outline btn-sm">View all</a>
    </div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Waiting</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($pendingApplications as $application)
                <tr>
                    <td>{{ $application->full_name }}</td>
                    <td><span class="badge badge-{{ $application->role }}">{{ ucfirst($application->role) }}</span></td>
                    <td>{{ $application->email }}</td>
                    <td>{{ $application->created_at->diffForHumans(null, true) }}</td>
                    <td><a href="{{ route('admin.registrations.show', $application) }}" class="btn btn-coral btn-sm">Review</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

@if($users)
<div class="oversight-grid-2">
    <section class="card" aria-labelledby="distribution-heading">
        <div class="card-header">
            <span class="card-title" id="distribution-heading">Active accounts</span>
            @if($users['suspended'])<a href="{{ route('admin.users', ['status' => 'suspended']) }}" class="oversight-description">{{ number_format($users['suspended']) }} suspended</a>@endif
        </div>
        @php $distributionMax = max(1, max($users['distribution'])); @endphp
        <ul class="oversight-bars">
            @foreach($users['distribution'] as $label => $count)
                <li class="oversight-bar-row" title="{{ $label }}: {{ number_format($count) }}">
                    <span class="oversight-bar-label">{{ $label }}</span>
                    <span class="oversight-bar-track" aria-hidden="true"><span class="oversight-bar-fill" style="width: {{ $count ? max(1, round($count / $distributionMax * 100, 1)) : 0 }}%; {{ $count ? '' : 'min-width:0' }}"></span></span>
                    <span class="oversight-bar-value">{{ number_format($count) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
    <section class="card" aria-labelledby="trend-heading">
        <div class="card-header"><span class="card-title" id="trend-heading">New registrations, last 6 months</span></div>
        <div class="card-body">
            <div id="trendChart" role="img" aria-label="New registrations per month: {{ collect($users['trend']['labels'])->zip($users['trend']['values'])->map(fn ($pair) => $pair[0] . ' ' . $pair[1])->join(', ') }}"></div>
        </div>
    </section>
</div>
<div data-admin-trend data-labels='@json($users['trend']['labels'])' data-values='@json($users['trend']['values'])' hidden></div>
@endif
@endsection

@section('scripts')
@vite('resources/js/views/admin-dashboard.js')
@endsection
