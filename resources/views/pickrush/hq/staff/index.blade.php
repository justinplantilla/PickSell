@extends('pickrush.layouts.app')
@section('title', 'Staff & roles')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Staff &amp; roles</h1>
        <p>Portal accounts for HQ, province hubs and stations. Riders are managed by their station.</p>
    </div>
    <a href="{{ route('pickrush.hq.staff.create') }}" class="pr-btn pr-btn-primary">Add staff</a>
</div>

<form method="GET" class="pr-filters" role="search">
    <input type="search" name="q" value="{{ $filters['q'] }}" class="pr-input" placeholder="Name, email or hub" aria-label="Search staff">
    <select name="role" class="pr-select" aria-label="Role">
        <option value="">All roles</option>
        @foreach($roles as $role)<option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>@endforeach
    </select>
    <label class="pr-check" style="margin:0"><input type="checkbox" name="samples" value="1" @checked($filters['samples'])> Include sample accounts</label>
    <button type="submit" class="pr-btn pr-btn-outline">Filter</button>
</form>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Name</th><th>Role</th><th>Hub</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
        <tbody>
        @forelse($staff as $member)
            <tr>
                <td><strong>{{ $member->full_name }}</strong><div class="pr-muted">{{ $member->email }}</div></td>
                <td>{{ $member->role->label() }}</td>
                <td>{{ $member->hub?->name ?? 'HQ' }}</td>
                <td>
                    <span class="pr-badge {{ $member->is_active ? 'pr-badge-ok' : '' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span>
                    @if($member->is_sample)<span class="pr-badge">Sample</span>@endif
                </td>
                <td>{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td><a href="{{ route('pickrush.hq.staff.edit', $member) }}" class="pr-btn pr-btn-outline pr-btn-sm">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="pr-muted">No staff match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pr-pagination">{{ $staff->links('pagination::tailwind') }}</div>

<section class="pr-card" style="margin-top:1.25rem">
    <h2>Permission matrix</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Permission</th><th>Super admin</th><th>Hub manager</th><th>Dispatcher</th><th>Sorter</th><th>Finance</th><th>Support</th><th>Rider</th></tr></thead>
            <tbody>
            @foreach([
                ['View all hubs', 'Yes', 'No', 'No', 'No', 'Read', 'Read', 'No'],
                ['Create or edit hubs, staff, rates and policies', 'Yes', 'No', 'No', 'No', 'No', 'No', 'No'],
                ['Create and approve riders', 'Yes', 'Own station', 'Verify only', 'No', 'No', 'No', 'No'],
                ['Assign parcels to riders', 'No', 'Own station', 'Own station', 'No', 'No', 'No', 'No'],
                ['Scan, sort and run trips', 'No', 'Own hub', 'Own station', 'Own province hub', 'No', 'No', 'Own tasks'],
                ['Record rider cash, remit to HQ', 'No', 'Own station', 'No', 'No', 'No', 'No', 'No'],
                ['Confirm station remittances', 'Yes', 'No', 'No', 'No', 'Yes', 'No', 'No'],
                ['Fix addresses', 'Yes', 'No', 'No', 'No', 'No', 'Yes', 'No'],
                ['Audit log', 'Yes', 'No', 'No', 'No', 'Yes', 'No', 'No'],
            ] as $row)
                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
