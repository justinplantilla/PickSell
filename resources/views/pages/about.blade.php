@extends('layouts.app')
@section('title', 'About PickSell')

@section('styles')
@vite('resources/css/views/pages-about.css')
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
    <div class="blade-inline-1">
        <div class="section-label">Legal</div>
        <h2 class="section-title">Terms of <em>Service</em></h2>
        <div class="blade-inline-2">{{ $tos }}</div>
    </div>
    @endif

    @if($privacy)
    <div class="blade-inline-3">
        <div class="section-label">Legal</div>
        <h2 class="section-title">Privacy <em>Policy</em></h2>
        <div class="blade-inline-4">{{ $privacy }}</div>
    </div>
    @endif
</div>
@endsection
