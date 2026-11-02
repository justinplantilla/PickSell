// Show / hide the password on the staff sign-in page.
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});

// While the sign-in posts, the button spins, the fields dim and the rider speeds up; Back (bfcache) puts it back.
const form = document.querySelector('.prl-form');
const submit = form?.querySelector('.prl-submit');
const label = submit?.textContent;
form?.addEventListener('submit', () => {
    document.body.classList.add('is-signing-in');
    submit.innerHTML = '<span class="prl-spinner" aria-hidden="true"></span> Signing in...';
    setRideSpeed(3.5);
});
window.addEventListener('pageshow', event => {
    if (!event.persisted || !submit) return;
    document.body.classList.remove('is-signing-in');
    submit.textContent = label;
    setRideSpeed(1);
});

// playbackRate keeps the rider where it is; changing animation-duration would make it jump.
function setRideSpeed(rate) {
    document.getAnimations?.()
        .filter(animation => ['prl-ride', 'prp-spin'].includes(animation.animationName) && !animation.effect?.target?.classList.contains('prl-spinner'))
        .forEach(animation => { animation.playbackRate = rate; });
}
