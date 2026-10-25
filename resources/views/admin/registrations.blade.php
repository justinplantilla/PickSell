@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-registrations.css'])
@endsection
@section('title', 'Registrations')

@section('content')
@php
    $canExport = auth()->user()->can(\App\Auth\Permission::REPORTS_EXPORT);
    $canManage = auth()->user()->can(\App\Auth\Permission::REGISTRATIONS_MANAGE);
    $overdueDays = \App\Http\Controllers\AdminRegistrationController::OVERDUE_DAYS;
    $selectable = $canManage && in_array($status, ['pending', 'all'], true);
    $showDecision = $status !== 'pending';
    $tabQuery = fn (string $key) => array_filter(['status' => $key === 'pending' ? null : $key] + request()->except(['status', 'page']), fn ($v) => $v !== null && $v !== '');
@endphp
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Registration queue</span>
            <p class="oversight-description">{{ $status === 'pending' ? 'Oldest applications first. Click an applicant for documents and a quick look.' : 'Newest first. Click an applicant for documents and the decision.' }}</p>
        </div>
        @if($canExport && $matchingCount > 0)
            <a href="{{ route('admin.registrations.export', request()->except('page')) }}" class="btn btn-outline btn-sm list-export">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
                Export {{ number_format($matchingCount) }}
            </a>
        @endif
    </div>

    <nav class="oversight-tabs" aria-label="Application status">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('admin.registrations', $tabQuery($key)) }}" class="oversight-tab {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                {{ $label }} <span class="oversight-tab-count">{{ number_format($statusCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.registrations') }}" class="list-toolbar" role="search" aria-label="Filter registrations">
        @if($status !== 'pending')<input type="hidden" name="status" value="{{ $status }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search applicants</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Name, email or business…">
        </label>
        <select name="role" class="filter-select" aria-label="Role" data-submit-on-change>
            <option value="all">All roles</option>
            @foreach(\App\Http\Controllers\AdminRegistrationController::ROLE_FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="date" class="filter-select" aria-label="Submitted" data-submit-on-change>
            <option value="any">Any date</option>
            @foreach($dateFilters as $value => $label)
                <option value="{{ $value }}" @selected($date === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($overdueCount > 0 && in_array($status, ['pending', 'all'], true) && $date !== 'older_7d')
        <p class="registration-overdue" role="status">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
            <strong>{{ number_format($overdueCount) }} {{ $overdueCount === 1 ? 'application has' : 'applications have' }} waited over {{ $overdueDays }} days.</strong>
            <a href="{{ route('admin.registrations', ['date' => 'older_7d']) }}">Show only these</a>
        </p>
    @endif

    @if($chips)
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="list-chip">{{ $chip['label'] }} <span aria-hidden="true">✕</span><span class="sr-only">(remove filter)</span></a>
            @endforeach
            <a href="{{ route('admin.registrations', $status !== 'pending' ? ['status' => $status] : []) }}" class="list-chips-clear">Clear all</a>
        </div>
    @endif

    @if(session('bulkSkippedCount', 0) > 0)
        <div class="alert alert-warning list-alert" role="status">
            {{ session('bulkSkippedCount') }} application(s) were skipped:
            <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
        </div>
    @endif

    @if($users->isEmpty())
        <div class="oversight-empty"><strong>No applications found</strong>{{ $status === 'pending' && ! $chips ? 'The queue is clear.' : 'Try different filters.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table registration-table" data-list-table>
                <thead>
                    <tr>
                        @if($selectable)<th class="list-check"><input type="checkbox" data-select-page aria-label="Select all pending applications on this page"></th>@endif
                        <th>Applicant</th>
                        <th>Role</th>
                        <th>Documents</th>
                        <th>{{ $status === 'pending' ? 'Waiting' : 'Submitted' }}</th>
                        @if($showDecision)<th>Decision</th>@endif
                        <th class="list-chevron"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    @php
                        $docs = $documentStatus[$user->id];
                        $review = $user->latestRegistrationReview;
                        $isPending = $user->status === 'pending';
                        $overdue = $isPending && $user->created_at->lt(now()->subDays($overdueDays));
                        $sameName = $duplicates[$user->id] ?? 0;
                        $reapplied = $isPending && $review;
                    @endphp
                    <tr class="list-row {{ $overdue ? 'is-stale' : '' }}" data-list-row data-preview-url="{{ route('admin.registrations.preview', $user) }}">
                        @if($selectable)
                            <td class="list-check">@if($isPending)<input type="checkbox" value="{{ $user->id }}" data-select-row aria-label="Select {{ $user->full_name }}">@endif</td>
                        @endif
                        <td class="list-cell-main" data-label="Applicant">
                            <a href="{{ route('admin.registrations.show', $user) }}" class="list-title" data-row-link>{{ $user->full_name }}</a>
                            <span class="oversight-meta registration-contact">{{ $user->email }}@if($user->business_name) · {{ $user->business_name }}@endif</span>
                            @if($sameName)
                                <span class="oversight-flag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>Same name as {{ $sameName }} other {{ $sameName === 1 ? 'account' : 'accounts' }}</span>
                            @endif
                            @if($reapplied)
                                <span class="oversight-flag is-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>Re-applied · previously {{ $review->decision }} {{ $review->reviewed_at->format('M d') }}</span>
                            @endif
                        </td>
                        <td class="list-status list-cell-aside" data-label="Role">
                            <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                            <span class="oversight-meta">{{ \App\Http\Controllers\AdminRegistrationController::sellerTypeLabel($user) ?? 'Personal account' }}</span>
                        </td>
                        <td class="list-cell-sub registration-docs {{ $docs['complete'] ? 'is-complete' : 'is-missing' }}" data-label="Documents">
                            <span class="list-line">
                                <span aria-hidden="true">{{ $docs['complete'] ? '✓' : '⚠' }}</span>
                                {{ $docs['uploaded'] }} of {{ $docs['required'] }} {{ $docs['required'] === 1 ? 'document' : 'documents' }}
                            </span>
                            <span class="oversight-meta">{{ $docs['complete'] ? 'Complete' : 'Missing: '.implode(', ', $docs['missing']) }}</span>
                        </td>
                        <td class="list-age list-cell-sub-aside {{ $overdue ? 'is-overdue' : '' }}" data-label="{{ $status === 'pending' ? 'Waiting' : 'Submitted' }}">
                            <time datetime="{{ $user->created_at->toIso8601String() }}" title="Submitted {{ $user->created_at->format('M d, Y h:i A') }}">@if($overdue)<span aria-hidden="true">⚠ </span>@endif{{ $user->created_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) }}</time>
                            <span class="oversight-meta">{{ $user->created_at->format('M d, Y') }}</span>
                        </td>
                        @if($showDecision)
                            <td class="list-status list-cell-wide" data-label="Decision">
                                <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span>
                                <span class="oversight-meta">
                                    @if($isPending)
                                        Awaiting review
                                    @elseif($review)
                                        by {{ $review->reviewer?->full_name ?? 'Admin' }} · {{ $review->reviewed_at->format('M d, Y') }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </td>
                        @endif
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="dashboard-pagination">{{ $users->links() }}</div>@endif
    @endif
</div>

@if($selectable && $users->isNotEmpty())
    <div class="list-bulkbar" data-bulkbar data-matching-count="0" data-noun="application" hidden>
        <div class="list-bulkbar-summary">
            <strong data-bulk-count>0 selected</strong>
        </div>
        <div class="list-bulkbar-actions">
            <button type="button" class="btn btn-coral btn-sm" data-bulk-open="bulk-disapprove-dialog">Disapprove</button>
            <button type="button" class="list-link-button" data-bulk-clear>Clear</button>
        </div>
    </div>

    <dialog class="list-dialog" id="bulk-disapprove-dialog" data-bulk-dialog aria-labelledby="bulk-disapprove-title">
        <form method="POST" action="{{ route('admin.registrations.bulk-disapprove') }}" class="bulk-action-form" data-no-confirm>
            @csrf
            <h2 id="bulk-disapprove-title">Disapprove applications</h2>
            <p class="oversight-description"><span data-bulk-scope>Applies to the selected applications.</span> For spam, test or duplicate sign-ups. Approvals are made one at a time on each application, after the checklist.</p>
            <div data-bulk-ids data-ids-name="user_ids[]"></div>
            <input type="hidden" name="matching_count" value="0" data-bulk-matching>
            <label class="form-label" for="bulk-disapprove-reason">Reason sent to each applicant (required)</label>
            <textarea id="bulk-disapprove-reason" name="reason" class="form-control" minlength="10" maxlength="1000" required placeholder="e.g. This looks like a test or duplicate registration. Please sign up again with your real details."></textarea>
            <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> <span data-bulk-confirm-text>Apply to the selected applications.</span> Each applicant is emailed this reason.</label>
            <div class="list-dialog-actions">
                <button type="button" class="btn btn-outline btn-sm" data-bulk-cancel>Cancel</button>
                <button type="submit" class="btn btn-coral btn-sm">Disapprove</button>
            </div>
        </form>
    </dialog>
@endif

<div class="list-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="list-drawer" data-list-drawer hidden aria-labelledby="list-drawer-title" role="dialog" aria-modal="true">
    <button type="button" class="list-drawer-close" data-drawer-close aria-label="Close applicant preview">&times;</button>
    <div data-drawer-body><p class="oversight-empty">Loading…</p></div>
</aside>
@endsection

@section('scripts')
@vite('resources/js/components/admin-list.js')
@endsection
