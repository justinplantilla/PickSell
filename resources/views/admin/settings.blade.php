@extends('admin.layout')
@vite('resources/css/views/admin-settings.css')
@section('title', 'Platform Settings')

@section('content')
<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-1"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>Post Announcement</span></div>
        <div class="card-body">
            <form method="POST" action="/admin/settings">
                @csrf
                <div class="form-group">
                    <label class="form-label">Announcement Title</label>
                    <input type="text" name="announcement_title" class="form-control" placeholder="e.g. System Maintenance Notice" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Announcement Message</label>
                    <textarea name="announcement" class="form-control" rows="4" placeholder="Write your announcement here..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Audience</label>
                    <select name="audience" class="form-control">
                        <option value="all">All Users</option>
                        <option value="buyer">Buyers Only</option>
                        <option value="seller">Sellers Only</option>
                        <option value="courier">Couriers Only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-coral">Post Announcement</button>
            </form>

            @if($announcements->isNotEmpty())
            <div class="blade-inline-2">
                <div class="blade-inline-3">Posted Announcements</div>
                @foreach($announcements as $ann)
                <div class="blade-inline-4">
                    <div class="blade-inline-5">
                        <div class="blade-inline-6">{{ $ann->title }} <span class="blade-inline-7">{{ ucfirst($ann->audience) }}</span></div>
                        <div class="blade-inline-8">{{ $ann->message }}</div>
                        <div class="blade-inline-9">{{ $ann->created_at->format('M d, Y h:i A') }} &nbsp;·&nbsp; {{ $ann->active ? 'Active' : 'Hidden' }}</div>
                    </div>
                    <div class="blade-inline-10">
                        <form method="POST" action="/admin/settings/announcements/{{ $ann->id }}/toggle">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline btn-sm">{{ $ann->active ? 'Hide' : 'Show' }}</button>
                        </form>
                        <form method="POST" action="/admin/settings/announcements/{{ $ann->id }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Platform Policies</span></div>
        <div class="card-body">
            <form method="POST" action="/admin/settings">
                @csrf
                <div class="form-group">
                    <label class="form-label">Terms of Service</label>
                    <textarea name="policy" class="form-control" rows="6" placeholder="Write Terms of Service content...">{{ $tos }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Privacy Policy</label>
                    <textarea name="privacy" class="form-control" rows="6" placeholder="Write Privacy Policy content...">{{ $privacy }}</textarea>
                </div>
                <button type="submit" class="btn btn-coral">Save Policies</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-11"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a7.01 7.01 0 0 0-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.47.47 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.37 1.04.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.57 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>General Settings</span></div>
    <div class="card-body">
        <form method="POST" action="/admin/settings">
            @csrf
            <div class="blade-inline-12">
                <div class="form-group">
                    <label class="form-label">Platform Name</label>
                    <input type="text" name="platform_name" class="form-control" value="{{ $platformName ?? 'PickSell' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Support Email</label>
                    <input type="email" name="support_email" class="form-control" value="{{ $supportEmail ?? 'support@picksell.ph' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Commission Rate (%)</label>
                    <input type="number" name="commission_rate" class="form-control" value="{{ $commissionRate ?? 10 }}" min="0" max="100" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Max File Upload Size (MB)</label>
                    <input type="number" name="max_file_upload_mb" class="form-control" value="{{ $maxFileUploadMb ?? 5 }}" min="1" max="500">
                </div>
            </div>
            <button type="submit" class="btn btn-coral"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-13"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg>Save Settings</button>
        </form>
    </div>
</div>
@endsection
