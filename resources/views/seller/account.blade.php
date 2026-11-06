@extends('seller.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/seller-account.css')
@endsection

@section('content')
<div class="account-page grid-2">
    <div class="account-column">
        <div class="card">
            <div class="card-header profile-card-header">
                <span class="card-title">Profile Information</span>
                <div class="account-status">
                    <span class="badge badge-seller">Seller</span>
                    <span class="badge badge-{{ auth()->user()->status }}">{{ ucfirst(auth()->user()->status) }}</span>
                </div>
            </div>
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

        <div class="card danger-card">
            <div class="card-header"><span class="card-title">Delete Account</span></div>
            <div class="card-body">
                <p class="account-muted-text">Permanently delete your seller account and its related records.</p>
                <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                    @csrf @method('DELETE')
                    <input type="password" name="password" class="form-control account-spaced" placeholder="Confirm your password" required>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </form>
            </div>
        </div>
    </div>

    <div class="account-column">
        @php
            $seller = auth()->user();
            $onVacation = $seller->isOnVacation();
            $maxDays = \App\Models\User::MAX_VACATION_DAYS_PER_MONTH;
        @endphp
        {{-- What buyers see at the top of the store and beside its products. --}}
        <div class="card" id="store-profile">
            <div class="card-header"><span class="card-title">Store Profile</span></div>
            <div class="card-body">
                <div class="store-profile-preview" @if($seller->store_banner_url) style="background-image:url('{{ $seller->store_banner_url }}')" @endif>
                    <span class="store-profile-logo">@if($seller->store_logo_url)<img src="{{ $seller->store_logo_url }}" alt="">@else{{ strtoupper(substr($seller->business_name ?: $seller->first_name, 0, 1)) }}@endif</span>
                    <strong>{{ $seller->business_name ?? $seller->full_name }}</strong>
                </div>
                <form method="POST" action="{{ route('seller.account.store-profile') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="store_logo">Logo</label>
                        <input type="file" id="store_logo" name="store_logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="form-hint">Square works best · JPG, PNG or WEBP · 2 MB.</small>
                        @if($seller->store_logo)<label class="store-profile-remove"><input type="checkbox" name="remove_store_logo" value="1"> Remove current logo</label>@endif
                        @error('store_logo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="store_banner">Banner</label>
                        <input type="file" id="store_banner" name="store_banner" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="form-hint">Wide image, about 1200 × 300 · 4 MB.</small>
                        @if($seller->store_banner)<label class="store-profile-remove"><input type="checkbox" name="remove_store_banner" value="1"> Remove current banner</label>@endif
                        @error('store_banner')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="store_description">About the store</label>
                        <textarea id="store_description" name="store_description" class="form-control" rows="4" maxlength="1000" placeholder="What you sell, where you ship from, what makes your store worth following.">{{ old('store_description', $seller->store_description) }}</textarea>
                        @error('store_description')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-coral btn-sm">Save store profile</button>
                </form>
            </div>
        </div>

        <div class="card" id="vacation-mode">
            <div class="card-header"><span class="card-title">Vacation Mode</span></div>
            <div class="card-body">
                <p class="account-muted-text">You get {{ $maxDays }} vacation days a month. Your store and products stay visible, but buyers can't order until your return date, when vacation mode turns off by itself. Buyers who message you get your away message once.</p>
                <p class="account-vacation-days"><strong>{{ $vacationDaysLeft }}</strong> of {{ $maxDays }} vacation days left this month</p>
                @if($onVacation)
                    <div class="alert alert-warning">You're on vacation until {{ $seller->vacation_ends_at?->format('M j, Y') }}. Buyers can't order from you until then.</div>
                @elseif(! $latestReturn)
                    <div class="alert alert-warning">You've used all your vacation days this month. They reset on {{ now()->addMonthNoOverflow()->startOfMonth()->format('M j') }}.</div>
                @endif
                @error('vacation_mode')<div class="alert alert-error">{{ $message }}</div>@enderror
                @if($onVacation || $latestReturn)
                <form method="POST" action="{{ route('seller.account.vacation') }}">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Back on *</label>
                        <input type="date" name="vacation_until" class="form-control" required
                            min="{{ now()->addDay()->toDateString() }}" max="{{ $latestReturn?->toDateString() }}"
                            value="{{ old('vacation_until', $onVacation ? $seller->vacation_ends_at?->toDateString() : '') }}">
                        @error('vacation_until')<div class="account-danger-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Away message</label>
                        <textarea name="vacation_message" class="form-control" rows="3" maxlength="255" placeholder="{{ sprintf(\App\Models\User::DEFAULT_VACATION_MESSAGE, 'your return date') }}">{{ old('vacation_message', $seller->vacation_message) }}</textarea>
                        @error('vacation_message')<div class="account-danger-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="account-actions">
                        @if($onVacation)
                            <button type="submit" name="vacation_mode" value="0" class="btn btn-coral" formnovalidate>End Vacation</button>
                            <button type="submit" name="vacation_mode" value="1" class="btn btn-outline">Save Changes</button>
                        @else
                            <button type="submit" name="vacation_mode" value="1" class="btn btn-coral">Turn On Vacation Mode</button>
                        @endif
                    </div>
                </form>
                @endif
            </div>
        </div>

        <div class="card" id="pickup-location">
            <div class="card-header"><span class="card-title">Shop Pin for Pickups</span></div>
            <div class="card-body">
                <p class="account-muted-text">Pin where Pickrush riders collect your parcels. Riders can only confirm they arrived when they are near this pin, so place it at your shop's door.</p>
                @if(auth()->user()->pickup_lat === null)
                    <div class="alert alert-warning">No pin yet. Riders will rely on your typed address until you set one.</div>
                @endif
                <form method="POST" action="{{ route('seller.account.pickup-location') }}">
                    @csrf @method('PATCH')
                    @include('partials.map-pin', ['id' => 'seller-pin', 'lat' => auth()->user()->pickup_lat, 'lng' => auth()->user()->pickup_lng])
                    @error('lat')<div class="account-danger-text">{{ $message }}</div>@enderror
                    @error('lng')<div class="account-danger-text">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-coral account-pin-save">Save Pin</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST" action="/seller/account/password">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                        @error('current_password')<div class="account-danger-text">{{ $message }}</div>@enderror
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
</div>
@endsection

@section('scripts')
@include('partials.profile-address-script')
@endsection
