@extends('logistics.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/buyer-account.css')
@vite('resources/css/views/logistics-account-template.css')
@endsection

@section('content')
<h2 class="account-page-title">My Account</h2>

<div class="account-page logistics-account-page">
    <div>
        <div class="card account-profile-card">
            <div class="card-header">
                <span class="card-title">Profile Information</span>
                <span class="badge badge-approved">{{ ucfirst($user->status) }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.account.update') }}">
                    @csrf @method('PATCH')

                    <div class="form-row profile-name-row">
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">M.I.</label>
                            <input type="text" name="middle_initial" class="form-control" value="{{ old('middle_initial', $user->middle_initial) }}" maxlength="5">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sex *</label>
                            <select name="sex" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male" {{ old('sex', $user->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('sex', $user->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Birthday *</label>
                            <input type="date" name="birthday" class="form-control" value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-control" value="{{ $user->age ?? 'Not set' }}" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact No. *</label>
                            <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $user->contact_no) }}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Province *</label>
                            <input type="text" name="province" class="form-control" value="{{ old('province', $user->province) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Municipality *</label>
                            <input type="text" name="municipality" class="form-control" value="{{ old('municipality', $user->municipality) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Barangay *</label>
                            <input type="text" name="barangay" class="form-control" value="{{ old('barangay', $user->barangay) }}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">House No.</label>
                            <input type="text" name="house_no" class="form-control" value="{{ old('house_no', $user->house_no) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Street</label>
                            <input type="text" name="street" class="form-control" value="{{ old('street', $user->street) }}">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-coral">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.account.password') }}">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                        @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-coral">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="card logistics-details-card">
            <div class="card-header"><span class="card-title">Logistics Details</span></div>
            <div class="card-body account-details-list">
                <div><span>Role</span><strong>{{ ucfirst($user->role) }}</strong></div>
                <div><span>Provider Type</span><strong>{{ $user->provider_type ? ucfirst($user->provider_type) : 'Not set' }}</strong></div>
                <div><span>Business Name</span><strong>{{ $user->business_name ?: 'Not set' }}</strong></div>
                <div><span>Line of Business</span><strong>{{ $user->line_of_business ?: 'Not set' }}</strong></div>
                <div><span>Account Status</span><strong>{{ ucfirst($user->status) }}</strong></div>
                <div><span>Government ID</span><strong>@if($user->id_upload)<a href="{{ asset('storage/'.$user->id_upload) }}" target="_blank">View uploaded ID</a>@else Not uploaded @endif</strong></div>
                <div><span>Business Permit</span><strong>@if($user->business_permit)<a href="{{ asset('storage/'.$user->business_permit) }}" target="_blank">View uploaded permit</a>@else Not uploaded @endif</strong></div>
            </div>
        </div>

        <div class="card danger-card">
            <div class="card-header"><span class="card-title">Delete Account</span></div>
            <div class="card-body">
                <p class="account-danger-copy">Permanently delete your Logistics account and its related records.</p>
                <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                    @csrf @method('DELETE')
                    <input type="password" name="password" class="form-control" placeholder="Confirm your password" required>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
