function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
    const nav = document.querySelector('.nav-user');
    if (nav && !nav.contains(e.target)) {
        document.getElementById('userDropdown').classList.remove('open');
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
window.toggleUserMenu = toggleUserMenu;
window.toggleDark = toggleDark;
window.dismissAnnouncement = dismissAnnouncement;
