@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-users.css'])
@endsection
@section('title', $user->full_name)

@php
    $statusLabel = fn ($status) => $status === 'approved' ? 'Active' : ucfirst((string) $status);
    $actionLabels = \App\Services\Admin\UserModerationService::ACTION_LABELS;
    $needsConfirmation = \App\Services\Admin\UserModerationService::REQUIRES_CONFIRMATION;
@endphp

@section('content')
<p class="account-back"><a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm">← Back to user accounts</a></p>

<div class="account-layout">
    {{-- Profile --}}
    <section class="card" aria-labelledby="profile-heading">
        <div class="card-header">
            <span class="card-title" id="profile-heading">Profile</span>
            <span class="badge badge-{{ $user->role }}">{{ $user->role === 'courier' ? 'Rider' : ucfirst($user->role) }}</span>
        </div>
        @php
            $fields = array_filter([
                'Name' => $user->full_name,
                'Email' => $user->email,
                'Contact no.' => $user->contact_no,
                'Address' => collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter(fn ($v) => is_string($v) && $v !== '')->join(', '),
                'Business' => $user->business_name ? $user->business_name . ($user->line_of_business ? ' · ' . $user->line_of_business : '') : null,
                'Vehicle' => $user->vehicle_type ? $user->vehicle_type . ($user->plate_number ? ' · ' . $user->plate_number : '') : null,
                'Joined' => $user->created_at->format('M d, Y') . ' (' . $user->created_at->diffForHumans() . ')',
            ]);
        @endphp
        <dl class="account-fields">
            @foreach($fields as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
        </dl>
    </section>

    {{-- Account status + admin actions --}}
    <section class="card" aria-labelledby="status-heading">
        <div class="card-header">
            <span class="card-title" id="status-heading">Account status</span>
            <span class="badge badge-{{ $user->status }}">{{ $statusLabel($user->status) }}</span>
        </div>
        <div class="account-actions">
            @if(in_array($user->status, ['pending', 'disapproved'], true))
                <p class="oversight-description">This is a registration application. Decide it in <a href="{{ route('admin.registrations.show', $user) }}">Registrations</a>.</p>
            @elseif($transitions->isEmpty())
                <p class="oversight-description">{{ auth()->user()->can(\App\Auth\Permission::USERS_MANAGE) ? 'No status changes are available for this account.' : 'Changing account status requires the user management permission.' }}</p>
            @else
                <h3 class="account-subheading">Admin actions</h3>
                @foreach($transitions as $to)
                    @php $confirm = in_array($to, $needsConfirmation, true); $open = old('status') === $to; @endphp
                    <details class="account-action {{ $confirm ? 'is-blocking' : '' }}" @if($open) open @endif>
                        <summary>{{ $actionLabels[$to] }} account</summary>
                        <form method="POST" action="{{ route('admin.users.status', $user) }}" class="account-action-form">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $to }}">
                            @if($confirm)
                                <p class="account-warning">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                                    {{ $user->full_name }} will immediately lose access to PickSell and cannot sign in until an admin reactivates the account. They will be notified by email.
                                </p>
                            @endif
                            <label class="form-label" for="reason-{{ $to }}">Reason{{ $confirm ? '' : ' (optional)' }}</label>
                            <textarea id="reason-{{ $to }}" name="reason" class="form-control" rows="2" maxlength="1000" @if($confirm) required minlength="10" @endif
                                @if($open) @error('reason') aria-invalid="true" aria-describedby="reason-error-{{ $to }}" @enderror @endif>{{ $open ? old('reason') : '' }}</textarea>
                            @if($open) @error('reason')<span class="account-error" id="reason-error-{{ $to }}">{{ $message }}</span>@enderror @endif
                            @if($confirm)
                                <label class="account-confirm">
                                    <input type="checkbox" name="confirm" value="1" required>
                                    I confirm I want to {{ strtolower($actionLabels[$to]) }} this account.
                                </label>
                                @if($open) @error('confirm')<span class="account-error">{{ $message }}</span>@enderror @endif
                            @endif
                            <button type="submit" class="btn btn-sm {{ $confirm ? 'btn-danger' : 'btn-success' }}">{{ $actionLabels[$to] }} account</button>
                        </form>
                    </details>
                @endforeach
            @endif
        </div>

        <h3 class="account-subheading account-subheading-padded">Status history</h3>
        @if($statusHistory->isEmpty())
            <p class="oversight-description account-pad">No status changes recorded yet.</p>
        @else
            <ol class="account-timeline">
                @foreach($statusHistory as $change)
                    <li>
                        <span class="account-transition">{{ $statusLabel($change->from_status ?? 'new') }} → <strong>{{ $statusLabel($change->to_status) }}</strong></span>
                        <span class="oversight-meta">by {{ $change->changer?->full_name ?? 'Unknown' }}{{ $change->changer && $change->changer->role !== 'admin' ? ' (' . $change->changer->role . ')' : '' }} · <time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->format('M d, Y h:i A') }}</time></span>
                        @if($change->reason)<p class="account-reason">{{ $change->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Registration history --}}
    <section class="card" aria-labelledby="registration-heading">
        <div class="card-header">
            <span class="card-title" id="registration-heading">Registration history</span>
            @can('viewRegistration', $user)<a href="{{ route('admin.registrations.show', $user) }}" class="oversight-description">Open application</a>@endcan
        </div>
        @if($registrationReviews->isEmpty())
            <p class="oversight-description account-pad">No recorded registration reviews{{ $user->status !== 'pending' ? ' (decided before review history was kept)' : '' }}.</p>
        @else
            <ol class="account-timeline">
                @foreach($registrationReviews as $review)
                    <li>
                        <span class="account-transition"><span class="badge badge-{{ $review->decision }}">{{ ucfirst($review->decision) }}</span></span>
                        <span class="oversight-meta">by {{ $review->reviewer?->full_name ?? 'Admin' }} · {{ $review->reviewed_at->format('M d, Y h:i A') }}</span>
                        @if($review->reason)<p class="account-reason">{{ $review->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Order summary --}}
    @if($orderSummary)
    <section class="card" aria-labelledby="orders-heading">
        <div class="card-header"><span class="card-title" id="orders-heading">Order summary · {{ $orderSummary['perspective'] }}</span></div>
        <dl class="account-stats">
            <div><dt>Total</dt><dd class="oversight-number">{{ number_format($orderSummary['total']) }}</dd></div>
            <div><dt>Completed</dt><dd class="oversight-number">{{ number_format($orderSummary['completed']) }}</dd></div>
            <div><dt>In progress</dt><dd class="oversight-number">{{ number_format($orderSummary['in_progress']) }}</dd></div>
            <div><dt>Cancelled / failed / returned</dt><dd class="oversight-number">{{ number_format($orderSummary['problems']) }}</dd></div>
            @if($orderSummary['completed_value'] !== null)
                <div><dt>Completed value</dt><dd class="oversight-number">₱{{ number_format($orderSummary['completed_value'], 2) }}</dd></div>
            @endif
        </dl>
        @if($orderSummary['recent']->isNotEmpty())
            <div class="oversight-table-wrap">
                <table>
                    <thead><tr><th>Recent order</th><th>Amount</th><th>Status</th><th>Placed</th></tr></thead>
                    <tbody>
                    @foreach($orderSummary['recent'] as $order)
                        <tr>
                            <td>
                                @can(\App\Auth\Permission::ORDERS_VIEW)<a href="{{ route('admin.orders', ['search' => $order->order_number]) }}">{{ $order->order_number }}</a>@else{{ $order->order_number }}@endcan
                                <span class="oversight-meta">{{ $order->product_name }}</span>
                            </td>
                            <td class="oversight-number">₱{{ number_format($order->amount, 2) }}</td>
                            <td><x-admin-order-status :status="$order->status" /></td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @endif

    {{-- Complaint history --}}
    <section class="card" aria-labelledby="complaints-heading">
        <div class="card-header"><span class="card-title" id="complaints-heading">Complaint history</span></div>
        @if($complaints->isEmpty())
            <p class="oversight-description account-pad">No complaints filed by or against this account.</p>
        @else
            <ul class="account-list">
                @foreach($complaints as $complaint)
                    <li>
                        <span>
                            @can(\App\Auth\Permission::COMPLAINTS_VIEW)<a href="{{ route('admin.complaints.show', $complaint) }}">#{{ $complaint->id }} {{ $complaint->subject }}</a>@else#{{ $complaint->id }} {{ $complaint->subject }}@endcan
                            <span class="oversight-meta">{{ $complaint->filed_by === $user->id ? 'Filed by this user' . ($complaint->against ? ' against ' . $complaint->against->full_name : '') : 'Filed against this user by ' . ($complaint->filer?->full_name ?? 'unknown') }} · {{ $complaint->created_at->format('M d, Y') }}</span>
                        </span>
                        <span class="badge badge-{{ in_array($complaint->status, ['resolved', 'dismissed'], true) ? 'approved' : 'pending' }}">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Compliance history (sellers) --}}
    @if($user->role === 'seller')
    <section class="card" aria-labelledby="compliance-heading">
        <div class="card-header">
            <span class="card-title" id="compliance-heading">Compliance history</span>
            @can('viewSellerCompliance', $user)<a href="{{ route('admin.compliance.seller', $user) }}" class="oversight-description">Open seller compliance</a>@endcan
        </div>
        @if($compliance->isEmpty())
            <p class="oversight-description account-pad">No warnings, suspensions or compliance cases on record.</p>
        @else
            <ul class="account-list">
                @foreach($compliance as $entry)
                    <li>
                        <span>
                            <strong>{{ $entry->label }}</strong>@if($entry->reason) — {{ \Illuminate\Support\Str::limit($entry->reason, 160) }}@endif
                            <span class="oversight-meta">by {{ $entry->actor?->full_name ?? 'Admin' }} · {{ $entry->created_at->format('M d, Y h:i A') }}@if($entry->complianceCase) · case #{{ $entry->compliance_case_id }}@endif</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    @endif

    {{-- Audit history --}}
    <section class="card" aria-labelledby="audit-heading">
        <div class="card-header">
            <span class="card-title" id="audit-heading">Audit history</span>
            @can(\App\Auth\Permission::AUDIT_VIEW)<a href="{{ route('admin.audit') }}" class="oversight-description">All audit logs</a>@endcan
        </div>
        @if($auditHistory->isEmpty())
            <p class="oversight-description account-pad">No audited admin actions on this account.</p>
        @else
            <ul class="account-list">
                @foreach($auditHistory as $entry)
                    <li>
                        <span>
                            <span class="audit-action">{{ $entry->action }}</span>
                            @foreach($entry->changes ?? [] as $field => $change)<span class="oversight-meta">{{ $field }}: {{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</span>@endforeach
                            @if(!empty($entry->metadata['reason']))<span class="oversight-meta">Reason: {{ $entry->metadata['reason'] }}</span>@endif
                        </span>
                        <span class="oversight-meta">{{ $entry->actor?->full_name ?? 'System' }} · {{ $entry->created_at?->format('M d, Y h:i A') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
