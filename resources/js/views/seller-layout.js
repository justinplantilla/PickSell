function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const m = document.getElementById('main');
    s.classList.toggle('mini');
    m.classList.toggle('mini');
    localStorage.setItem('sellerSidebarMini', s.classList.contains('mini'));
}
let sellerNotifications = [];
function showSellerNotificationDetail(notification) {
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
if (localStorage.getItem('sellerSidebarMini') === 'true') {
    document.getElementById('sidebar').classList.add('mini');
    document.getElementById('main').classList.add('mini');
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('sellerDark', document.body.classList.contains('dark'));
}
if (localStorage.getItem('sellerDark') === 'true') document.body.classList.add('dark');

function toggleSellerNotif() {
    const dd = document.getElementById('sellerNotifDropdown');
    dd.classList.toggle('open');
    document.getElementById('notifBtn').classList.toggle('is-open', dd.classList.contains('open'));
    if (dd.classList.contains('open')) loadSellerNotifs();
}

function loadSellerNotifs() {
    fetch('/seller/notifications')
        .then(response => response.json().then(data => ({ data, unread: Number(response.headers.get('X-Unread-Count') || 0) })))
        .then(({ data, unread }) => {
            sellerNotifications = data;
            const list = document.getElementById('sellerNotifList');
            document.getElementById('sellerNotifCount').textContent = unread ? `(${unread})` : '';
            document.getElementById('sellerNotifDot').style.display = unread ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map((n, index) => {
                const payload = JSON.parse(n.data);
                const msg = payload.message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<button type="button" class="notif-item" data-notification-index="${index}"><span>${msg}</span><span class="notif-item-time">${time}</span></button>`;
            }).join('');
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
        document.getElementById('sellerNotifDot').style.display = 'none';
        document.getElementById('sellerNotifCount').textContent = '';
    });
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
    const unread = Number(response.headers.get('X-Unread-Count') || 0);
    document.getElementById('sellerNotifDot').style.display = unread ? 'block' : 'none';
});

function dismissSellerAnnouncement(button) {
    const banner = button.closest('[data-announcement-id]');
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
