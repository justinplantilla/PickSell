@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-dashboard.css'])
@endsection
@section('title', 'Dashboard')

@section('content')
<div class="admin-dashboard-heading">
    <div>
        <h1>Admin Dashboard</h1>
        <p>Platform activity and important updates at a glance.</p>
    </div>
</div>

@if($kpis)
<section class="dashboard-kpis" aria-label="Key indicators">
    @foreach($kpis as $kpi)
        <a href="{{ $kpi['url'] }}" class="stat-card command-kpi dashboard-kpi dashboard-kpi--{{ $kpi['key'] }} {{ ($kpi['alert'] ?? false) ? 'is-alert' : '' }}" data-kpi="{{ $kpi['key'] }}">
            <span class="dashboard-kpi-icon" aria-hidden="true">
                @if($kpi['key'] === 'orders_today')
                    <svg viewBox="0 0 24 24"><path d="M7 3h10l1 3h3v15H3V6h3l1-3Zm1.5 3h7l-.5-1.5H9L8.5 6ZM6 9v9h12V9H6Zm2 2h8v2H8v-2Zm0 4h5v2H8v-2Z"/></svg>
                @elseif($kpi['key'] === 'pending_approvals')
                    <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 5v5.4l3.2 1.9-1 1.7-4.2-2.6V7h2Z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><path d="M12 2 2 20h20L12 2Zm1 13h-2V9h2v6Zm0 3h-2v-2h2v2Z"/></svg>
                @endif
            </span>
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

<div class="dashboard-chart-grid">
    @if($lifecycle)
    <section class="card dashboard-panel" aria-labelledby="lifecycle-heading">
        <div class="card-header dashboard-panel-header">
            <div><span class="card-title" id="lifecycle-heading">Marketplace lifecycle</span><span class="dashboard-panel-subtitle">Current orders across each stage</span></div>
            <a href="{{ route('admin.orders') }}" class="dashboard-link">View all</a>
        </div>
        <div class="dashboard-donut-layout">
            <div id="lifecycleChart" class="dashboard-donut" role="img" aria-label="Orders by lifecycle: {{ collect($lifecycle)->map(fn ($stage) => $stage['label'].' '.$stage['count'])->join(', ') }}"></div>
            <ul class="dashboard-legend">
                @foreach($lifecycle as $index => $stage)
                    <li>
                        <a href="{{ $stage['url'] }}">
                            <span class="dashboard-legend-dot dashboard-legend-dot--{{ $index }}" aria-hidden="true"></span>
                            <strong class="oversight-number">{{ number_format($stage['count']) }}</strong>
                            <span>{{ $stage['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    @endif

    @if($users)
    <section class="card dashboard-panel" aria-labelledby="trend-heading">
        <div class="card-header dashboard-panel-header">
            <div><span class="card-title" id="trend-heading">New registrations</span><span class="dashboard-panel-subtitle">Last 6 months</span></div>
            <a href="{{ route('admin.registrations') }}" class="dashboard-link">View all</a>
        </div>
        <div class="card-body dashboard-chart-body">
            <div id="trendChart" class="dashboard-trend-chart" role="img" aria-label="New registrations per month: {{ collect($users['trend']['labels'])->zip($users['trend']['values'])->map(fn ($pair) => $pair[0].' '.$pair[1])->join(', ') }}"></div>
        </div>
    </section>
    @endif
</div>

<div class="dashboard-data-grid">
    @if($users)
    <section class="card dashboard-panel" aria-labelledby="accounts-heading">
        <div class="card-header dashboard-panel-header">
            <div>
                <span class="card-title" id="accounts-heading">Active accounts</span>
                <span class="dashboard-panel-subtitle">By account type</span>
            </div>
            @if($users['suspended'])
                <a href="{{ route('admin.users', ['status' => 'suspended']) }}" class="dashboard-link">{{ number_format($users['suspended']) }} suspended</a>
            @endif
        </div>
        <div class="dashboard-donut-layout dashboard-accounts-layout">
            <div id="accountsChart" class="dashboard-donut dashboard-accounts-chart" role="img" aria-label="Active accounts: {{ collect($users['distribution'])->map(fn ($count, $label) => $label.' '.$count)->join(', ') }}"></div>
            <ul class="dashboard-legend dashboard-accounts-legend">
                @foreach($users['distribution'] as $label => $count)
                    <li>
                        <span class="dashboard-legend-dot dashboard-account-dot--{{ $loop->index }}" aria-hidden="true"></span>
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($count) }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    @endif

    @if($finance)
    <section class="card dashboard-panel" aria-labelledby="finance-heading">
        <div class="card-header dashboard-panel-header">
            <div>
                <span class="card-title" id="finance-heading">Finance snapshot</span>
                <span class="dashboard-panel-subtitle">Completed orders · {{ rtrim(rtrim(number_format($finance['month']['commission_rate'], 2), '0'), '.') }}% commission</span>
            </div>
            <a href="{{ route('admin.reports') }}" class="dashboard-link">Reports</a>
        </div>
        <div class="oversight-table-wrap dashboard-finance-table">
            <table>
                <thead><tr><th scope="col">Period</th><th scope="col">Gross sales</th><th scope="col">Commission</th><th scope="col">Net to sellers</th><th scope="col">Orders</th></tr></thead>
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
</div>

<div class="dashboard-bottom-grid">
    @if($pendingApplications->isNotEmpty())
    <section class="card dashboard-panel dashboard-applications" aria-labelledby="applications-heading">
        <div class="card-header dashboard-panel-header">
            <div><span class="card-title" id="applications-heading">Oldest pending applications</span><span class="dashboard-panel-subtitle">Applications waiting for review</span></div>
            <a href="{{ route('admin.registrations', ['status' => 'pending']) }}" class="dashboard-link">View all</a>
        </div>
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Waiting</th><th>Action</th></tr></thead>
                <tbody>
                @foreach($pendingApplications as $application)
                    <tr>
                        <td><strong>{{ $application->full_name }}</strong></td>
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

    @if($pendingActions || $exceptions)
    <div class="dashboard-alerts" aria-label="System alerts">
        @if($pendingActions)
        <section class="card dashboard-panel dashboard-pending-actions">
            <div class="card-header dashboard-panel-header"><div><span class="card-title">Pending actions</span><span class="dashboard-panel-subtitle">Needs your attention</span></div></div>
            <ul class="dashboard-action-list">
                @foreach($pendingActions as $action)
                    <li class="dashboard-action-card">
                        <span class="dashboard-action-count oversight-number {{ $action['count'] ? '' : 'is-zero' }}">{{ number_format($action['count']) }}</span>
                        <strong class="dashboard-action-label">{{ $action['label'] }}</strong>
                        @if($action['count'])
                            <a href="{{ $action['url'] }}" class="dashboard-action-link">{{ $action['cta'] }}</a>
                        @else
                            <span class="dashboard-action-clear">Clear</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
        @endif

        @if($exceptions)
        <section class="dashboard-exceptions" id="exceptions" aria-labelledby="exceptions-heading">
            <div class="dashboard-exceptions-heading">
                <span class="card-title" id="exceptions-heading">Operational exceptions</span>
                <span class="dashboard-panel-subtitle">Orders that may need follow-up</span>
            </div>
            <ul class="dashboard-exception-grid">
                @foreach($exceptions as $exception)
                    <li class="dashboard-exception-card {{ $exception['count'] ? 'is-exception' : '' }}">
                        <div class="dashboard-exception-title">
                            <span class="dashboard-exception-count oversight-number {{ $exception['count'] ? '' : 'is-zero' }}">{{ number_format($exception['count']) }}</span>
                            <strong>
                                @if($exception['count'])<svg class="command-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>@endif
                                {{ $exception['label'] }}
                            </strong>
                        </div>
                        <span class="dashboard-exception-detail">{{ $exception['detail'] }}</span>
                        @if($exception['count'])
                            <a href="{{ $exception['url'] }}" class="btn btn-outline btn-sm dashboard-exception-link">View</a>
                        @else
                            <span class="command-done dashboard-exception-link">None</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>
    @endif
</div>

@if($users)
<div data-admin-trend data-labels='@json($users['trend']['labels'])' data-values='@json($users['trend']['values'])' hidden></div>
<div data-admin-lifecycle data-labels='@json(collect($lifecycle ?? [])->pluck("label"))' data-values='@json(collect($lifecycle ?? [])->pluck("count"))' hidden></div>
<div data-admin-accounts data-labels='@json(array_keys($users['distribution']))' data-values='@json(array_values($users['distribution']))' hidden></div>
@elseif($lifecycle)
<div data-admin-lifecycle data-labels='@json(collect($lifecycle)->pluck("label"))' data-values='@json(collect($lifecycle)->pluck("count"))' hidden></div>
@endif
@endsection

@section('scripts')
@vite('resources/js/views/admin-dashboard.js')
@endsection
