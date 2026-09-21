const msgs = document.getElementById('chatMessages');
if (msgs) msgs.scrollTop = msgs.scrollHeight;
function filterUsers(val) {
    document.querySelectorAll('#userList .chat-item').forEach(item => {
        item.style.display = item.dataset.name.includes(val.toLowerCase()) ? 'flex' : 'none';
    });
}
window.filterUsers = filterUsers;
