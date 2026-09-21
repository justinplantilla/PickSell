// Auto-scroll to bottom
const msgs = document.getElementById('chatMessages');
if (msgs) msgs.scrollTop = msgs.scrollHeight;

// Filter users
function filterUsers(val) {
    document.querySelectorAll('#userList .chat-item').forEach(item => {
        item.style.display = item.dataset.name.includes(val.toLowerCase()) ? 'flex' : 'none';
    });
}
window.filterUsers = filterUsers;
