@extends('admin.layout')
@vite('resources/css/views/admin-compliance.css')
@section('title', 'Seller Compliance')

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Active Sellers — Compliance Monitor</span></div>
    <table>
        <thead>
            <tr><th>Seller</th><th>Business Name</th><th>Category</th><th>Email</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse($sellers as $seller)
        <tr>
            <td><strong>{{ $seller->full_name }}</strong></td>
            <td>{{ $seller->business_name ?? '—' }}</td>
            <td><span class="badge badge-seller">{{ $seller->line_of_business ?? '—' }}</span></td>
            <td>{{ $seller->email }}</td>
            <td>{{ $seller->created_at->format('M d, Y') }}</td>
            <td>
                <div class="blade-inline-1">
                    <!-- Warn -->
                    <button class="btn btn-outline btn-sm" onclick="openWarn({{ $seller->id }}, '{{ $seller->full_name }}')">⚠️ Warn</button>
                    <!-- Suspend -->
                    <form method="POST" action="/admin/users/{{ $seller->id }}/status">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="suspended">
                        <button class="btn btn-danger btn-sm" onclick="return confirm('Suspend {{ $seller->full_name }}?')">Suspend</button>
                    </form>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="blade-inline-2">No sellers found.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($sellers->hasPages())
    <div class="dashboard-pagination">{{ $sellers->withQueryString()->links() }}</div>
    @endif
</div>

<!-- Warn Modal -->
<div id="warnModal" class="blade-inline-3">
    <div class="blade-inline-4">
        <h3 class="blade-inline-5">⚠️ Issue Warning to <span id="warnName"></span></h3>
        <form method="POST" id="warnForm">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Warning Message <span class="blade-inline-6">*</span></label>
                <textarea name="warning" class="form-control" placeholder="Describe the violation..." required></textarea>
            </div>
            <div class="blade-inline-7">
                <button type="button" class="btn btn-outline" onclick="closeWarn()">Cancel</button>
                <button type="submit" class="btn btn-coral">Send Warning</button>
            </div>
        </form>
    </div>
</div>

@vite('resources/js/views/admin-compliance.js')
@endsection
