@extends('logistics.layout')
@section('title', 'Edit Province Hub')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $branch->name }}</h1>
        <p>Update the hub's details and status.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-outline" href="{{ route('logistics.branches') }}">← All province hubs</a>
    </div>
</div>

@if(! $branch->assignedOperator)
    <div class="callout"><div><strong>This hub has no operator yet,</strong> so nobody can sign in to its portal. Fill in the operator account below.</div></div>
@endif

<div class="card">
    <form method="POST" action="{{ route('logistics.branches.update', $branch) }}">
        @csrf
        @method('PUT')
        <div class="form-section">
            <h2>Hub details</h2>
            <p>The operator signs in at {{ \App\Support\LogisticsDomains::superadminHost() }}/login.</p>
            <div class="branch-form-grid">
                <div><label for="hub-name">Hub name *</label><input class="form-control" id="hub-name" name="name" value="{{ old('name', $branch->name) }}" required></div>
                <div><label for="hub-province">Province *</label><input class="form-control" id="hub-province" name="province" value="{{ old('province', $branch->municipality->province) }}" required></div>
                <div><label for="hub-municipality">Municipality / City *</label><input class="form-control" id="hub-municipality" name="municipality" value="{{ old('municipality', $branch->municipality->name) }}" required></div>
                <div><label for="hub-address">Address</label><input class="form-control" id="hub-address" name="address" value="{{ old('address', $branch->address) }}"></div>
                <div>
                    <label for="hub-status">Status *</label>
                    <select class="form-control" id="hub-status" name="status" required>
                        <option value="active" @selected(old('status', $branch->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $branch->status) === 'inactive')>Inactive</option>
                    </select>
                    <small>Inactive hubs' portals stop working until reactivated.</small>
                </div>
            </div>
        </div>
        <div class="form-section">
            <h2>Operator</h2>
            @if($branch->assignedOperator)
                <p>This account signs in at the hub's portal.</p>
                <div class="branch-form-grid">
                    <div><label>Name</label><div>{{ $branch->assignedOperator->full_name }}</div></div>
                    <div><label>Login email</label><div>{{ $branch->assignedOperator->email }}</div></div>
                </div>
            @else
                <p>Create the account that will run this hub.</p>
                @include('logistics.partials.operator-fields')
            @endif
        </div>
        <div class="form-actions"><a class="btn btn-outline" href="{{ route('logistics.branches') }}">Cancel</a><button class="btn btn-coral" type="submit">Save Changes</button></div>
    </form>
</div>
@endsection
