@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-users.css'])
@endsection
@section('title', 'User Accounts')

@section('content')
@php
    $canExport = auth()->user()->can(\App\Auth\Permission::REPORTS_EXPORT);
    $canManage = auth()->user()->can(\App\Auth\Permission::USERS_MANAGE);
    $canReviewRegistrations = auth()->user()->can(\App\Auth\Permission::REGISTRATIONS_VIEW);
    $actionLabels = \App\Services\Admin\UserModerationService::ACTION_LABELS;
    $statusLabel = fn (string $status) => $status === 'approved' ? 'Active' : ucfirst($status);
    $tabQuery = fn (string $key) => array_filter(['status' => $key === 'all' ? null : $key] + request()->except(['status', 'filter_status', 'page']), fn ($v) => $v !== null && $v !== '');
    $icons = [
        'view' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'review' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>',
        'approved' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'suspended' => '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
        'deactivated' => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
    ];
@endphp
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">User accounts</span>
            <p class="oversight-description">Buyers and sellers. Click an account for a quick look; use ⋯ for account actions. Every change is recorded with its reason.</p>
        </div>
        @if($canExport && $matchingCount > 0)
            <a href="{{ route('admin.users.export', request()->except('page')) }}" class="btn btn-outline btn-sm list-export">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
                Export {{ number_format($matchingCount) }}
            </a>
        @endif
    </div>

    <nav class="oversight-tabs" aria-label="Account status">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('admin.users', $tabQuery($key)) }}" class="oversight-tab {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                {{ $label }} <span class="oversight-tab-count">{{ number_format($statusCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.users') }}" class="list-toolbar" role="search" aria-label="Filter user accounts"
          data-saved-filter-scope="users" data-saved-filter-user="{{ auth()->id() }}" data-saved-filter-mode="menu">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search users</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Name, email, business or phone…">
        </label>
        <select name="role" class="filter-select" aria-label="Role" data-submit-on-change>
            <option value="all">All roles</option>
            @foreach(\App\Http\Controllers\AdminUserController::ROLE_FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <details class="list-popover">
            <summary class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                More filters @if($moreFiltersCount)<span class="list-count-pill">{{ $moreFiltersCount }}</span>@endif
            </summary>
            <div class="list-popover-panel">
                <label class="list-field">
                    <span>Joined</span>
                    <select name="joined" class="filter-select">
                        <option value="any">Any join date</option>
                        @foreach($joinedFilters as $value => $label)
                            <option value="{{ $value }}" @selected($joined === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="list-popover-actions">
                    <button type="submit" class="btn btn-coral btn-sm">Apply filters</button>
                </div>
            </div>
        </details>
        <div class="list-views" data-saved-filter-mount></div>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($chips)
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="list-chip">{{ $chip['label'] }} <span aria-hidden="true">✕</span><span class="sr-only">(remove filter)</span></a>
            @endforeach
            <a href="{{ route('admin.users', $status !== 'all' ? ['status' => $status] : []) }}" class="list-chips-clear">Clear all</a>
        </div>
    @endif

    @if(session('bulkSkippedCount', 0) > 0)
        <div class="alert alert-warning list-alert" role="status">
            {{ session('bulkSkippedCount') }} account(s) were skipped:
            <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
            @if(session('bulkSkippedCount') > count(session('bulkSkipped', [])))<p>Additional skipped accounts are omitted from this summary.</p>@endif
        </div>
    @endif

    @if($users->isEmpty())
        <div class="oversight-empty"><strong>No accounts found</strong>Try different filters.</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table user-table" data-list-table>
                <thead>
                    <tr>
                        @if($canManage)<th class="list-check"><input type="checkbox" data-select-page aria-label="Select all accounts on this page"></th>@endif
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="list-number">Orders</th>
                        <th>Joined</th>
                        <th class="list-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    @php
                        $stats = \App\Http\Controllers\AdminUserController::orderStats($user);
                        $transitions = \App\Http\Controllers\AdminUserController::transitionsFor($user);
                        $changedAt = $user->status_changed_at ? \Illuminate\Support\Carbon::parse($user->status_changed_at) : null;
                        $since = ($changedAt ?? $user->created_at)->format('M d, Y');
                    @endphp
                    <tr class="list-row" data-list-row data-preview-url="{{ route('admin.users.preview', $user) }}">
                        @if($canManage)
                            <td class="list-check"><input type="checkbox" value="{{ $user->id }}" data-select-row aria-label="Select {{ $user->full_name }}"></td>
                        @endif
                        <td class="list-cell-main" data-label="User">
                            <a href="{{ route('admin.users.show', $user) }}" class="list-title" data-row-link>{{ $user->full_name }}</a>
                            <span class="oversight-meta user-contact">{{ $user->email }}@if($user->business_name) · {{ $user->business_name }}@endif</span>
                        </td>
                        <td class="list-status list-cell-sub" data-label="Role">
                            <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                            <span class="oversight-meta">{{ $user->role === 'seller' ? (\App\Support\SellerTypes::label($user->seller_type) ?? 'Seller') : 'Personal' }}</span>
                        </td>
                        <td class="list-status list-cell-wide" data-label="Status">
                            <span class="badge badge-{{ $user->status }}">{{ $statusLabel($user->status) }}</span>
                            @if($user->status === 'pending')
                                @if($canReviewRegistrations)
                                    <a href="{{ route('admin.registrations.show', $user) }}" class="oversight-meta user-status-link">Review in queue →</a>
                                @else
                                    <span class="oversight-meta">Awaiting registration review</span>
                                @endif
                            @elseif(in_array($user->status, ['suspended', 'deactivated', 'disapproved'], true) && $user->status_reason)
                                <span class="oversight-meta user-status-reason" title="{{ $user->status_reason }}">{{ \Illuminate\Support\Str::limit($user->status_reason, 48) }}</span>
                            @else
                                <span class="oversight-meta">Since {{ $since }}</span>
                            @endif
                        </td>
                        <td class="list-number list-cell-sub-aside" data-label="Orders">
                            <span class="list-line">{{ $stats['count'] === null ? '—' : number_format($stats['count']) }}</span>
                            <span class="oversight-meta">{{ $stats['value'] > 0 ? '₱'.number_format($stats['value'], 2) : '—' }}</span>
                        </td>
                        <td class="list-age list-cell-sub" data-label="Joined">
                            <time datetime="{{ $user->created_at->toIso8601String() }}" title="Joined {{ $user->created_at->format('M d, Y h:i A') }}">{{ $user->created_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) }}</time>
                            <span class="oversight-meta">{{ $user->created_at->format('M d, Y') }}</span>
                        </td>
                        <td class="list-actions">
                            <details class="list-menu">
                                <summary aria-label="Actions for {{ $user->full_name }}" title="Actions"><span aria-hidden="true">⋯</span></summary>
                                <div class="list-menu-panel">
                                    <a href="{{ route('admin.users.show', $user) }}" class="list-menu-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $icons['view'] !!}</svg>View profile</a>
                                    @if($user->status === 'pending' && $canReviewRegistrations)
                                        <a href="{{ route('admin.registrations.show', $user) }}" class="list-menu-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $icons['review'] !!}</svg>Review in Registrations</a>
                                    @endif
                                    @foreach($transitions as $to)
                                        @if($to === 'deactivated')<div class="list-menu-divider" role="separator"></div>@endif
                                        <button type="button" class="list-menu-item {{ $to === 'deactivated' ? 'is-danger' : '' }}"
                                                data-row-dialog="user-status-{{ $to }}" data-action-url="{{ route('admin.users.status', $user) }}"
                                                data-row-id="{{ $user->id }}" data-row-name="{{ $user->full_name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $icons[$to] !!}</svg>{{ $actionLabels[$to] }}…
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="dashboard-pagination">{{ $users->links() }}</div>@endif
    @endif
</div>

@include('admin.users._status-dialogs')

@if($canManage && $matchingCount > 0)
    <div class="list-bulkbar" data-bulkbar data-matching-count="{{ $matchingCount }}" data-noun="account" hidden>
        <div class="list-bulkbar-summary">
            <strong data-bulk-count>0 selected</strong>
            @if($matchingCount > $users->count())
                <button type="button" class="list-link-button" data-select-all-matching>Select all {{ number_format($matchingCount) }} matching</button>
            @endif
        </div>
        <div class="list-bulkbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-bulk-open="bulk-users-approved">Reactivate</button>
            <button type="button" class="btn btn-outline btn-sm" data-bulk-open="bulk-users-suspended">Suspend</button>
            <button type="button" class="btn btn-coral btn-sm" data-bulk-open="bulk-users-deactivated">Deactivate</button>
            @if($canExport)
                <a href="{{ route('admin.users.export', request()->except('page')) }}" class="btn btn-outline btn-sm" data-bulk-export data-ids-param="user_ids" data-export-base="{{ route('admin.users.export', request()->except('page')) }}">Export</a>
            @endif
            <button type="button" class="list-link-button" data-bulk-clear>Clear</button>
        </div>
    </div>

    @foreach(['approved' => 'Reactivated accounts can sign in again.', 'suspended' => 'Suspended accounts cannot sign in until reactivated.', 'deactivated' => 'Deactivated accounts cannot sign in until reactivated.'] as $to => $note)
        <dialog class="list-dialog" id="bulk-users-{{ $to }}" data-bulk-dialog aria-labelledby="bulk-users-{{ $to }}-title">
            <form method="POST" action="{{ route('admin.users.bulk-status') }}" class="bulk-action-form" data-no-confirm>
                @csrf
                <h2 id="bulk-users-{{ $to }}-title">{{ $actionLabels[$to] }} accounts</h2>
                <p class="oversight-description"><span data-bulk-scope>Applies to the selected accounts.</span> {{ $note }} Accounts that cannot change are skipped.</p>
                <div data-bulk-ids data-ids-name="user_ids[]"></div>
                <input type="hidden" name="to_status" value="{{ $to }}">
                <input type="hidden" name="role" value="{{ $role }}">
                <input type="hidden" name="filter_status" value="{{ $status }}">
                <input type="hidden" name="joined" value="{{ $joined }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="matching_count" value="{{ $matchingCount }}" data-bulk-matching>
                <label class="form-label" for="bulk-users-{{ $to }}-reason">Reason (required, kept in each account's history)</label>
                <textarea id="bulk-users-{{ $to }}-reason" name="reason" class="form-control" minlength="10" maxlength="1000" required></textarea>
                <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> <span data-bulk-confirm-text>Apply to the selected accounts.</span></label>
                <div class="list-dialog-actions">
                    <button type="button" class="btn btn-outline btn-sm" data-bulk-cancel>Cancel</button>
                    <button type="submit" class="btn {{ $to === 'approved' ? 'btn-success' : 'btn-danger' }} btn-sm">{{ $actionLabels[$to] }}</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endif

<div class="list-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="list-drawer" data-list-drawer hidden aria-labelledby="list-drawer-title" role="dialog" aria-modal="true">
    <button type="button" class="list-drawer-close" data-drawer-close aria-label="Close account preview">&times;</button>
    <div data-drawer-body><p class="oversight-empty">Loading…</p></div>
</aside>
@endsection

@section('scripts')
@vite('resources/js/components/admin-list.js')
@endsection
