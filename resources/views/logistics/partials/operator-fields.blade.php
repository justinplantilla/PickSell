{{-- Operator account fields shared by the province-hub and station-hub forms. --}}
<div class="branch-form-grid">
    <div><label for="operator-first-name">First name *</label><input class="form-control" id="operator-first-name" name="operator_first_name" value="{{ old('operator_first_name') }}" required autocomplete="off"></div>
    <div><label for="operator-last-name">Last name *</label><input class="form-control" id="operator-last-name" name="operator_last_name" value="{{ old('operator_last_name') }}" required autocomplete="off"></div>
    <div>
        <label for="operator-sex">Sex *</label>
        <select class="form-control" id="operator-sex" name="operator_sex" required>
            <option value="">Select</option>
            @foreach(['Male', 'Female'] as $sex)
                <option value="{{ $sex }}" @selected(old('operator_sex') === $sex)>{{ $sex }}</option>
            @endforeach
        </select>
    </div>
    <div><label for="operator-birthday">Birthday *</label><input class="form-control" id="operator-birthday" name="operator_birthday" type="date" value="{{ old('operator_birthday') }}" max="{{ now()->subYears(18)->toDateString() }}" required><small>Operators must be at least 18.</small></div>
    <div><label for="operator-email">Login email *</label><input class="form-control" id="operator-email" name="operator_email" type="email" value="{{ old('operator_email') }}" required autocomplete="off"><small>They sign in with this at {{ \App\Support\LogisticsDomains::superadminHost() }}/login.</small></div>
    <div><label for="operator-contact">Mobile number *</label><input class="form-control" id="operator-contact" name="operator_contact_no" value="{{ old('operator_contact_no') }}" pattern="09[0-9]{9}" inputmode="numeric" maxlength="11" placeholder="09xxxxxxxxx" required></div>
    <div><label for="operator-password">Temporary password *</label><input class="form-control" id="operator-password" name="operator_password" type="password" minlength="8" required autocomplete="new-password"><small>At least 8 characters. Ask them to change it under My Account.</small></div>
    <div><label for="operator-password-confirmation">Confirm password *</label><input class="form-control" id="operator-password-confirmation" name="operator_password_confirmation" type="password" minlength="8" required autocomplete="new-password"></div>
</div>
