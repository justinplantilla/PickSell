@extends('seller.layout')
@section('title', 'My Account')

@section('content')
<div class="grid-2">
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

        <div class="card">
            <div class="card-header"><span class="card-title">Logout</span></div>
            <div class="card-body">
                <p style="font-size:0.88rem;color:#888;margin-bottom:1rem;">Sign out of your seller account.</p>
                <button class="btn btn-danger" onclick="document.getElementById('logoutModal').style.display='flex'">Logout</button>
            </div>
        </div>
    </div>
</div>
@endsection
