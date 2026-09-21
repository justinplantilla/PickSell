@extends('seller.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/seller-account.css')
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
                    <label class="form-label">Middle Initial</label>
                    <input type="text" name="middle_initial" class="form-control" value="{{ auth()->user()->middle_initial }}" maxlength="5">
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Sex *</label><select name="sex" class="form-control" required><option value="Male" {{ auth()->user()->sex === 'Male' ? 'selected' : '' }}>Male</option><option value="Female" {{ auth()->user()->sex === 'Female' ? 'selected' : '' }}>Female</option></select></div>
                    <div class="form-group"><label class="form-label">Birthday *</label><input type="date" name="birthday" class="form-control" value="{{ auth()->user()->birthday?->format('Y-m-d') }}" required></div>
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
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Province</label><select class="form-control" data-profile-province aria-label="Registered province"><option>Loading provinces...</option></select><input type="hidden" name="province" data-profile-hidden="province" value="{{ auth()->user()->province }}"></div>
                    <div class="form-group"><label class="form-label">Municipality</label><select class="form-control" data-profile-municipality aria-label="Registered municipality"><option>Loading municipalities...</option></select><input type="hidden" name="municipality" data-profile-hidden="municipality" value="{{ auth()->user()->municipality }}"></div>
                    <div class="form-group"><label class="form-label">Barangay</label><select class="form-control" data-profile-barangay aria-label="Registered barangay"><option>Loading barangays...</option></select><input type="hidden" name="barangay" data-profile-hidden="barangay" value="{{ auth()->user()->barangay }}"></div>
                    <div class="form-group"><label class="form-label">House No.</label><input type="text" name="house_no" class="form-control" value="{{ auth()->user()->house_no }}"></div>
                </div>
                <div class="form-group"><label class="form-label">Street</label><input type="text" name="street" class="form-control" value="{{ auth()->user()->street }}"></div>
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
                        @error('current_password')<div class="blade-inline-1">{{ $message }}</div>@enderror
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
                    <tr><td class="blade-inline-2">Role</td><td><span class="badge badge-active">Seller</span></td></tr>
                    <tr><td class="blade-inline-3">Status</td><td><span class="badge badge-{{ auth()->user()->status }}">{{ auth()->user()->status }}</span></td></tr>
                    <tr><td class="blade-inline-4">Email</td><td>{{ auth()->user()->email }}</td></tr>
                    <tr><td class="blade-inline-5">Contact</td><td>{{ auth()->user()->contact_no }}</td></tr>
                    <tr><td class="blade-inline-6">Business</td><td>{{ auth()->user()->business_name ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-7">Line of Business</td><td>{{ auth()->user()->line_of_business ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-8">Sex</td><td>{{ auth()->user()->sex }}</td></tr>
                    <tr><td class="blade-inline-9">Birthday</td><td>{{ auth()->user()->birthday ? auth()->user()->birthday->format('M d, Y').' (Age '.auth()->user()->age.')' : '—' }}</td></tr>
                    <tr><td class="blade-inline-10">Address</td><td>{{ implode(', ', array_filter([auth()->user()->house_no, auth()->user()->street, auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province])) }}</td></tr>
                    <tr><td class="blade-inline-11">Member Since</td><td>{{ auth()->user()->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card danger-card">
            <div class="card-header"><span class="card-title">Delete Account</span></div>
            <div class="card-body">
                <p class="blade-inline-12">Permanently delete your seller account and its related records.</p>
                <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                    @csrf @method('DELETE')
                    <input type="password" name="password" class="form-control" placeholder="Confirm your password" required class="blade-inline-13">
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.profile-address-script')
@endsection
