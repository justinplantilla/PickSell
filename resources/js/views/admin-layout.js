function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'flex';
}
let adminNotifications = [];
function showAdminNotificationDetail(notification) {
    const payload = typeof notification.data === 'string' ? JSON.parse(notification.data || '{}') : (notification.data || notification);
    const existing = document.getElementById('notificationDetailModal');
    if (existing) existing.remove();
    const modal = document.createElement('div');
    modal.id = 'notificationDetailModal';
    modal.className = 'notification-detail-modal';
    modal.innerHTML = '<div class="notification-detail-card" role="dialog" aria-modal="true" aria-labelledby="notificationDetailTitle"><button type="button" class="notification-detail-close" aria-label="Close notification">&times;</button><div class="notification-detail-kicker">Notification details</div><h2 id="notificationDetailTitle"></h2><p class="notification-detail-message"></p><time class="notification-detail-time"></time></div>';
    modal.querySelector('h2').textContent = payload.title || notification.type || 'PickSell notification';
    modal.querySelector('.notification-detail-message').textContent = payload.message || 'No additional details available.';
    modal.querySelector('.notification-detail-time').textContent = new Date(notification.created_at).toLocaleString();
    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('.notification-detail-close').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
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
    fetch('/admin/notifications')
        .then(response => response.json().then(data => ({ data, unread: Number(response.headers.get('X-Unread-Count') || 0) })))
        .then(({ data, unread }) => {
            adminNotifications = data;
            const list = document.getElementById('notifList');
            document.getElementById('notifCount').textContent = unread ? `(${unread})` : '';
            document.getElementById('notifDot').style.display = unread ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map((n, index) => {
                const msg = JSON.parse(n.data).message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<button type="button" class="notif-item" data-notification-index="${index}"><span>${msg}</span><span class="notif-item-time">${time}</span></button>`;
            }).join('');
        });
}
document.getElementById('notifList')?.addEventListener('click', event => {
    const item = event.target.closest('[data-notification-index]');
    if (item) showAdminNotificationDetail(adminNotifications[Number(item.dataset.notificationIndex)] || {});
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
fetch('/admin/notifications').then(response => {
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
