@extends('pickrush.layouts.app')
@section('title', 'Audit log')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Audit log</h1>
        <p>Every change in Pickrush with who made it, from which hub, and the values before and after. Entries cannot be edited.</p>
    </div>
</div>

<form method="GET" class="pr-filters">
    <input name="action" class="pr-input" value="{{ $filters['action'] }}" placeholder="Action, e.g. Rider.updated" aria-label="Action">
    <select name="subject" class="pr-select" aria-label="Record type">
        <option value="">Any record</option>
        @foreach($subjects as $subject)<option value="{{ $subject }}" @selected($filters['subject'] === $subject)>{{ $subject }}</option>@endforeach
    </select>
    <input name="subject_id" type="number" class="pr-input" value="{{ $filters['subject_id'] }}" placeholder="Record id" aria-label="Record id" style="width:110px">
    <input name="actor" type="number" class="pr-input" value="{{ $filters['actor'] }}" placeholder="Staff id" aria-label="Staff id" style="width:110px">
    <input name="from" type="date" class="pr-input" value="{{ $filters['from']?->toDateString() }}" aria-label="From">
    <input name="to" type="date" class="pr-input" value="{{ $filters['to']?->toDateString() }}" aria-label="To">
    <button type="submit" class="pr-btn pr-btn-outline">Filter</button>
</form>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Change</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('M d, Y h:i:s A') }}</td>
                <td>{{ $log->actor_type === 'staff' ? ($actors[$log->actor_id] ?? 'Staff #'.$log->actor_id) : ucfirst($log->actor_type).($log->actor_id ? ' #'.$log->actor_id : '') }}@if($log->actor_hub_id)<div class="pr-muted">hub #{{ $log->actor_hub_id }}</div>@endif</td>
                <td><code>{{ $log->action }}</code></td>
                <td>{{ $log->subject_type ? $log->subject_type.' #'.$log->subject_id : '—' }}</td>
                <td style="max-width:420px">
                    @foreach(collect($log->after ?? [])->except(['created_at', 'updated_at']) as $field => $value)
                        <div><span class="pr-muted">{{ $field }}:</span>
                            @if(isset($log->before[$field]))<s>{{ is_scalar($log->before[$field]) ? $log->before[$field] : json_encode($log->before[$field]) }}</s> → @endif
                            {{ is_scalar($value) || $value === null ? \Illuminate\Support\Str::limit((string) $value, 80) : json_encode($value) }}</div>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">No entries match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pr-pagination">{{ $logs->links('pagination::tailwind') }}</div>
@endsection
