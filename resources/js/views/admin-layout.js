function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'flex';
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
    if (dd.classList.contains('open')) loadNotifs();
}
function loadNotifs() {
    fetch('/admin/notifications')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notifList');
            document.getElementById('notifCount').textContent = data.length ? `(${data.length})` : '';
            document.getElementById('notifDot').style.display = data.length ? 'block' : 'none';
            if (!data.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n => {
                const msg = JSON.parse(n.data).message || '';
                const time = new Date(n.created_at).toLocaleString();
                return `<div class="notif-item"><div>${msg}</div><div style="font-size:0.75rem;color:#aaa;margin-top:0.2rem;">${time}</div></div>`;
            }).join('');
        });
}
document.addEventListener('click', e => {
    if (!document.getElementById('notifBtn').contains(e.target) && !document.getElementById('notifDropdown').contains(e.target)) {
        document.getElementById('notifDropdown').classList.remove('open');
    }
});
// Check unread on load
fetch('/admin/notifications').then(r=>r.json()).then(data=>{
    if(data.length) document.getElementById('notifDot').style.display='block';
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
window.toggleDark = toggleDark;
