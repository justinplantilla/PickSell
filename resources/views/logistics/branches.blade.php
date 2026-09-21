@extends('logistics.layout')
@section('title', 'Branches')
@section('content')
@vite('resources/css/views/logistics-branches.css')

<div class="branch-toolbar"><div><h1 class="blade-inline-1">Branches</h1><p class="blade-inline-2">Create a municipality branch and manage its delivery coverage.</p></div><div class="blade-inline-3"><form method="GET" class="blade-inline-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search branch, city, manager..." aria-label="Search branches"><button class="btn btn-outline" type="submit">Search</button></form><button class="btn btn-coral" type="button" data-open-branch-modal="add">+ Add Branch</button></div></div>

<div class="card">
    <div class="card-header"><span class="card-title">Configured Branches</span><span class="blade-inline-5">{{ $branches->count() }} total</span></div>
    <div class="blade-inline-6"><table><thead><tr><th>Branch</th><th>Municipality</th><th>Manager</th><th>Barangays</th><th>Riders</th><th>Action</th></tr></thead><tbody>
        @forelse($branches as $branch)
            <tr>
                <td><strong>{{ $branch->name }}</strong><div class="blade-inline-7">{{ ucfirst($branch->status) }}</div></td>
                <td>{{ $branch->municipality->name }}, {{ $branch->municipality->province }}</td>
                <td>{{ $branch->logistics->full_name ?? 'Unassigned' }}</td>
                <td>{{ $branch->riderAssignments->flatMap->barangays->map(fn ($assignment) => $assignment->barangay->name)->unique()->join(', ') ?: 'No barangays assigned' }}</td>
                <td>{{ $branch->riderAssignments->where('status', 'active')->map->rider->map->full_name->join(', ') ?: 'No riders assigned' }}</td>
                <td><button class="btn btn-outline" type="button" data-open-branch-modal="edit" data-action="{{ route('logistics.branches.update', $branch) }}" data-name="{{ $branch->name }}" data-province="{{ $branch->municipality->province }}" data-municipality="{{ $branch->municipality->name }}" data-address="{{ $branch->address }}" data-manager="{{ $branch->logistics_id }}" data-status="{{ $branch->status }}">Edit</button></td>
            </tr>
        @empty
            <tr><td colspan="6" class="blade-inline-8">No branches configured yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="branch-modal" id="branchModal" aria-hidden="true">
    <div class="branch-modal-card" role="dialog" aria-modal="true" aria-labelledby="branchModalTitle">
        <div class="branch-modal-head"><span class="branch-modal-title" id="branchModalTitle">Add Municipality Branch</span><button class="branch-modal-close" type="button" data-close-branch-modal aria-label="Close">&times;</button></div>
        <div class="branch-modal-body">
            <form method="POST" id="branchModalForm" action="{{ route('logistics.branches.store') }}">
                @csrf
                <input type="hidden" name="_method" id="branchModalMethod" value="POST">
                <div class="branch-form-grid">
                    <div><label class="branch-form-label">Branch Name *</label><input class="form-control blade-inline-9" name="name" id="branchName" placeholder="e.g. Santa Cruz Branch" required></div>
                    <div><label class="branch-form-label">Status *</label><select class="form-control blade-inline-10" name="status" id="branchStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div><label class="branch-form-label">Province *</label><select class="form-control blade-inline-11" name="province" id="branchProvince" required><option value="">-- Select Province --</option></select></div>
                    <div><label class="branch-form-label">Municipality / City *</label><select class="form-control blade-inline-12" name="municipality" id="branchMunicipality" required disabled><option value="">-- Select Municipality --</option></select></div>
                    <div><label class="branch-form-label">Branch Manager</label><select class="form-control blade-inline-13" name="logistics_id" id="branchManager"><option value="">-- Unassigned --</option>@foreach($logisticsManagers as $manager)<option value="{{ $manager->id }}">{{ $manager->full_name }}</option>@endforeach</select></div>
                    <div><label class="branch-form-label">Branch Address</label><input class="form-control blade-inline-14" name="address" id="branchAddress" placeholder="Street, building, or landmark"></div>
                </div>
                <div class="branch-form-actions"><button class="btn btn-outline" type="button" data-close-branch-modal>Cancel</button><button class="btn btn-coral" type="submit" id="branchModalSubmit">Add Branch</button></div>
            </form>
        </div>
    </div>
</div>

@vite('resources/js/views/logistics-branches.js')
@endsection
