@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-complaint-detail.css')
@endsection
@section('title', 'Review Complaint #{{ $complaint->id }}')

@section('content')
<div class="blade-inline-1">
    <a href="/admin/complaints" class="btn btn-outline btn-sm">← Back to Complaints</a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Complaint Details</span></div>
        <div class="card-body">
            <table class="blade-inline-2">
                <tr><td class="blade-inline-3">Filed By</td><td>{{ $complaint->filer->full_name ?? '—' }} ({{ ucfirst($complaint->filer->role ?? '') }})</td></tr>
                <tr><td class="blade-inline-4">Against</td><td>{{ $complaint->against->full_name ?? 'N/A' }}</td></tr>
                <tr><td class="blade-inline-5">Subject</td><td>{{ $complaint->subject }}</td></tr>
                <tr><td class="blade-inline-6">Filed On</td><td>{{ $complaint->created_at->format('M d, Y h:i A') }}</td></tr>
                <tr><td class="blade-inline-7">Status</td>
                    <td>
                        @php $badgeMap = ['open'=>'badge-pending','under_review'=>'badge-pending','resolved'=>'badge-approved','dismissed'=>'badge-deactivated']; @endphp
                        <span class="badge {{ $badgeMap[$complaint->status] ?? 'badge-pending' }}">{{ ucfirst(str_replace('_',' ',$complaint->status)) }}</span>
                    </td>
                </tr>
            </table>
            <div class="blade-inline-8">
                <div class="blade-inline-9">Details</div>
                <div class="blade-inline-10">{{ $complaint->details }}</div>
            </div>
            @if($complaint->evidence_path)
            <div class="blade-inline-11">
                <div class="blade-inline-12">Evidence</div>
                <a href="{{ asset('storage/'.$complaint->evidence_path) }}" target="_blank" class="btn btn-outline btn-sm">📎 View Evidence</a>
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Admin Resolution</span></div>
        <div class="card-body">
            <form method="POST" action="/admin/complaints/{{ $complaint->id }}">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label class="form-label">Update Status</label>
                    <select name="status" class="form-control">
                        <option value="open"         {{ $complaint->status==='open'?'selected':'' }}>Open</option>
                        <option value="under_review" {{ $complaint->status==='under_review'?'selected':'' }}>Under Review</option>
                        <option value="resolved"     {{ $complaint->status==='resolved'?'selected':'' }}>Resolved</option>
                        <option value="dismissed"    {{ $complaint->status==='dismissed'?'selected':'' }}>Dismissed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Notes / Resolution</label>
                    <textarea name="admin_notes" class="form-control" rows="6" placeholder="Write your resolution or coordination notes...">{{ $complaint->admin_notes }}</textarea>
                </div>
                <div class="blade-inline-13">
                    <button type="submit" class="btn btn-coral">💾 Save Resolution</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
