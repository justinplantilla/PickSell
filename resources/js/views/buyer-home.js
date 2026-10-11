(function () {
    const carousel = document.getElementById('promoCarousel');
    const track = document.getElementById('promoTrack');
    const dotsWrap = document.getElementById('promoDots');
    if (!carousel || !track || !dotsWrap) return;

    const slides = Array.from(track.querySelectorAll('.promo-slide'));
    if (slides.length < 2) return;

    const dots = [];
    let current = 0;
    let timer = null;
    let startX = 0;
    let paused = false;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    slides.forEach((_, index) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'promo-dot';
        btn.setAttribute('aria-label', `Go to promotion ${index + 1}`);
        btn.addEventListener('click', () => {
            goTo(index);
            restartAuto();
        });
        dots.push(btn);
        dotsWrap.appendChild(btn);
    });

    function goTo(index) {
        current = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${current * 100}%)`;
        slides.forEach((slide, slideIndex) => {
            slide.inert = slideIndex !== current;
            slide.setAttribute('aria-hidden', String(slideIndex !== current));
            slide.classList.remove('is-entering');
        });
        // Force reflow then add class so entrance animations replay
        void slides[current].offsetWidth;
        slides[current].classList.add('is-entering');
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === current);
            dot.setAttribute('aria-current', dotIndex === current ? 'true' : 'false');
        });
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function stopAuto() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function startAuto() {
        stopAuto();
        if (!paused && !reducedMotion.matches && document.visibilityState === 'visible') {
            timer = window.setInterval(next, 5000);
        }
    }

    function restartAuto() {
        paused = false;
        startAuto();
    }

    goTo(0);

    document.getElementById('promoNext')?.addEventListener('click', () => {
        next();
        restartAuto();
    });
    document.getElementById('promoPrev')?.addEventListener('click', () => {
        prev();
        restartAuto();
    });

    track.addEventListener('touchstart', event => {
        startX = event.touches[0].clientX;
        stopAuto();
    }, { passive: true });
    track.addEventListener('touchend', event => {
        const diff = startX - event.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) diff > 0 ? next() : prev();
        restartAuto();
    });

    carousel.addEventListener('mouseenter', () => {
        paused = true;
        stopAuto();
    });
    carousel.addEventListener('mouseleave', restartAuto);
    carousel.addEventListener('focusin', () => {
        paused = true;
        stopAuto();
    });
    carousel.addEventListener('focusout', event => {
        if (!carousel.contains(event.relatedTarget)) restartAuto();
    });
    document.addEventListener('visibilitychange', startAuto);
    reducedMotion.addEventListener('change', startAuto);

    startAuto();
})();
