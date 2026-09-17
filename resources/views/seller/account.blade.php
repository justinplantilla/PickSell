@extends('seller.layout')
@section('title', 'My Account')

@section('styles')
<style>
    .account-page .card { position: relative; border: 1px solid rgba(232,71,42,.28); box-shadow: 0 8px 24px rgba(232,71,42,.08); }
    .account-page .card::before { content: ''; position: absolute; inset: 0 0 auto; height: 3px; background: var(--coral); }
    .account-page .btn { box-shadow: 0 0 0 1px rgba(232,71,42,.18), 0 6px 16px rgba(232,71,42,.2); transition: transform .2s, box-shadow .2s, background .2s; }
    .account-page .btn:hover { transform: translateY(-2px); box-shadow: 0 0 0 2px rgba(232,71,42,.2), 0 10px 24px rgba(232,71,42,.32); }
    .account-page .danger-card { border-color: rgba(220,38,38,.35); }
    .account-page .danger-card::before { background: #dc2626; }
</style>
@endsection

@section('content')
<div class="account-page grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Profile Information</span></div>
        <div class="card-body">
            <form method="POST" action="/seller/account">
                @csrf @method('PATCH')
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="{{ auth()->user()->first_name }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="{{ auth()->user()->last_name }}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="{{ auth()->user()->email }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact No. *</label>
                    <input type="text" name="contact_no" class="form-control" value="{{ auth()->user()->contact_no }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="business_name" class="form-control" value="{{ auth()->user()->business_name }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Line of Business</label>
                    <input type="text" name="line_of_business" class="form-control" value="{{ auth()->user()->line_of_business }}">
                </div>
                <button type="submit" class="btn btn-coral">Save Changes</button>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST" action="/seller/account/password">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                        @error('current_password')<div style="color:#dc2626;font-size:0.8rem;margin-top:0.3rem;">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-coral">Update Password</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Account Info</span></div>
            <div class="card-body">
                <table>
                    <tr><td style="color:#888;width:140px;">Role</td><td><span class="badge badge-active">Seller</span></td></tr>
                    <tr><td style="color:#888;">Status</td><td><span class="badge badge-{{ auth()->user()->status }}">{{ auth()->user()->status }}</span></td></tr>
                    <tr><td style="color:#888;">Sex</td><td>{{ auth()->user()->sex }}</td></tr>
                    <tr><td style="color:#888;">Birthday</td><td>{{ auth()->user()->birthday ? auth()->user()->birthday->format('M d, Y').' (Age '.auth()->user()->age.')' : '—' }}</td></tr>
                    <tr><td style="color:#888;">Address</td><td>{{ implode(', ', array_filter([auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province])) }}</td></tr>
                    <tr><td style="color:#888;">Member Since</td><td>{{ auth()->user()->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card danger-card">
            <div class="card-header"><span class="card-title">Delete Account</span></div>
            <div class="card-body">
                <p style="font-size:0.88rem;color:#888;margin-bottom:1rem;">Permanently delete your seller account and its related records.</p>
                <form method="POST" action="{{ route('account.delete') }}" onsubmit="return confirm('Delete your account permanently? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <input type="password" name="password" class="form-control" placeholder="Confirm your password" required style="margin-bottom:.7rem;">
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
