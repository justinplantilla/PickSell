const nav = document.querySelector('.nav-top');
if (nav) {
    const syncNav = () => nav.classList.toggle('is-scrolled', window.scrollY > 18);
    syncNav();
    window.addEventListener('scroll', syncNav, { passive: true });
}
