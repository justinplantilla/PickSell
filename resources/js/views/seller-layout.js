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
window.dismissSellerAnnouncement = dismissSellerAnnouncement;
