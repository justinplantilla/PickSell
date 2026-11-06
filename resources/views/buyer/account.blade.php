@extends('buyer.layout')
@section('title', 'My Account')

@section('styles')
@vite('resources/css/views/buyer-account.css')
@endsection

@section('content')
<div class="buyer-account-template">
    <form method="POST" action="/buyer/account" class="buyer-account-profile-form">
        @csrf @method('PATCH')
        <section class="buyer-account-section buyer-account-personal">
            <div class="buyer-account-section-heading">
                <div>
                    <h2>Personal Information</h2>
                    <p>Update your personal details and how we can reach you.</p>
                </div>
                <button type="submit" class="btn btn-coral">Save Changes</button>
            </div>

            <div class="buyer-account-profile-grid">
                <div class="buyer-account-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
                <div class="buyer-account-field">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name', auth()->user()->last_name) }}" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name', auth()->user()->first_name) }}" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">M.I.</label>
                    <input type="text" name="middle_initial" class="form-control" value="{{ old('middle_initial', auth()->user()->middle_initial) }}" maxlength="5">
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">Contact No. *</label>
                    <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', auth()->user()->contact_no) }}" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">Birthday *</label>
                    <input type="date" name="birthday" class="form-control" value="{{ old('birthday', auth()->user()->birthday?->format('Y-m-d')) }}" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">Sex *</label>
                    <select name="sex" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="Male" {{ old('sex', auth()->user()->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('sex', auth()->user()->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="buyer-account-section buyer-account-addresses">
            <div class="buyer-account-section-heading compact-heading">
                <div>
                    <h2>Shipping Address</h2>
                    <p>Manage the address where your orders are delivered.</p>
                </div>
                <span class="buyer-account-address-badge">DEFAULT</span>
            </div>
            <div class="buyer-account-address-card">
                <div class="buyer-account-address-card-heading">
                    <strong>{{ auth()->user()->full_name }}</strong>
                    <span>Default address</span>
                </div>
                <div class="buyer-account-address-grid">
                    <div class="buyer-account-field">
                        <label class="form-label">Province *</label>
                        <select class="form-control" data-profile-province aria-label="Registered province"><option>Loading provinces...</option></select>
                        <input type="hidden" name="province" data-profile-hidden="province" value="{{ old('province', auth()->user()->province) }}">
                    </div>
                    <div class="buyer-account-field">
                        <label class="form-label">Municipality *</label>
                        <select class="form-control" data-profile-municipality aria-label="Registered municipality"><option>Loading municipalities...</option></select>
                        <input type="hidden" name="municipality" data-profile-hidden="municipality" value="{{ old('municipality', auth()->user()->municipality) }}">
                    </div>
                    <div class="buyer-account-field">
                        <label class="form-label">Barangay *</label>
                        <select class="form-control" data-profile-barangay aria-label="Registered barangay"><option>Loading barangays...</option></select>
                        <input type="hidden" name="barangay" data-profile-hidden="barangay" value="{{ old('barangay', auth()->user()->barangay) }}">
                    </div>
                    <div class="buyer-account-field">
                        <label class="form-label">House No.</label>
                        <input type="text" name="house_no" class="form-control" value="{{ old('house_no', auth()->user()->house_no) }}">
                    </div>
                    <div class="buyer-account-field buyer-account-street-field">
                        <label class="form-label">Street</label>
                        <input type="text" name="street" class="form-control" value="{{ old('street', auth()->user()->street) }}">
                    </div>
                </div>
            </div>
        </section>
    </form>

    {{-- Other delivery addresses, chosen at checkout; the profile address above stays the default. --}}
    <section class="buyer-account-section buyer-account-book" id="addresses">
        <div class="buyer-account-section-heading compact-heading">
            <div>
                <h2>Other Addresses</h2>
                <p>Save other places you ship to, like work or family. Pick one at checkout.</p>
            </div>
        </div>
        @forelse(auth()->user()->addresses as $saved)
            <details class="buyer-account-book-item">
                <summary>
                    <strong>{{ $saved->label ?: 'Saved address' }}</strong> · {{ $saved->recipient_name }} · {{ $saved->contact_no }}
                    <span>{{ \App\Support\DeliveryAddress::fromBook($saved)->line() }}</span>
                </summary>
                @include('buyer.partials.address-form', ['address' => $saved, 'itemIds' => []])
                <div class="address-extra-actions">
                    <form method="POST" action="{{ route('buyer.addresses.default', $saved) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Set as default</button>
                    </form>
                    <form method="POST" action="{{ route('buyer.addresses.destroy', $saved) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm buyer-account-book-delete">Delete this address</button>
                    </form>
                </div>
            </details>
        @empty
            <p class="buyer-account-book-empty">No other addresses yet.</p>
        @endforelse
        @if(auth()->user()->addresses->count() < \App\Http\Controllers\BuyerAddressController::MAX_ADDRESSES)
        <details class="buyer-account-book-new">
            <summary class="btn btn-outline btn-sm">+ Add address</summary>
            @include('buyer.partials.address-form', ['address' => null, 'itemIds' => []])
        </details>
        @endif
    </section>

    <section class="buyer-account-section buyer-account-pin" id="delivery-location">
        <div class="buyer-account-section-heading compact-heading">
            <div>
                <h2>Delivery Pin</h2>
                <p>Pin your door so the Pickrush rider can find you. Your typed address is still printed on the label.</p>
            </div>
        </div>
        @if(auth()->user()->delivery_lat === null)
            <div class="alert alert-warning buyer-account-pin-note">No pin yet. Riders will rely on your typed address until you set one.</div>
        @endif
        <form method="POST" action="{{ route('buyer.account.delivery-location') }}" data-no-confirm>
            @csrf @method('PATCH')
            @include('partials.map-pin', ['id' => 'buyer-pin', 'lat' => auth()->user()->delivery_lat, 'lng' => auth()->user()->delivery_lng])
            @error('lat')<div class="field-error">{{ $message }}</div>@enderror
            @error('lng')<div class="field-error">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-coral buyer-account-pin-save">Save Pin</button>
        </form>
    </section>

    <div class="buyer-account-bottom-grid">
        <section class="buyer-account-section buyer-account-security">
            <div class="buyer-account-section-heading compact-heading">
                <div>
                    <h2>Security &amp; Password</h2>
                    <p>Keep your account secure with a strong password.</p>
                </div>
            </div>
            <form method="POST" action="/buyer/account/password">
                @csrf @method('PATCH')
                <div class="buyer-account-field">
                    <label class="form-label">Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                    @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="buyer-account-field">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-coral">Update Password</button>
            </form>
        </section>

        <section class="buyer-account-section buyer-account-danger">
            <div class="buyer-account-section-heading compact-heading">
                <div>
                    <h2>Delete Account</h2>
                    <p>Permanently remove your buyer account and related records.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('account.delete') }}" data-confirm="Delete your account permanently? This cannot be undone.">
                @csrf @method('DELETE')
                <div class="buyer-account-field">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-danger">Delete Account</button>
            </form>
        </section>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.profile-address-script')
@vite('resources/js/components/address-picker.js')
@endsection
