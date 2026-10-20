function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('mini');
    document.getElementById('main').classList.toggle('mini');
    localStorage.setItem('logisticsSidebarMini', document.querySelector('.sidebar').classList.contains('mini'));
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('logisticsDark', document.body.classList.contains('dark'));
}
if (localStorage.getItem('logisticsSidebarMini') === 'true') { document.querySelector('.sidebar').classList.add('mini'); document.getElementById('main').classList.add('mini'); }
if (localStorage.getItem('logisticsDark') === 'true') document.body.classList.add('dark');
window.toggleSidebar = toggleSidebar;
window.toggleDark = toggleDark;
