@extends('pickrush.layouts.app')
@section('title', $member->exists ? 'Edit '.$member->full_name : 'Add staff')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>{{ $member->exists ? $member->full_name : 'Add staff' }}</h1>
        <p>HQ roles have no hub. Sorters work at province hubs, dispatchers at stations, hub managers at either.</p>
    </div>
</div>

<form method="POST" action="{{ $member->exists ? route('pickrush.hq.staff.update', $member) : route('pickrush.hq.staff.store') }}" class="pr-card">
    @csrf
    @if($member->exists) @method('PUT') @endif
    <div class="pr-form-grid">
        <div class="pr-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" class="pr-input" value="{{ old('full_name', $member->full_name) }}" required></div>
        <div class="pr-field"><label for="email">Email (sign-in)</label><input id="email" type="email" name="email" class="pr-input" value="{{ old('email', $member->email) }}" required></div>
        <div class="pr-field"><label for="mobile_no">Mobile</label><input id="mobile_no" name="mobile_no" class="pr-input" value="{{ old('mobile_no', $member->mobile_no) }}" placeholder="09XXXXXXXXX"></div>
        <div class="pr-field"><label for="role">Role</label>
            <select id="role" name="role" class="pr-select">
                @foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $member->role?->value) === $role->value)>{{ $role->label() }}</option>@endforeach
            </select></div>
        <div class="pr-field"><label for="hub_code">Hub code</label>
            <input id="hub_code" name="hub_code" class="pr-input" value="{{ old('hub_code', $hub?->code) }}" placeholder="e.g. S-041029000 (empty for HQ)">
            <small class="pr-muted">{{ $hub ? $hub->name : 'Find codes in the hub directory.' }}</small>
            @error('hub_code')<span class="pr-field-error">{{ $message }}</span>@enderror</div>
        <div class="pr-field"><label for="password">{{ $member->exists ? 'New password (leave empty to keep)' : 'Starting password' }}</label>
            <input id="password" type="password" name="password" class="pr-input" autocomplete="new-password" @if(! $member->exists) required @endif minlength="10">
            @error('password')<span class="pr-field-error">{{ $message }}</span>@enderror</div>
    </div>
    <label class="pr-check" style="margin-top:1rem"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->is_active))> Active (can sign in)</label>
    <div class="pr-actions">
        <button type="submit" class="pr-btn pr-btn-primary">Save</button>
        <a href="{{ route('pickrush.hq.staff.index') }}" class="pr-btn pr-btn-outline">Cancel</a>
    </div>
</form>
@endsection
