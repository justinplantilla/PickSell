@extends('logistics.layout')
@vite('resources/css/views/logistics-branch-edit.css')
@section('title', 'Edit Branch')
@section('content')
<div class="blade-inline-1">
    <div><h1 class="blade-inline-2">Edit Branch</h1><p class="blade-inline-3">Update the branch details without removing its rider assignments.</p></div>
    <a class="btn btn-outline" href="{{ route('logistics.branches') }}">Back to Branches</a>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">{{ $branch->name }}</span><span class="blade-inline-4">{{ $branch->municipality->name }}, {{ $branch->municipality->province }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.branches.update', $branch) }}" class="blade-inline-5">
            @csrf
            @method('PUT')
            <div><label class="blade-inline-6">Branch Name *</label><input class="form-control blade-inline-7" name="name" value="{{ old('name', $branch->name) }}" required></div>
            <div><label class="blade-inline-8">Status *</label><select class="form-control blade-inline-9" name="status" required><option value="active" {{ old('status', $branch->status) === 'active' ? 'selected' : '' }}>Active</option><option value="inactive" {{ old('status', $branch->status) === 'inactive' ? 'selected' : '' }}>Inactive</option></select></div>
            <div><label class="blade-inline-10">Province *</label><input class="form-control blade-inline-11" name="province" value="{{ old('province', $branch->municipality->province) }}" required></div>
            <div><label class="blade-inline-12">Municipality / City *</label><input class="form-control blade-inline-13" name="municipality" value="{{ old('municipality', $branch->municipality->name) }}" required></div>
            <div><label class="blade-inline-14">Branch Manager</label><select class="form-control blade-inline-15" name="logistics_id"><option value="">-- Unassigned --</option>@foreach($logisticsManagers as $manager)<option value="{{ $manager->id }}" {{ (string) old('logistics_id', $branch->logistics_id) === (string) $manager->id ? 'selected' : '' }}>{{ $manager->full_name }}</option>@endforeach</select></div>
            <div><label class="blade-inline-16">Branch Address</label><input class="form-control blade-inline-17" name="address" value="{{ old('address', $branch->address) }}" placeholder="Street, building, or landmark"></div>
            <div class="blade-inline-18"><a class="btn btn-outline" href="{{ route('logistics.branches') }}">Cancel</a><button class="btn btn-coral" type="submit">Save Changes</button></div>
        </form>
    </div>
</div>
@endsection
