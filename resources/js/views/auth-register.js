function togglePw(btn) {
    const inp = btn.previousElementSibling || btn.parentElement.querySelector('input');
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.style.color = isText ? '' : 'var(--coral)';
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}
if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark');
window.togglePw = togglePw;
window.toggleDark = toggleDark;
