function openWarn(id, name) {
    document.getElementById('warnName').textContent = name;
    document.getElementById('warnForm').action = '/admin/compliance/' + id + '/warn';
    document.getElementById('warnModal').style.display = 'flex';
}
function closeWarn() {
    document.getElementById('warnModal').style.display = 'none';
}
window.openWarn = openWarn;
window.closeWarn = closeWarn;
