@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-settings.css')
@endsection
@section('title', 'Platform Settings')

@section('content')
@unless($settingHistoryAvailable)
    <div class="alert alert-warning" role="status">
        Settings are temporarily read-only because the setting history database migration has not been applied. Announcements remain available.
    </div>
@endunless

<nav class="settings-sections" aria-label="Platform settings sections">
    <a href="#general">General</a>
    <a href="#financial">Financial</a>
    <a href="#uploads">Uploads</a>
    <a href="#policies">Policies</a>
    <a href="#announcements">Announcements</a>
    <a href="#history">Setting history</a>
</nav>

<section class="card" id="general">
    <div class="card-header"><span class="card-title">General Settings</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.save') }}">
            @csrf
            <input type="hidden" name="section" value="general">
            <fieldset class="settings-fieldset" @disabled(! $settingHistoryAvailable)>
            <div class="settings-fields">
                <div class="form-group">
                    <label class="form-label" for="platform-name">Platform name</label>
                    <input id="platform-name" type="text" name="platform_name" class="form-control" value="{{ old('platform_name', $platformName) }}" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="support-email">Support email</label>
                    <input id="support-email" type="email" name="support_email" class="form-control" value="{{ old('support_email', $supportEmail) }}" maxlength="255" required>
                </div>
            </div>
            <button type="submit" class="btn btn-coral">Save general settings</button>
            </fieldset>
        </form>
    </div>
</section>

<section class="card" id="financial">
    <div class="card-header"><span class="card-title">Financial Settings</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.save') }}">
            @csrf
            <input type="hidden" name="section" value="financial">
            <fieldset class="settings-fieldset" @disabled(! $settingHistoryAvailable)>
            <div class="form-group settings-single-field">
                <label class="form-label" for="commission-rate">Commission rate (%)</label>
                <input id="commission-rate" type="number" name="commission_rate" class="form-control" value="{{ old('commission_rate', $commissionRate) }}" min="0" max="100" step="0.01" @cannot(\App\Auth\Permission::COMMISSION_MANAGE) disabled aria-describedby="commission-rate-locked" @endcannot>
                @cannot(\App\Auth\Permission::COMMISSION_MANAGE)<small id="commission-rate-locked" class="form-hint">Requires commission management permission.</small>@endcannot
            </div>
            @can(\App\Auth\Permission::COMMISSION_MANAGE)
                <button type="submit" class="btn btn-coral">Save financial settings</button>
            @endcan
            </fieldset>
        </form>
    </div>
</section>

<section class="card" id="uploads">
    <div class="card-header"><span class="card-title">Upload Settings</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.save') }}">
            @csrf
            <input type="hidden" name="section" value="uploads">
            <fieldset class="settings-fieldset" @disabled(! $settingHistoryAvailable)>
            <div class="form-group settings-single-field">
                <label class="form-label" for="max-upload-size">Maximum file upload size (MB)</label>
                <input id="max-upload-size" type="number" name="max_file_upload_mb" class="form-control" value="{{ old('max_file_upload_mb', $maxFileUploadMb) }}" min="1" max="500" step="1" required>
                <small class="form-hint">Allowed range: 1–500 MB.</small>
            </div>
            <button type="submit" class="btn btn-coral">Save upload settings</button>
            </fieldset>
        </form>
    </div>
</section>

<section class="card" id="policies">
    <div class="card-header"><span class="card-title">Platform Policies</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.save') }}">
            @csrf
            <input type="hidden" name="section" value="policies">
            <fieldset class="settings-fieldset" @disabled(! $settingHistoryAvailable)>
            <div class="settings-fields">
                <div class="form-group">
                    <label class="form-label" for="terms-policy">Terms of Service</label>
                    <textarea id="terms-policy" name="policy" class="form-control" rows="8" maxlength="100000">{{ old('policy', $tos) }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="privacy-policy">Privacy Policy</label>
                    <textarea id="privacy-policy" name="privacy" class="form-control" rows="8" maxlength="100000">{{ old('privacy', $privacy) }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-coral">Save policies</button>
            </fieldset>
        </form>
    </div>
</section>

<section class="card" id="announcements">
    <div class="card-header"><span class="card-title">Announcements</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf
            <div class="settings-fields">
                <div class="form-group">
                    <label class="form-label" for="announcement-title">Announcement title</label>
                    <input id="announcement-title" type="text" name="title" class="form-control" maxlength="255" value="{{ old('title') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="announcement-audience">Audience</label>
                    <select id="announcement-audience" name="audience" class="form-control" required>
                        @foreach(['all' => 'All users', 'buyer' => 'Buyers only', 'seller' => 'Sellers only', 'courier' => 'Couriers only'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="announcement-message">Announcement message</label>
                <textarea id="announcement-message" name="message" class="form-control" rows="4" maxlength="1000" required>{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn btn-coral">Post announcement</button>
        </form>

        @if($announcements->isNotEmpty())
            <div class="settings-history">
                <h3>Posted announcements</h3>
                @foreach($announcements as $announcement)
                    <article class="settings-history-row">
                        <div>
                            <strong>{{ $announcement->title }}</strong>
                            <span class="settings-audience">{{ ucfirst($announcement->audience) }}</span>
                            <p>{{ $announcement->message }}</p>
                            <small>{{ $announcement->created_at->format('M d, Y h:i A') }} · {{ $announcement->active ? 'Active' : 'Hidden' }}</small>
                        </div>
                        <div class="settings-actions">
                            <form method="POST" action="{{ route('admin.announcements.toggle', $announcement) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline btn-sm">{{ $announcement->active ? 'Hide' : 'Show' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.announcements.delete', $announcement) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="card" id="history">
    <div class="card-header"><span class="card-title">Setting History</span></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Setting</th><th>Previous value</th><th>New value</th><th>Changed by</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($settingChanges as $change)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::headline($change->setting_key) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($change->old_value ?? '—', 100) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($change->new_value ?? '—', 100) }}</td>
                        <td>{{ $change->changedBy?->full_name ?? 'Unknown user' }}</td>
                        <td>{{ $change->created_at->format('M d, Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No setting changes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
