const heroImage = (file) => `/images/landing/hero/${file}`;
    const leftProducts = [
        { cat:'Electronics', name:'Wireless Over-Ear Headphones', price:'₱1,299', rating:'4.8', verified:true, img:heroImage('electronics-headphones.jpg') },
        { cat:'Home & Garden', name:'Matte Floor Lamp', price:'₱1,150', rating:'4.7', verified:true, img:heroImage('home-lamp.jpg') },
        { cat:'Fashion', name:'Everyday Canvas Backpack', price:'₱899', rating:'4.9', verified:false, img:heroImage('fashion-bag.jpg') },
        { cat:'Sports', name:'Adjustable Yoga Mat', price:'₱720', rating:'4.6', verified:true, img:heroImage('sports-yoga.jpg') },
        { cat:'Home & Garden', name:'Stoneware Coffee Mug', price:'₱249', rating:'4.5', verified:false, img:heroImage('home-mugs.jpg') }
    ];
    const rightProducts = [
        { cat:'Fashion', name:'Classic Cotton Tee', price:'₱399', rating:'4.7', verified:true, img:heroImage('fashion-shirt.jpg') },
        { cat:'Electronics', name:'Minimal Smart Watch', price:'₱2,450', rating:'4.8', verified:true, img:heroImage('electronics-watch.jpg') },
        { cat:'Food', name:'Café Latte Bundle', price:'₱480', rating:'4.9', verified:false, img:heroImage('home-coffee.jpg') },
        { cat:'Sports', name:'Insulated Water Bottle', price:'₱650', rating:'4.6', verified:true, img:heroImage('sports-bottle.jpg') },
        { cat:'Sports', name:'Lightweight Gym Daypack', price:'₱799', rating:'4.5', verified:false, img:heroImage('sports-duffel.jpg') }
    ];
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[character]);
    // A missing photo falls back to the category label the card showed before images existed.
    const buildHeroCard = (product, state) => `<article class="pcard ${state}"><div class="pcard-img" data-fallback="${escapeHtml(product.cat)}"><img src="${escapeHtml(product.img)}" alt="" loading="lazy" decoding="async" onerror="this.parentElement.textContent=this.parentElement.dataset.fallback"></div><div class="pcard-cat">${escapeHtml(product.cat)}</div><div class="pcard-name">${escapeHtml(product.name)}</div><div class="pcard-row"><span class="pcard-price">${escapeHtml(product.price)}</span><span class="pcard-rating">★ ${escapeHtml(product.rating)}</span></div>${product.verified ? '<span class="pcard-verified">✓ Verified Seller</span>' : ''}</article>`;
    const fillHeroTrack = (element, products) => {
        const states = ['dim', 'focus', 'dim', 'focus', 'dim'];
        element.innerHTML = [...products, ...products].map((product, index) => buildHeroCard(product, states[index % states.length])).join('');
    };
    fillHeroTrack(document.getElementById('leftTrack'), leftProducts);
    fillHeroTrack(document.getElementById('rightTrack'), rightProducts);
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    window.scrollTo(0, 0);
    const landingNav = document.querySelector('.landing-nav');
    const syncLandingNav = () => landingNav?.classList.toggle('is-scrolled', window.scrollY > 18);
    syncLandingNav();
    window.addEventListener('scroll', syncLandingNav, { passive: true });
    const landingObserver = new IntersectionObserver((entries) => { entries.forEach((entry) => { entry.target.classList.toggle('visible', entry.isIntersecting); }); }, { threshold: 0.14 });
    document.querySelectorAll('.reveal').forEach((section) => landingObserver.observe(section));
    document.querySelectorAll('[data-normalize-email]').forEach((form) => {
        form.addEventListener('submit', () => {
            const email = form.querySelector('input[name="email"]');
            if (email) email.value = email.value.trim().toLowerCase();
        });
    });

