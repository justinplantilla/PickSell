@extends('logistics.layout')
@section('styles')
@vite('resources/css/views/logistics-module.css')
@endsection
@section('title', $definition['title'])
@section('content')
<div class="blade-inline-1"><h1 class="blade-inline-2">{{ $definition['title'] }}</h1><p class="blade-inline-3">{{ $definition['description'] }}</p></div>

@if($module === 'delivery-reports')
<div class="stats">@foreach($summary as $status => $total)<div class="stat"><strong>{{ $total }}</strong><span>{{ ucfirst($status) }} Orders</span></div>@endforeach</div>
@elseif($module === 'branch-reports')
<div class="stats"><div class="stat"><strong>{{ App\Models\LogisticsBranch::where('status', 'active')->count() }}</strong><span>Active Branches</span></div><div class="stat"><strong>{{ App\Models\BranchRider::where('status', 'active')->count() }}</strong><span>Assigned Riders</span></div><div class="stat"><strong>{{ App\Models\RiderBarangay::count() }}</strong><span>Barangay Routes</span></div></div>
@endif

<div class="card"><div class="card-header"><span class="card-title">{{ $definition['title'] }}</span><span class="blade-inline-4">{{ $records instanceof \Illuminate\Pagination\LengthAwarePaginator ? $records->total() : $records->count() }} records</span></div><div class="blade-inline-5"><table><thead><tr>
@if($module === 'coverage')<th>Barangay</th><th>Municipality</th><th>Rider</th><th>Branch</th>
@elseif($module === 'assignments')<th>Rider</th><th>Branch</th><th>Barangays</th><th>Status</th>
@elseif($module === 'tracking')<th>Order</th><th>Destination</th><th>Tracking</th><th>Rider</th>
@elseif($module === 'riders')<th>Rider</th><th>Area</th><th>Branches</th><th>Status</th>
@elseif($module === 'complaints')<th>Subject</th><th>Filed By</th><th>Against</th><th>Status</th>
@elseif($module === 'messages')<th>Sender</th><th>Receiver</th><th>Message</th><th>Date</th>
@else<th>Branch</th><th>Municipality</th><th>Riders</th><th>Status</th>@endif
</tr></thead><tbody>
@forelse($records as $record)
<tr>
@if($module === 'coverage')<td>{{ $record->barangay->name }}</td><td>{{ $record->barangay->municipality->name }}, {{ $record->barangay->municipality->province }}</td><td>{{ $record->assignment->rider->full_name }}</td><td>{{ $record->assignment->branch->name }}</td>
@elseif($module === 'assignments')<td>{{ $record->rider->full_name }}</td><td>{{ $record->branch->name }}</td><td>{{ $record->barangays->map->barangay->map->name->join(', ') ?: 'None' }}</td><td>{{ ucfirst($record->status) }}</td>
@elseif($module === 'tracking')<td><strong>{{ $record->order_number }}</strong></td><td>{{ $record->buyer->municipality ?? '—' }}, {{ $record->buyer->province ?? '—' }}</td><td>{{ $record->tracking_status ?? $record->status }}</td><td>{{ $record->courier->full_name ?? 'Unassigned' }}</td>
@elseif($module === 'riders')<td>{{ $record->full_name }}<div class="blade-inline-6">{{ $record->email }}</div></td><td>{{ $record->delivery_area ?? ($record->municipality ?? 'Not specified') }}</td><td>{{ $record->branchAssignments->map->branch->map->name->join(', ') ?: 'Unassigned' }}</td><td><span class="badge badge-approved">Approved</span></td>
@elseif($module === 'complaints')<td>{{ $record->subject }}</td><td>{{ $record->filer->full_name ?? '—' }}</td><td>{{ $record->against->full_name ?? '—' }}</td><td>{{ ucfirst($record->status) }}</td>
@elseif($module === 'messages')<td>{{ $record->sender->full_name ?? '—' }}</td><td>{{ $record->receiver->full_name ?? '—' }}</td><td>{{ \Illuminate\Support\Str::limit($record->body, 80) }}</td><td>{{ $record->created_at?->format('M d, Y') }}</td>
@else<td>{{ $record->name }}</td><td>{{ $record->municipality->name }}, {{ $record->municipality->province }}</td><td>{{ $record->riderAssignments->count() }}</td><td>{{ ucfirst($record->status) }}</td>@endif
</tr>
@empty<tr><td colspan="4" class="blade-inline-7">No records found.</td></tr>@endforelse
</tbody></table></div></div>
@if($records instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="blade-inline-8">{{ $records->links() }}</div>@endif
@endsection
