@extends('admin.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/admin-account.css')
@endsection

@section('content')
<div class="account-page">
    <div class="card">
        <div class="card-header"><span class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-1"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>Profile Information</span></div>
        <div class="card-body">
            <form method="POST" action="/admin/account">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="{{ auth()->user()->first_name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="{{ auth()->user()->last_name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="{{ auth()->user()->email }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact No.</label>
                    <input type="text" name="contact_no" class="form-control" value="{{ auth()->user()->contact_no }}" required>
                </div>
                <button type="submit" class="btn btn-coral"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-2"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg>Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-3"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6a3.1 3.1 0 0 1 6.2 0v2z"/></svg>Change Password</span></div>
        <div class="card-body">
            <form method="POST" action="/admin/account/password">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                    @error('current_password')<div class="blade-inline-4">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-coral"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-5"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6a3.1 3.1 0 0 1 6.2 0v2z"/></svg>Update Password</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>Account Details</span></div>
    <div class="card-body">
        <table class="blade-inline-7">
            <tr><td class="blade-inline-8">Role</td><td><span class="badge blade-inline-9">Administrator</span></td></tr>
            <tr><td class="blade-inline-10">Status</td><td><span class="badge badge-approved">Active</span></td></tr>
            <tr><td class="blade-inline-11">Member Since</td><td>{{ auth()->user()->created_at->format('F d, Y') }}</td></tr>
        </table>
    </div>
</div>

<div class="account-page blade-inline-12">
    <div class="card danger-card">
        <div class="card-header"><span class="card-title">Delete Account</span></div>
        <div class="card-body">
            <p class="blade-inline-13">Permanently delete your administrator account and related records.</p>
            <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                @csrf @method('DELETE')
                <input type="password" name="password" class="form-control" placeholder="Confirm your password" required class="blade-inline-14">
                <button type="submit" class="btn btn-danger">Delete Account</button>
            </form>
        </div>
    </div>
</div>
@endsection
