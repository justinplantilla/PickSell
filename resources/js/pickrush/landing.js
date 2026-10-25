// Landing page: sections fade up as they scroll into view, and the header gains a backdrop once the page scrolls.
const root = document.documentElement;
const reveal = document.querySelectorAll('[data-reveal]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reducedMotion && 'IntersectionObserver' in window) {
    root.classList.add('prp-js');
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
    }), { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    reveal.forEach(element => observer.observe(element));
}

// Hero scene: tilt the card toward the pointer and shift its [data-depth] layers (CSS reads --px / --py).
const scene = document.querySelector('.prp-scene');
if (scene && reducedMotion) {
    scene.querySelector('svg')?.pauseAnimations?.(); // the route dot is SMIL, which CSS cannot stop
} else if (scene && window.matchMedia('(hover: hover)').matches) {
    let frame = 0;
    scene.addEventListener('pointermove', event => {
        const box = scene.getBoundingClientRect();
        const px = ((event.clientX - box.left) / box.width) * 2 - 1;
        const py = ((event.clientY - box.top) / box.height) * 2 - 1;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            scene.classList.add('is-tilting');
            scene.style.setProperty('--px', px.toFixed(3));
            scene.style.setProperty('--py', py.toFixed(3));
        });
    });
    scene.addEventListener('pointerleave', () => {
        cancelAnimationFrame(frame);
        scene.classList.remove('is-tilting');
        scene.style.setProperty('--px', '0');
        scene.style.setProperty('--py', '0');
    });
}

const header = document.querySelector('[data-header]');
if (header) {
    const sync = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    sync();
    window.addEventListener('scroll', sync, { passive: true });
}
