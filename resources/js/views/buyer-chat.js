const msgs = document.getElementById('chatMessages');
if (msgs) msgs.scrollTop = msgs.scrollHeight;
function filterUsers(val) {
    document.querySelectorAll('#userList .chat-item').forEach(item => {
        item.style.display = item.dataset.name.includes(val.toLowerCase()) ? 'flex' : 'none';
    });
}
window.filterUsers = filterUsers;
document.querySelector('[data-filter-users]')?.addEventListener('input', event => filterUsers(event.target.value));

// The X on "Asking about" sends the next messages as a plain chat, without the product tag.
document.querySelector('[data-clear-product-context]')?.addEventListener('click', event => {
    event.currentTarget.closest('.product-context')?.remove();
    document.querySelector('.chat-product-context')?.remove();
    document.querySelector('.chat-input-area input[name="product_id"]')?.remove();
    document.querySelector('.chat-input')?.focus();
});
