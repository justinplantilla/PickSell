@extends('buyer.layout')
@section('title', 'My Account')

@section('content')
<h2 style="font-size:1.2rem;font-weight:800;margin-bottom:1.2rem;">My Account</h2>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Profile Information</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/account">
                    @csrf @method('PATCH')

                    <div style="display:grid;grid-template-columns:1fr 1fr 0.5fr;gap:1rem;">
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

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
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

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Province *</label>
                            <input type="text" name="province" class="form-control" value="{{ auth()->user()->province }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Municipality *</label>
                            <input type="text" name="municipality" class="form-control" value="{{ auth()->user()->municipality }}" required>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Barangay *</label>
                            <input type="text" name="barangay" class="form-control" value="{{ auth()->user()->barangay }}" required>
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
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Account Details</span></div>
            <div class="card-body">
                <table>
                    <tr><td style="color:#888;width:130px;">Full Name</td><td>{{ auth()->user()->full_name }}</td></tr>
                    <tr><td style="color:#888;">Sex</td><td>{{ auth()->user()->sex }}</td></tr>
                    <tr><td style="color:#888;">Birthday</td><td>{{ auth()->user()->birthday ? auth()->user()->birthday->format('M d, Y').' (Age '.auth()->user()->age.')' : '—' }}</td></tr>
                    <tr><td style="color:#888;">Address</td><td>{{ implode(', ', array_filter([auth()->user()->house_no, auth()->user()->street, auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province])) }}</td></tr>
                    <tr><td style="color:#888;">Member Since</td><td>{{ auth()->user()->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Quick Links</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">
                <a href="/buyer/orders" class="btn btn-outline" style="justify-content:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    My Orders
                </a>
                <a href="/buyer/cart" class="btn btn-outline" style="justify-content:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                    My Cart
                </a>
                <a href="/buyer/chat" class="btn btn-outline" style="justify-content:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                    Messages
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Logout</span></div>
            <div class="card-body">
                <p style="font-size:0.88rem;color:#888;margin-bottom:1rem;">Sign out of your buyer account.</p>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit" class="btn btn-danger">Logout</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
