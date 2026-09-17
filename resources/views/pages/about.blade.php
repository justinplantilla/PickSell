@extends('layouts.app')
@section('title', 'About PickSell')

@section('styles')
<style>
    .about-hero { background: var(--charcoal); color: #fff; padding: 3.5rem 2rem; text-align: center; }
    .about-hero h1 { font-size: 2rem; font-weight: 800; }
    .about-hero h1 em { color: var(--coral); font-style: normal; }
    .about-hero p { color: #aaa; margin-top: 0.6rem; max-width: 500px; margin-inline: auto; font-size: 0.95rem; line-height: 1.7; }

    .about-wrapper { max-width: 1000px; margin: 0 auto; padding: 3rem 1.5rem; }

    /* Trust badges */
    .trust-strip { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; background: var(--bone-dark); border-top: 1px solid #ddd6c8; border-bottom: 1px solid #ddd6c8; padding: 1.5rem 2rem; }
    .trust-item { display: flex; align-items: center; gap: 0.6rem; font-size: 0.88rem; font-weight: 600; color: var(--charcoal); }
    .trust-item span { font-size: 1.3rem; }

    .section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--coral); margin-bottom: 0.4rem; }
    .section-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.6rem; }
    .section-title em { color: var(--coral); font-style: normal; }
    .section-body { color: #666; line-height: 1.8; font-size: 0.93rem; margin-bottom: 2.5rem; }

    /* How it works */
    .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
    .step { background: #fff; border: 1px solid #e8e2d8; border-radius: 12px; padding: 1.5rem; text-align: center; }
    .step-num { width: 40px; height: 40px; background: var(--coral); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; margin: 0 auto 0.8rem; }
    .step h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.3rem; }
    .step p { font-size: 0.83rem; color: #777; line-height: 1.6; }

    /* Seller CTA */
    .seller-cta { background: var(--charcoal); color: #fff; border-radius: 16px; padding: 2.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 3rem; }
    .seller-cta h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.4rem; }
    .seller-cta h2 em { color: var(--coral); font-style: normal; }
    .seller-cta p { color: #aaa; font-size: 0.9rem; }
    .btn-lg { padding: 0.8rem 2rem; font-size: 0.95rem; border-radius: 8px; font-weight: 700; }

    /* Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 3rem; }
    .stat-card { background: #fff; border: 1px solid #e8e2d8; border-radius: 12px; padding: 1.5rem; text-align: center; }
    .stat-num { font-size: 2rem; font-weight: 800; color: var(--coral); }
    .stat-label { font-size: 0.82rem; color: #888; margin-top: 0.2rem; }

    .company-intro { background: #fff; border: 1px solid #e8e2d8; border-radius: 14px; padding: 2rem; margin-bottom: 3rem; }
    .company-intro p { color: #555; line-height: 1.8; font-size: 1rem; max-width: 820px; }
    .direction-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 3rem; }
    .direction-card { border-radius: 12px; padding: 1.6rem; border: 1px solid #e8e2d8; background: #fff; }
    .direction-card.mission { border-top: 4px solid var(--coral); }
    .direction-card.vision { border-top: 4px solid #e3ae24; }
    .direction-card h3 { font-size: 1.05rem; margin-bottom: 0.6rem; }
    .direction-card p { color: #666; font-size: 0.9rem; line-height: 1.75; }
    .values-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; margin-bottom: 3rem; }
    .value-card { min-height: 130px; padding: 1rem; border: 1px solid #e8e2d8; border-radius: 10px; background: #fff; }
    .value-number { color: var(--coral); font-size: 0.7rem; font-weight: 800; }
    .value-card h3 { font-size: 0.85rem; margin: 0.8rem 0 0.35rem; }
    .value-card p { color: #777; font-size: 0.75rem; line-height: 1.45; }
    .goals-list { display: grid; gap: 0.65rem; margin: 0 0 3rem; padding: 0; list-style: none; }
    .goal-item { display: grid; grid-template-columns: 32px 1fr; gap: 0.75rem; align-items: start; padding: 0.9rem 1rem; background: #fff; border: 1px solid #e8e2d8; border-radius: 8px; color: #555; font-size: 0.86rem; line-height: 1.55; }
    .goal-number { color: var(--coral); font-weight: 800; }
    @media(max-width: 800px) { .values-grid { grid-template-columns: repeat(3, 1fr); } }
    @media(max-width: 600px) { .direction-grid { grid-template-columns: 1fr; } .values-grid { grid-template-columns: repeat(2, 1fr); } .company-intro { padding: 1.3rem; } }
</style>
@endsection

@section('content')
<div class="about-hero">
    <h1>About <em>PickSell</em></h1>
    <p>Pick it. Sell it. We handle the rest.</p>
</div>

<div class="trust-strip">
    <div class="trust-item"><span>🛡️</span> Buyer Protection</div>
    <div class="trust-item"><span>🔒</span> Secure Checkout</div>
    <div class="trust-item"><span>🚚</span> Fast Nationwide Delivery</div>
    <div class="trust-item"><span>↩️</span> 30-Day Returns</div>
    <div class="trust-item"><span>✅</span> Verified Sellers</div>
</div>

<div class="about-wrapper">
    <section class="company-intro">
        <div class="section-label">Who We Are</div>
        <h2 class="section-title">A marketplace that moves with you.</h2>
        <p>PickSell is a three-sided Philippine e-commerce marketplace connecting buyers, sellers, and couriers through an integrated logistics and sorting center. We enable easy browsing, secure checkout, and full order tracking from purchase to doorstep delivery.</p>
    </section>

    <div class="section-label">Our Direction</div>
    <div class="direction-grid">
        <article class="direction-card mission">
            <h3>Mission</h3>
            <p>To give Filipino buyers, sellers, and couriers a fast, trustworthy, and accessible online marketplace that makes local commerce simple — from the moment an item is clicked to the moment it's delivered.</p>
        </article>
        <article class="direction-card vision">
            <h3>Vision</h3>
            <p>To become a trusted, efficient e-commerce ecosystem in the Philippines, where every transaction moves seamlessly from seller to sorting center to doorstep.</p>
        </article>
    </div>

    <div class="section-label">What We Stand For</div>
    <div class="values-grid">
        <article class="value-card"><span class="value-number">01</span><h3>Trust</h3><p>Verified sellers, tracked couriers, and transparent transactions.</p></article>
        <article class="value-card"><span class="value-number">02</span><h3>Speed</h3><p>Mobile-first experiences, fast checkout, and real-time tracking.</p></article>
        <article class="value-card"><span class="value-number">03</span><h3>Reliability</h3><p>Streamlined logistics from pickup to final delivery.</p></article>
        <article class="value-card"><span class="value-number">04</span><h3>Fairness</h3><p>Fair commissions and honest dispute resolution.</p></article>
        <article class="value-card"><span class="value-number">05</span><h3>Community</h3><p>Supporting local sellers and riders across the Philippines.</p></article>
    </div>

    <div class="section-label">Our Goals</div>
    <ul class="goals-list">
        <li class="goal-item"><span class="goal-number">01</span><span>Deliver a fully functional 5-role marketplace for Buyers, Sellers, Couriers, Logistics/Sorting Center, and Admin within the 18-week development timeline.</span></li>
        <li class="goal-item"><span class="goal-number">02</span><span>Provide an end-to-end trackable order lifecycle, from checkout to delivery confirmation.</span></li>
        <li class="goal-item"><span class="goal-number">03</span><span>Maintain platform trust through seller verification, compliance monitoring, and dispute handling.</span></li>
        <li class="goal-item"><span class="goal-number">04</span><span>Build a scalable foundation for real-world use beyond the course requirement.</span></li>
    </ul>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-num">50K+</div><div class="stat-label">Products Listed</div></div>
        <div class="stat-card"><div class="stat-num">12K+</div><div class="stat-label">Happy Buyers</div></div>
        <div class="stat-card"><div class="stat-num">3K+</div><div class="stat-label">Active Sellers</div></div>
        <div class="stat-card"><div class="stat-num">99%</div><div class="stat-label">Satisfaction Rate</div></div>
    </div>

    <div class="section-label">How It Works</div>
    <h2 class="section-title">Shop in <em>3 Easy Steps</em></h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><h3>Browse & Discover</h3><p>Search thousands of products across all categories at the best prices.</p></div>
        <div class="step"><div class="step-num">2</div><h3>Add to Cart & Pay</h3><p>Checkout securely with GCash, credit card, or cash on delivery.</p></div>
        <div class="step"><div class="step-num">3</div><h3>Receive & Enjoy</h3><p>Get your order delivered fast. Not satisfied? Return it hassle-free.</p></div>
    </div>

    <div class="seller-cta">
        <div>
            <h2>Want to <em>Start Selling?</em></h2>
            <p>List your products for free and reach thousands of buyers across the Philippines.</p>
        </div>
        <a href="/register" class="btn btn-coral btn-lg">Become a Seller</a>
    </div>

    <div class="section-label">Our Promise</div>
    <h2 class="section-title">Your <em>Protection</em> is Our Priority</h2>
    <p class="section-body">Every purchase on PickSell is covered by our Buyer Protection Program. If your item doesn't arrive, arrives damaged, or isn't as described — we'll make it right. We only work with verified sellers and hold payments until you confirm your order is received.</p>

    @php
        $tos     = \App\Models\PlatformSetting::get('terms_of_service');
        $privacy = \App\Models\PlatformSetting::get('privacy_policy');
    @endphp

    @if($tos)
    <div style="margin-bottom:2.5rem;">
        <div class="section-label">Legal</div>
        <h2 class="section-title">Terms of <em>Service</em></h2>
        <div style="background:#fff;border:1px solid #e8e2d8;border-radius:12px;padding:1.5rem;font-size:0.88rem;color:#555;line-height:1.8;white-space:pre-wrap;">{{ $tos }}</div>
    </div>
    @endif

    @if($privacy)
    <div style="margin-bottom:2.5rem;">
        <div class="section-label">Legal</div>
        <h2 class="section-title">Privacy <em>Policy</em></h2>
        <div style="background:#fff;border:1px solid #e8e2d8;border-radius:12px;padding:1.5rem;font-size:0.88rem;color:#555;line-height:1.8;white-space:pre-wrap;">{{ $privacy }}</div>
    </div>
    @endif
</div>
@endsection
