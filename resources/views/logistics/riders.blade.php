@extends('logistics.layout')
@section('title', 'Active Riders')
@section('content')
<div class="blade-inline-1"><h1 class="blade-inline-2">Rider Management</h1><p class="blade-inline-3">Activate or deactivate approved riders and review their delivery areas.</p></div>
<div class="card"><div class="card-header"><span class="card-title">Riders</span><span class="blade-inline-4">{{ $records->total() }} records</span></div><div class="blade-inline-5"><table><thead><tr><th>Rider</th><th>Area</th><th>Branches</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $rider)
<tr><td><strong>{{ $rider->full_name }}</strong><div class="blade-inline-6">{{ $rider->email }}</div></td><td>{{ $rider->delivery_area ?? ($rider->municipality ?? 'Not specified') }}</td><td>{{ $rider->branchAssignments->map->branch->map->name->join(', ') ?: 'Unassigned' }}</td><td><span class="badge badge-{{ $rider->status }}">{{ $rider->status }}</span></td><td><form method="POST" action="{{ route('logistics.riders.status', $rider) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $rider->status === 'approved' ? 'suspended' : 'approved' }}"><button class="btn {{ $rider->status === 'approved' ? 'btn-outline' : 'btn-success' }}">{{ $rider->status === 'approved' ? 'Deactivate' : 'Activate' }}</button></form></td></tr>
@empty<tr><td colspan="5" class="blade-inline-7">No active riders found.</td></tr>@endforelse
</tbody></table></div></div>
@if($records->hasPages())<div class="blade-inline-8">{{ $records->links() }}</div>@endif
@endsection