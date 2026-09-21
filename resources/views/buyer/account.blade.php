@extends('buyer.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/buyer-account.css')
@endsection

@section('content')
<h2 class="blade-inline-1">My Account</h2>

<div class="account-page grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Profile Information</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/account">
                    @csrf @method('PATCH')

                    <div class="blade-inline-2">
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="{{ auth()->user()->last_name }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="{{ auth()->user()->first_name }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">M.I.</label>
                            <input type="text" name="middle_initial" class="form-control" value="{{ auth()->user()->middle_initial }}" maxlength="5">
                        </div>
                    </div>

                    <div class="blade-inline-3">
                        <div class="form-group">
                            <label class="form-label">Sex *</label>
                            <select name="sex" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male" {{ auth()->user()->sex === 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ auth()->user()->sex === 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Birthday *</label>
                            <input type="date" name="birthday" class="form-control" value="{{ old('birthday', auth()->user()->birthday?->format('Y-m-d')) }}" required>
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

                    <div class="blade-inline-4">
                        <div class="form-group">
                            <label class="form-label">Province *</label>
                            <select class="form-control" data-profile-province aria-label="Registered province"><option>Loading provinces...</option></select><input type="hidden" name="province" data-profile-hidden="province" value="{{ auth()->user()->province }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Municipality *</label>
                            <select class="form-control" data-profile-municipality aria-label="Registered municipality"><option>Loading municipalities...</option></select><input type="hidden" name="municipality" data-profile-hidden="municipality" value="{{ auth()->user()->municipality }}">
                        </div>
                    </div>

                    <div class="blade-inline-5">
                        <div class="form-group">
                            <label class="form-label">Barangay *</label>
                            <select class="form-control" data-profile-barangay aria-label="Registered barangay"><option>Loading barangays...</option></select><input type="hidden" name="barangay" data-profile-hidden="barangay" value="{{ auth()->user()->barangay }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">House No.</label>
                            <input type="text" name="house_no" class="form-control" value="{{ auth()->user()->house_no }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Street</label>
                        <input type="text" name="street" class="form-control" value="{{ auth()->user()->street }}">
                    </div>

                    <button type="submit" class="btn btn-coral">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/account/password">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                        @error('current_password')<div class="blade-inline-6">{{ $message }}</div>@enderror
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
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Account Details</span></div>
            <div class="card-body">
                <table>
                    <tr><td class="blade-inline-7">Role</td><td><span class="badge badge-active">Buyer</span></td></tr>
                    <tr><td class="blade-inline-8">Status</td><td><span class="badge badge-{{ auth()->user()->status }}">{{ auth()->user()->status }}</span></td></tr>
                    <tr><td class="blade-inline-9">Full Name</td><td>{{ auth()->user()->full_name }}</td></tr>
                    <tr><td class="blade-inline-10">Email</td><td>{{ auth()->user()->email }}</td></tr>
                    <tr><td class="blade-inline-11">Contact</td><td>{{ auth()->user()->contact_no }}</td></tr>
                    <tr><td class="blade-inline-12">Sex</td><td>{{ auth()->user()->sex }}</td></tr>
                    <tr><td class="blade-inline-13">Birthday</td><td>{{ auth()->user()->birthday ? auth()->user()->birthday->format('M d, Y').' (Age '.auth()->user()->age.')' : '—' }}</td></tr>
                    <tr><td class="blade-inline-14">Address</td><td>{{ implode(', ', array_filter([auth()->user()->house_no, auth()->user()->street, auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province])) }}</td></tr>
                    <tr><td class="blade-inline-15">Member Since</td><td>{{ auth()->user()->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Quick Links</span></div>
            <div class="card-body blade-inline-16">
                <a href="/buyer/orders" class="btn btn-outline blade-inline-17">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    My Orders
                </a>
                <a href="/buyer/cart" class="btn btn-outline blade-inline-18">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                    My Cart
                </a>
                <a href="/buyer/chat" class="btn btn-outline blade-inline-19">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                    Messages
                </a>
            </div>
        </div>

        <div class="card danger-card">
            <div class="card-header"><span class="card-title">Delete Account</span></div>
            <div class="card-body">
                <p class="blade-inline-20">Permanently delete your buyer account and its related records.</p>
                <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                    @csrf @method('DELETE')
                    <input type="password" name="password" class="form-control" placeholder="Confirm your password" required class="blade-inline-21">
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
