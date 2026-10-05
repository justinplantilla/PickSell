@extends('admin.layout')
@section('title', 'Audit Logs')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Audit logs</span>
            <p class="oversight-description">Append-only record of admin operations and refused admin requests.</p>
        </div>
        @can(\App\Auth\Permission::AUDIT_EXPORT)
            <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-outline btn-sm">Export CSV</a>
        @endcan
    </div>
    <form method="GET" class="oversight-filters oversight-note" aria-label="Filter audit logs">
        <select name="action" class="filter-select" aria-label="Action">
            <option value="">All actions</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ $action }}</option>
            @endforeach
        </select>
        <label>From <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></label>
        <label>To <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
        <label><input type="checkbox" name="denied" value="1" {{ $filters['denied'] ? 'checked' : '' }}> Denied requests only</label>
        @if($filters['actor'])<input type="hidden" name="actor" value="{{ $filters['actor'] }}">@endif
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        @if(array_filter($filters))<a href="{{ route('admin.audit') }}" class="btn btn-outline btn-sm">Clear</a>@endif
    </form>

    @if($logs->isEmpty())
        <div class="oversight-empty"><strong>No audit entries</strong>Admin operations will be recorded here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>Details</th></tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('M d, Y h:i:s A') }}</time><span class="oversight-meta">{{ $log->ip_address }}</span></td>
                        <td>
                            @if($log->actor_id)
                                <a href="{{ route('admin.audit', array_merge(request()->query(), ['actor' => $log->actor_id])) }}">{{ $log->actor?->full_name ?? 'User #' . $log->actor_id }}</a>
                            @else
                                System
                            @endif
                            <span class="oversight-meta">{{ $log->actor_role }}</span>
                        </td>
                        <td>
                            <span class="audit-action">{{ $log->action }}</span>
                            @if($log->action === 'authorization.denied')<span class="badge badge-cancelled">Denied</span>@endif
                            @if($log->permission)<span class="oversight-meta">{{ $log->permission }}</span>@endif
                        </td>
                        <td>@if($log->subject_type){{ class_basename($log->subject_type) }} #{{ $log->subject_id }}@else — @endif</td>
                        <td>
                            <ul class="audit-changes">
                                @foreach($log->changes ?? [] as $field => $change)
                                    <li><code>{{ $field }}</code>: {{ json_encode($change['from'] ?? null) }} → {{ json_encode($change['to'] ?? null) }}</li>
                                @endforeach
                                @foreach($log->metadata ?? [] as $key => $value)
                                    @continue($value === null || $value === '' || $value === [])
                                    <li><code>{{ $key }}</code>: {{ is_scalar($value) ? \Illuminate\Support\Str::limit((string) $value, 140) : \Illuminate\Support\Str::limit(json_encode($value), 140) }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="dashboard-pagination">{{ $logs->links() }}</div>@endif
    @endif
</div>
@endsection
