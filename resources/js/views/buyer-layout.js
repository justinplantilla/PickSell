function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('open');
}

function toggleBuyerNotif() {
    const dd = document.getElementById('buyerNotifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) loadBuyerNotifs();
}

function loadBuyerNotifs() {
    fetch('/buyer/notifications')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('buyerNotifList');
            document.getElementById('buyerNotifCount').textContent = data.length ? `(${data.length})` : '';
            document.getElementById('buyerNotifDot').style.display = data.length ? 'block' : 'none';
            if (!data.length) {
                list.innerHTML = '<div class="notif-empty">No notifications</div>';
                return;
            }
            list.innerHTML = data.map(n => {
                const msg = n.message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<div class="notif-item"><div>${msg}</div><div style="font-size:0.75rem;color:#aaa;margin-top:0.2rem;">${time}</div></div>`;
            }).join('');
        });
}

document.addEventListener('click', e => {
    const nav = document.querySelector('.nav-user');
    const btn = document.getElementById('buyerNotifBtn');
    const dd = document.getElementById('buyerNotifDropdown');
    if (nav && !nav.contains(e.target)) {
        document.getElementById('userDropdown').classList.remove('open');
    }
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
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
fetch('/buyer/notifications').then(r => r.json()).then(data => {
    if (data.length) document.getElementById('buyerNotifDot').style.display = 'block';
});
window.toggleUserMenu = toggleUserMenu;
window.toggleBuyerNotif = toggleBuyerNotif;
window.loadBuyerNotifs = loadBuyerNotifs;
window.toggleDark = toggleDark;
window.dismissAnnouncement = dismissAnnouncement;
