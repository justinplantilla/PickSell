{{-- Account summary loaded into the side panel on the user list. Its action buttons open the list page's status dialogs. --}}
@php
    $actionLabels = \App\Services\Admin\UserModerationService::ACTION_LABELS;
    $statusLabel = fn (?string $status) => $status === 'approved' ? 'Active' : ucfirst((string) $status);
    $address = collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->join(', ');
@endphp
<div class="list-preview user-preview">
    <p class="list-preview-kicker">{{ ucfirst($user->role) }} account</p>
    <h2 id="list-drawer-title">{{ $user->full_name }}</h2>
    <div class="list-preview-status">
        <span class="badge badge-{{ $user->status }}">{{ $statusLabel($user->status) }}</span>
        <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
        <span class="oversight-meta">Joined {{ $user->created_at->format('M d, Y') }}</span>
    </div>

    <dl class="account-fields user-preview-fields">
        <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
        <div><dt>Mobile</dt><dd>{{ $user->contact_no ?: '—' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $address ?: '—' }}</dd></div>
        @if($user->role === 'seller')
            <div><dt>Shop</dt><dd>{{ $user->business_name ?: '—' }}</dd></div>
            <div><dt>Seller type</dt><dd>{{ \App\Support\SellerTypes::label($user->seller_type) ?? '—' }}</dd></div>
        @endif
    </dl>

    @if($orderSummary)
        <h3 class="list-preview-heading">{{ $orderSummary['perspective'] }}</h3>
        <dl class="user-preview-stats">
            <div><dt>Orders</dt><dd>{{ number_format($orderSummary['total']) }}</dd></div>
            <div><dt>Completed</dt><dd>{{ number_format($orderSummary['completed']) }}</dd></div>
            <div><dt>In progress</dt><dd>{{ number_format($orderSummary['in_progress']) }}</dd></div>
            <div><dt>Problems</dt><dd>{{ number_format($orderSummary['problems']) }}</dd></div>
            <div class="is-wide"><dt>Completed value</dt><dd>₱{{ number_format($orderSummary['completed_value'], 2) }}</dd></div>
        </dl>
    @endif

    <h3 class="list-preview-heading">Status history</h3>
    @if($statusHistory->isEmpty())
        <p class="oversight-meta">No status changes yet.</p>
    @else
        <ol class="user-preview-history">
            @foreach($statusHistory as $change)
                <li>
                    <strong>{{ $statusLabel($change->from_status) }} → {{ $statusLabel($change->to_status) }}</strong>
                    <span class="oversight-meta">{{ $change->created_at->format('M d, Y h:i A') }} · {{ $change->changer?->full_name ?? 'System' }}</span>
                    @if($change->reason)<p class="user-preview-reason">{{ $change->reason }}</p>@endif
                </li>
            @endforeach
        </ol>
    @endif

    <div class="user-preview-actions">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-coral btn-sm">Open full profile</a>
        @if($user->status === 'pending')
            @can(\App\Auth\Permission::REGISTRATIONS_VIEW)
                <a href="{{ route('admin.registrations.show', $user) }}" class="btn btn-outline btn-sm">Review in Registrations</a>
            @endcan
        @endif
        @foreach($transitions as $to)
            <button type="button" class="btn btn-sm {{ $to === 'approved' ? 'btn-outline' : ($to === 'deactivated' ? 'btn-outline user-preview-danger' : 'btn-outline') }}"
                    data-row-dialog="user-status-{{ $to }}" data-action-url="{{ route('admin.users.status', $user) }}"
                    data-row-id="{{ $user->id }}" data-row-name="{{ $user->full_name }}">{{ $actionLabels[$to] }}…</button>
        @endforeach
    </div>
</div>
