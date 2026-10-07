function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'flex';
}
let adminNotifications = [];
const adminNotificationIcon = '<svg class="notification-item-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
function safeAdminNotificationHref(value) {
    if (typeof value !== 'string' || !value.startsWith('/')) return null;
    try {
        const target = new URL(value, window.location.origin);
        return target.origin === window.location.origin ? target.href : null;
    } catch {
        return null;
    }
}
function showAdminNotificationDetail(notification) {
    let payload = notification.data || notification;
    if (typeof payload === 'string') {
        try { payload = JSON.parse(payload || '{}'); } catch { payload = {}; }
    }
    const existing = document.getElementById('notificationDetailModal');
    if (existing) existing.remove();
    const modal = document.createElement('div');
    modal.id = 'notificationDetailModal';
    modal.className = 'notification-detail-modal';
    modal.innerHTML = '<div class="notification-detail-card" role="dialog" aria-modal="true" aria-labelledby="notificationDetailTitle"><button type="button" class="notification-detail-close" aria-label="Close notification">&times;</button><div class="notification-detail-kicker">Notification details</div><h2 id="notificationDetailTitle"></h2><p class="notification-detail-message"></p><time class="notification-detail-time"></time><a class="notification-detail-link" hidden></a></div>';
    modal.querySelector('h2').textContent = payload.title || notification.type || 'PickSell notification';
    modal.querySelector('.notification-detail-message').textContent = payload.message || 'No additional details available.';
    modal.querySelector('.notification-detail-time').textContent = new Date(notification.created_at).toLocaleString();
    if (payload.priority === 'critical') {
        modal.querySelector('.notification-detail-kicker').textContent = 'Critical alert';
    }
    const resourceLink = modal.querySelector('.notification-detail-link');
    const resourceHref = safeAdminNotificationHref(payload.url);
    if (resourceHref) {
        resourceLink.href = resourceHref;
        resourceLink.textContent = 'Open related item';
        resourceLink.hidden = false;
    }
    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('.notification-detail-close').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
}
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const m = document.getElementById('main');
    s.classList.toggle('mini');
    m.classList.toggle('mini');
    localStorage.setItem('sidebarMini', s.classList.contains('mini'));
}
if (localStorage.getItem('sidebarMini') === 'true') {
    document.getElementById('sidebar').classList.add('mini');
    document.getElementById('main').classList.add('mini');
}
function toggleNotif() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    document.getElementById('notifBtn').classList.toggle('is-open', dd.classList.contains('open'));
    if (dd.classList.contains('open')) loadNotifs();
}
function loadNotifs() {
    fetch('/admin/notifications/feed')
        .then(response => response.json().then(data => ({ data, unread: Number(response.headers.get('X-Unread-Count') || 0) })))
        .then(({ data, unread }) => {
            adminNotifications = data;
            const list = document.getElementById('notifList');
            document.getElementById('notifCount').textContent = unread ? `(${unread})` : '';
            document.getElementById('notifDot').style.display = unread ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.replaceChildren(...data.map((n, index) => {
                let payload = n.data || {};
                if (typeof payload === 'string') {
                    try { payload = JSON.parse(payload); } catch { payload = {}; }
                }
                const item = document.createElement('button');
                item.type = 'button';
                item.className = `notif-item${n.read_at ? '' : ' is-unread'}`;
                item.dataset.notificationIndex = String(index);
                const icon = document.createElement('span');
                icon.innerHTML = adminNotificationIcon;
                const copy = document.createElement('span');
                copy.className = 'notification-item-copy';
                const message = document.createElement('span');
                message.textContent = payload.message || 'Notification';
                if (payload.priority === 'critical') {
                    const critical = document.createElement('strong');
                    critical.className = 'notification-critical-label';
                    critical.textContent = 'Critical: ';
                    message.prepend(critical);
                }
                const time = document.createElement('span');
                time.className = 'notif-item-time';
                time.textContent = new Date(n.created_at).toLocaleString();
                copy.append(message, time);
                item.append(icon.firstElementChild, copy);
                return item;
            }));
        });
}
document.getElementById('notifList')?.addEventListener('click', event => {
    const item = event.target.closest('[data-notification-index]');
    if (!item) return;
    const notification = adminNotifications[Number(item.dataset.notificationIndex)] || {};
    showAdminNotificationDetail(notification);
    if (!notification.read_at) {
        fetch(`/admin/notifications/${encodeURIComponent(notification.id)}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }
        }).then(response => {
            if (!response.ok) throw new Error('Unable to mark notification as read');
            notification.read_at = new Date().toISOString();
            item.classList.remove('is-unread');
            document.getElementById('notifDot').style.display = 'none';
        }).catch(error => console.error(error));
    }
});
document.querySelector('.oversight-list')?.addEventListener('click', event => {
    const item = event.target.closest('.notification-detail-trigger');
    if (!item) return;
    const notification = {
        id: item.dataset.notificationId,
        created_at: item.dataset.notificationCreatedAt,
        read_at: item.closest('.notification-row')?.classList.contains('is-unread') ? null : new Date().toISOString(),
        data: {
            title: item.dataset.notificationTitle,
            message: item.dataset.notificationMessage,
            url: item.dataset.notificationUrl || null,
            priority: item.dataset.notificationPriority
        }
    };
    showAdminNotificationDetail(notification);
    if (!notification.read_at) {
        fetch(`/admin/notifications/${encodeURIComponent(item.dataset.notificationId)}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }
        }).then(response => {
            if (!response.ok) throw new Error('Unable to mark notification as read');
            item.closest('.notification-row')?.classList.remove('is-unread');
            notification.read_at = new Date().toISOString();
        }).catch(error => console.error(error));
    }
});
function markAdminNotificationsRead() {
    fetch('/admin/notifications/read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }
    }).then(response => {
        if (!response.ok) throw new Error('Unable to mark notifications as read');
        document.getElementById('notifDot').style.display = 'none';
        document.getElementById('notifCount').textContent = '';
    });
}
document.getElementById('markAdminNotificationsRead')?.addEventListener('click', markAdminNotificationsRead);
document.addEventListener('click', e => {
    if (!document.getElementById('notifBtn').contains(e.target) && !document.getElementById('notifDropdown').contains(e.target)) {
        document.getElementById('notifDropdown').classList.remove('open');
        document.getElementById('notifBtn').classList.remove('is-open');
    }
});
// Check unread on load
fetch('/admin/notifications/feed').then(response => {
    const unread = Number(response.headers.get('X-Unread-Count') || 0);
    document.getElementById('notifDot').style.display = unread ? 'block' : 'none';
});

function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('adminDark', document.body.classList.contains('dark'));
}
if (localStorage.getItem('adminDark') === 'true') document.body.classList.add('dark');
window.confirmLogout = confirmLogout;
window.toggleSidebar = toggleSidebar;
window.toggleNotif = toggleNotif;
window.loadNotifs = loadNotifs;
window.markAdminNotificationsRead = markAdminNotificationsRead;
window.toggleDark = toggleDark;
