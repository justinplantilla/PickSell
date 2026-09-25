function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const m = document.getElementById('main');
    if (!s || !m) return;
    s.classList.toggle('mini');
    m.classList.toggle('mini');
    localStorage.setItem('sellerSidebarMini', s.classList.contains('mini'));
}
let sellerNotifications = [];
const sellerNotificationIcon = '<svg class="notification-item-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
function showSellerNotificationDetail(notification) {
    let payload = notification.data || notification;
    if (typeof payload === 'string') {
        try { payload = JSON.parse(payload || '{}'); } catch { payload = {}; }
    }
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
if (localStorage.getItem('sellerSidebarMini') === 'true') {
    document.getElementById('sidebar')?.classList.add('mini');
    document.getElementById('main')?.classList.add('mini');
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('sellerDark', document.body.classList.contains('dark'));
}
if (localStorage.getItem('sellerDark') === 'true') document.body.classList.add('dark');

function toggleSellerNotif() {
    const dd = document.getElementById('sellerNotifDropdown');
    const button = document.getElementById('notifBtn');
    if (!dd || !button) return;
    dd.classList.toggle('open');
    button.classList.toggle('is-open', dd.classList.contains('open'));
    if (dd.classList.contains('open')) loadSellerNotifs();
}

function loadSellerNotifs() {
    fetch('/seller/notifications')
        .then(response => {
            if (!response.ok) throw new Error('Unable to load notifications');
            return response.json().then(data => ({ data, unread: Number(response.headers.get('X-Unread-Count') || 0) }));
        })
        .then(({ data, unread }) => {
            sellerNotifications = data;
            const list = document.getElementById('sellerNotifList');
            if (!list) return;
            document.getElementById('sellerNotifCount').textContent = unread ? `(${unread})` : '';
            document.getElementById('sellerNotifDot').style.display = unread ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map((n, index) => {
                let payload = n.data || {};
                if (typeof payload === 'string') {
                    try { payload = JSON.parse(payload); } catch { payload = {}; }
                }
                const msg = payload.message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<button type="button" class="notif-item" data-notification-index="${index}">${sellerNotificationIcon}<span class="notification-item-copy"><span>${msg}</span><span class="notif-item-time">${time}</span></span></button>`;
            }).join('');
        })
        .catch(() => {
            const list = document.getElementById('sellerNotifList');
            if (list) list.innerHTML = '<div class="notif-empty">Unable to load notifications.</div>';
        });
}
document.getElementById('sellerNotifList')?.addEventListener('click', event => {
    const item = event.target.closest('[data-notification-index]');
    if (item) showSellerNotificationDetail(sellerNotifications[Number(item.dataset.notificationIndex)] || {});
});
function markSellerNotificationsRead() {
    fetch('/seller/notifications/read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }
    }).then(response => {
        if (!response.ok) throw new Error('Unable to mark notifications as read');
        document.getElementById('sellerNotifDot')?.style.setProperty('display', 'none');
        const count = document.getElementById('sellerNotifCount');
        if (count) count.textContent = '';
    }).catch(() => {});
}
document.getElementById('markSellerNotificationsRead')?.addEventListener('click', markSellerNotificationsRead);

document.addEventListener('click', e => {
    const btn = document.getElementById('notifBtn');
    const dd = document.getElementById('sellerNotifDropdown');
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
        btn.classList.remove('is-open');
    }
});

fetch('/seller/notifications').then(response => {
    if (!response.ok) return;
    const unread = Number(response.headers.get('X-Unread-Count') || 0);
    const dot = document.getElementById('sellerNotifDot');
    if (dot) dot.style.display = unread ? 'block' : 'none';
}).catch(() => {});

function dismissSellerAnnouncement(button) {
    const banner = button.closest('[data-announcement-id]');
    if (!banner) return;
    const dismissed = JSON.parse(localStorage.getItem('dismissedSellerAnnouncements') || '[]');
    const id = Number(banner.dataset.announcementId);
    if (!dismissed.includes(id)) dismissed.push(id);
    localStorage.setItem('dismissedSellerAnnouncements', JSON.stringify(dismissed));
    banner.remove();
}
document.querySelectorAll('.seller-announcement').forEach(banner => {
    const dismissed = JSON.parse(localStorage.getItem('dismissedSellerAnnouncements') || '[]');
    if (dismissed.includes(Number(banner.dataset.announcementId))) banner.remove();
});
window.toggleSidebar = toggleSidebar;
window.toggleDark = toggleDark;
window.toggleSellerNotif = toggleSellerNotif;
window.loadSellerNotifs = loadSellerNotifs;
window.markSellerNotificationsRead = markSellerNotificationsRead;
window.dismissSellerAnnouncement = dismissSellerAnnouncement;
