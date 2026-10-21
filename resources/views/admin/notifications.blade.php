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
            <p class="oversight-description">Alerts that need an admin's attention. Read notifications are removed after {{ \App\Services\Admin\AdminNotificationInbox::RETENTION_DAYS }} days.</p>
        </div>
        @can(\App\Auth\Permission::ACCOUNT_MANAGE)
            @if(($unreadCounts[$filter] ?? 0) > 0)
                <button type="button" class="btn btn-outline btn-sm" data-mark-notifications-read="{{ $filter }}">
                    {{ $filter === 'all' ? 'Mark all as read' : 'Mark '.strtolower($filters[$filter]).' as read' }}
                </button>
            @endif
        @endcan
    </div>

    <nav class="oversight-tabs notification-filters" aria-label="Filter notifications">
        @foreach($filters as $key => $label)
            <a href="{{ route('admin.notifications', $key === 'all' ? [] : ['filter' => $key]) }}"
               class="oversight-tab {{ $filter === $key ? 'is-active' : '' }}"
               @if($filter === $key) aria-current="page" @endif>
                {{ $label }}
                @if(($unreadCounts[$key] ?? 0) > 0)
                    <span class="oversight-tab-count">{{ $unreadCounts[$key] }}<span class="sr-only"> unread</span></span>
                @endif
            </a>
        @endforeach
    </nav>

    @if($notifications->isEmpty())
        <div class="oversight-empty">
            <strong>{{ $filter === 'all' ? 'No notifications' : 'Nothing here' }}</strong>
            {{ $filter === 'all' ? "You're all caught up." : 'No '.strtolower($filters[$filter]).' notifications right now.' }}
        </div>
    @else
        @foreach($sections as $section)
            <section class="notification-section" aria-labelledby="notif-section-{{ $loop->index }}">
                <h2 class="notification-section-label" id="notif-section-{{ $loop->index }}">{{ $section['label'] }}</h2>
                <ul class="oversight-list" role="list">
                    @foreach($section['rows'] as $row)
                        @if($row['kind'] === 'group')
                            <li class="notification-group {{ $row['unread'] ? 'is-unread' : '' }}">
                                <details>
                                    <summary class="notification-row">
                                        <span class="notification-icon is-{{ $row['category'] }} {{ $row['priority'] === 'critical' ? 'is-critical' : '' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{{ \App\Services\Admin\AdminNotificationInbox::icon($row['category'], $row['priority']) }}</svg></span>
                                        <div class="notification-body">
                                            <div class="notification-head">
                                                <span class="notification-title">{{ count($row['items']) }} × {{ $row['title'] }}</span>
                                                @if($row['unread'])<span class="notification-badge">{{ $row['unread'] }} unread</span>@endif
                                            </div>
                                            <p class="notification-message">Latest {{ $row['created_at']->diffForHumans() }}. Expand to see each one.</p>
                                        </div>
                                        <span class="notification-group-chevron" aria-hidden="true"></span>
                                    </summary>
                                    <ul class="oversight-list notification-group-items" role="list">
                                        @foreach($row['items'] as $item)
                                            @include('admin.notification-item', ['item' => $item, 'nested' => true])
                                        @endforeach
                                    </ul>
                                </details>
                            </li>
                        @else
                            @include('admin.notification-item', ['item' => $row, 'nested' => false])
                        @endif
                    @endforeach
                </ul>
            </section>
        @endforeach
        @if($notifications->hasPages())<div class="dashboard-pagination">{{ $notifications->links() }}</div>@endif
    @endif
</div>
@endsection
