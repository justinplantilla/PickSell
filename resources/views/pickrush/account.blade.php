@extends('pickrush.layouts.app')
@section('title', 'My account')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>My account</h1>
        <p>{{ $staff->full_name }} · {{ $staff->email }} · {{ $staff->role->label() }}{{ $staff->hub ? ' at '.$staff->hub->name : ' (HQ)' }}</p>
    </div>
</div>
<form method="POST" action="{{ route('pickrush.account.password') }}" class="pr-card" style="max-width:520px">
    @csrf @method('PUT')
    <h2>Change password</h2>
    <div class="pr-field"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" class="pr-input" autocomplete="current-password" required>
        @error('current_password')<span class="pr-field-error">{{ $message }}</span>@enderror</div>
    <div class="pr-field"><label for="password">New password (at least 10 characters)</label><input id="password" type="password" name="password" class="pr-input" autocomplete="new-password" required minlength="10">
        @error('password')<span class="pr-field-error">{{ $message }}</span>@enderror</div>
    <div class="pr-field"><label for="password_confirmation">Repeat new password</label><input id="password_confirmation" type="password" name="password_confirmation" class="pr-input" autocomplete="new-password" required></div>
    <button type="submit" class="pr-btn pr-btn-primary">Change password</button>
</form>
@endsection
