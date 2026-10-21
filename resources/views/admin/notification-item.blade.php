{{-- One notification row on the admin notifications page. Expects $item from AdminNotificationInbox::present(). --}}
<li class="notification-row {{ $item['unread'] ? 'is-unread' : '' }} {{ $item['resolved'] ? 'is-resolved' : '' }} {{ $nested ? 'is-nested' : '' }}">
    @unless($nested)
        <span class="notification-icon is-{{ $item['category'] }} {{ $item['priority'] === 'critical' ? 'is-critical' : '' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{{ \App\Services\Admin\AdminNotificationInbox::icon($item['category'], $item['priority']) }}</svg></span>
    @endunless
    <div class="notification-body">
        <div class="notification-head">
            @if($item['unread'])<span class="sr-only">Unread: </span>@endif
            @if($item['priority'] === 'critical')<strong class="notification-critical-label">Critical</strong>@endif
            <button
                type="button"
                class="notification-detail-trigger notification-title"
                data-notification-id="{{ $item['ids'][0] }}"
                data-notification-ids="{{ implode(',', $item['ids']) }}"
                data-notification-title="{{ $item['title'] }}"
                data-notification-message="{{ $item['message'] }}"
                data-notification-url="{{ $item['url'] }}"
                data-notification-priority="{{ $item['priority'] }}"
                data-notification-created-at="{{ $item['created_at']->toIso8601String() }}"
            >{{ $item['title'] }}</button>
            @if($item['count'] > 1)<span class="notification-badge" title="Received {{ $item['count'] }} times">×{{ $item['count'] }}</span>@endif
        </div>
        <p class="notification-message">{{ \App\Services\Admin\AdminNotificationInbox::highlight($item['message'], $item['highlight']) }}</p>
        <div class="notification-foot">
            <time class="oversight-meta" datetime="{{ $item['created_at']->toIso8601String() }}" title="{{ $item['created_at']->format('M d, Y h:i A') }}">{{ $item['created_at']->diffForHumans() }}</time>
            @if($item['resolved'])
                <span class="notification-status is-{{ $item['resolved']['tone'] }}">{{ $item['resolved']['label'] }}</span>
            @endif
        </div>
    </div>
    @if($item['action'] && $item['url'])
        <a href="{{ $item['url'] }}" class="btn {{ $item['resolved'] ? 'btn-outline' : 'btn-coral' }} btn-sm notification-action" data-notification-ids="{{ implode(',', $item['ids']) }}">{{ $item['action'] }}<span class="sr-only">: {{ $item['title'] }}</span></a>
    @endif
    <span class="notification-row-dot" aria-hidden="true"></span>
</li>
