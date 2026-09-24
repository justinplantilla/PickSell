function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const m = document.getElementById('main');
    s.classList.toggle('mini');
    m.classList.toggle('mini');
    localStorage.setItem('sellerSidebarMini', s.classList.contains('mini'));
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
    if (dd.classList.contains('open')) loadSellerNotifs();
}

function loadSellerNotifs() {
    fetch('/seller/notifications')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('sellerNotifList');
            document.getElementById('sellerNotifCount').textContent = data.length ? `(${data.length})` : '';
            document.getElementById('sellerNotifDot').style.display = data.length ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n => {
                const payload = JSON.parse(n.data);
                const msg = payload.message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<div class="notif-item"><div>${msg}</div><div style="font-size:0.75rem;color:#aaa;margin-top:0.2rem;">${time}</div></div>`;
            }).join('');
        });
}

document.addEventListener('click', e => {
    const btn = document.getElementById('notifBtn');
    const dd = document.getElementById('sellerNotifDropdown');
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
    }
});

fetch('/seller/notifications').then(r => r.json()).then(data => {
    if (data.length) document.getElementById('sellerNotifDot').style.display = 'block';
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
window.dismissSellerAnnouncement = dismissSellerAnnouncement;
