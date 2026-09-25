function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('open');
}
let buyerNotifications = [];
function showBuyerNotificationDetail(notification) {
    const existing = document.getElementById('notificationDetailModal');
    if (existing) existing.remove();
    const modal = document.createElement('div');
    modal.id = 'notificationDetailModal';
    modal.className = 'notification-detail-modal';
    modal.innerHTML = '<div class="notification-detail-card" role="dialog" aria-modal="true" aria-labelledby="notificationDetailTitle"><button type="button" class="notification-detail-close" aria-label="Close notification">&times;</button><div class="notification-detail-kicker">Notification details</div><h2 id="notificationDetailTitle"></h2><p class="notification-detail-message"></p><time class="notification-detail-time"></time></div>';
    modal.querySelector('h2').textContent = notification.title || notification.type || 'PickSell notification';
    modal.querySelector('.notification-detail-message').textContent = notification.message || 'No additional details available.';
    modal.querySelector('.notification-detail-time').textContent = new Date(notification.created_at).toLocaleString();
    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('.notification-detail-close').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
}

function toggleBuyerNotif() {
    const dd = document.getElementById('buyerNotifDropdown');
    dd.classList.toggle('open');
    document.getElementById('buyerNotifBtn').classList.toggle('is-open', dd.classList.contains('open'));
    if (dd.classList.contains('open')) loadBuyerNotifs();
}

function loadBuyerNotifs() {
    fetch('/buyer/notifications')
        .then(response => response.json().then(data => ({ data, unread: Number(response.headers.get('X-Unread-Count') || 0) })))
        .then(({ data, unread }) => {
            buyerNotifications = data;
            const list = document.getElementById('buyerNotifList');
            document.getElementById('buyerNotifCount').textContent = unread ? `(${unread})` : '';
            document.getElementById('buyerNotifDot').style.display = unread ? 'block' : 'none';
            if (!data.length) {
                list.innerHTML = '<div class="notif-empty">No notifications</div>';
                return;
            }
            list.innerHTML = data.map((n, index) => {
                const msg = n.message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<button type="button" class="notif-item" data-notification-index="${index}"><span>${msg}</span><span class="notif-item-time">${time}</span></button>`;
            }).join('');
        });
}
document.getElementById('buyerNotifList')?.addEventListener('click', event => {
    const item = event.target.closest('[data-notification-index]');
    if (item) showBuyerNotificationDetail(buyerNotifications[Number(item.dataset.notificationIndex)] || {});
});
function markBuyerNotificationsRead() {
    fetch('/buyer/notifications/read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }
    }).then(response => {
        if (!response.ok) throw new Error('Unable to mark notifications as read');
        document.getElementById('buyerNotifDot').style.display = 'none';
        document.getElementById('buyerNotifCount').textContent = '';
    });
}
document.getElementById('markBuyerNotificationsRead')?.addEventListener('click', markBuyerNotificationsRead);

document.addEventListener('click', e => {
    const nav = document.querySelector('.nav-user');
    const btn = document.getElementById('buyerNotifBtn');
    const dd = document.getElementById('buyerNotifDropdown');
    if (nav && !nav.contains(e.target)) {
        document.getElementById('userDropdown').classList.remove('open');
    }
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
        btn.classList.remove('is-open');
    }
});
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}
function dismissAnnouncement(id, button) {
    const banner = button.closest('.announcement-banner');
    const dismissed = JSON.parse(localStorage.getItem('dismissedAnnouncements') || '[]');
    if (!dismissed.includes(id)) dismissed.push(id);
    localStorage.setItem('dismissedAnnouncements', JSON.stringify(dismissed));
    banner.remove();
}
document.querySelectorAll('.announcement-banner').forEach(banner => {
    const dismissed = JSON.parse(localStorage.getItem('dismissedAnnouncements') || '[]');
    if (dismissed.includes(Number(banner.dataset.announcementId))) banner.remove();
});
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark');
}
fetch('/buyer/notifications').then(response => {
    const unread = Number(response.headers.get('X-Unread-Count') || 0);
    document.getElementById('buyerNotifDot').style.display = unread ? 'block' : 'none';
});
window.toggleUserMenu = toggleUserMenu;
window.toggleBuyerNotif = toggleBuyerNotif;
window.loadBuyerNotifs = loadBuyerNotifs;
window.markBuyerNotificationsRead = markBuyerNotificationsRead;
window.toggleDark = toggleDark;
window.dismissAnnouncement = dismissAnnouncement;
