const leftProducts = [
        { cat:'Electronics', name:'Wireless Earbuds Pro', price:'₱1,299', rating:'4.8', verified:true },
        { cat:'Home & Garden', name:'Ceramic Plant Pot Set', price:'₱549', rating:'4.7', verified:true },
        { cat:'Beauty', name:'Vitamin C Serum 30ml', price:'₱389', rating:'4.9', verified:false },
        { cat:'Sports', name:'Adjustable Yoga Mat', price:'₱720', rating:'4.6', verified:true },
        { cat:'Office & School', name:'Recycled Notebook Set', price:'₱210', rating:'4.5', verified:false }
    ];
    const rightProducts = [
        { cat:'Fashion', name:'Linen Blend Shirt', price:'₱899', rating:'4.7', verified:true },
        { cat:'Pet Supplies', name:'Grain-Free Dog Treats', price:'₱325', rating:'4.8', verified:true },
        { cat:'Food', name:'Single-Origin Coffee Beans', price:'₱480', rating:'4.9', verified:false },
        { cat:'Electronics', name:'USB-C Fast Charger', price:'₱650', rating:'4.6', verified:true },
        { cat:'Home & Garden', name:'Bamboo Utensil Set', price:'₱299', rating:'4.5', verified:false }
    ];
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[character]);
    const buildHeroCard = (product, state) => `<article class="pcard ${state}"><div class="pcard-img">${escapeHtml(product.cat)}</div><div class="pcard-cat">${escapeHtml(product.cat)}</div><div class="pcard-name">${escapeHtml(product.name)}</div><div class="pcard-row"><span class="pcard-price">${escapeHtml(product.price)}</span><span class="pcard-rating">★ ${escapeHtml(product.rating)}</span></div>${product.verified ? '<span class="pcard-verified">✓ Verified Seller</span>' : ''}</article>`;
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

