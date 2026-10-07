@extends('admin.layout')
@section('title', 'Notifications')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Notifications</span>
            <p class="oversight-description">Activity and alerts addressed to your admin account.</p>
        </div>
        @can(\App\Auth\Permission::ACCOUNT_MANAGE)
            @if(auth()->user()->unreadNotifications()->exists())
                <button type="button" class="btn btn-outline btn-sm" onclick="markAdminNotificationsRead(); document.querySelectorAll('.notification-row.is-unread').forEach(row => row.classList.remove('is-unread')); this.remove();">Mark all as read</button>
            @endif
        @endcan
    </div>
    @if($notifications->isEmpty())
        <div class="oversight-empty"><strong>No notifications</strong>You're all caught up.</div>
    @else
        <ul class="oversight-list" role="list">
            @foreach($notifications as $notification)
                <li class="notification-row {{ $notification->read_at ? '' : 'is-unread' }}">
                    <span class="notification-row-dot" aria-hidden="true"></span>
                    <div>
                        <p>
                            @unless($notification->read_at)<span class="sr-only">Unread: </span>@endunless
                            @if($notification->priority === 'critical')<strong class="notification-critical-label">Critical alert:</strong> @endif
                            <button
                                type="button"
                                class="notification-detail-trigger"
                                data-notification-id="{{ $notification->id }}"
                                data-notification-title="{{ $notification->title }}"
                                data-notification-message="{{ $notification->message }}"
                                data-notification-url="{{ $notification->resourceUrl }}"
                                data-notification-priority="{{ $notification->priority }}"
                                data-notification-created-at="{{ $notification->created_at->toIso8601String() }}"
                            >{{ $notification->title }}: {{ $notification->message }}</button>
                        </p>
                        <time class="oversight-meta" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M d, Y h:i A') }} · {{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                </li>
            @endforeach
        </ul>
        @if($notifications->hasPages())<div class="dashboard-pagination">{{ $notifications->links() }}</div>@endif
    @endif
</div>
@endsection
