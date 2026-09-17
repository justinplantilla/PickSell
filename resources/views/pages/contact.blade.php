@extends('layouts.app')
@section('title', 'Help & Support')

@section('styles')
<style>
    .contact-hero { background: var(--charcoal); color: #fff; padding: 3rem 2rem; text-align: center; }
    .contact-hero h1 { font-size: 2rem; font-weight: 800; }
    .contact-hero h1 em { color: var(--coral); font-style: normal; }
    .contact-hero p { color: #aaa; margin-top: 0.5rem; font-size: 0.92rem; }

    .contact-wrapper { max-width: 1000px; margin: 2.5rem auto; padding: 0 1.5rem; }

    /* Quick help */
    .quick-help { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
    .help-card { background: #fff; border: 1px solid #e8e2d8; border-radius: 12px; padding: 1.3rem; text-align: center; cursor: pointer; transition: box-shadow 0.2s, transform 0.2s; text-decoration: none; color: var(--charcoal); }
    .help-card:hover { box-shadow: 0 6px 20px rgba(45,45,45,0.1); transform: translateY(-2px); border-color: var(--coral); }
    .help-card h3 { font-size: 0.88rem; font-weight: 700; margin-bottom: 0.2rem; }
    .help-card p { font-size: 0.78rem; color: #888; }

    /* Layout */
    .contact-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 2rem; }
    @media(max-width: 640px) { .contact-grid { grid-template-columns: 1fr; } }

    /* FAQ */
    .faq-list { margin-bottom: 1.5rem; }
    .faq-item { border-bottom: 1px solid #e8e2d8; padding: 0.9rem 0; }
    .faq-q { font-size: 0.9rem; font-weight: 700; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
    .faq-q:hover { color: var(--coral); }
    .faq-a { font-size: 0.85rem; color: #666; line-height: 1.7; margin-top: 0.5rem; display: none; }
    .faq-item.open .faq-a { display: block; }
    .faq-item.open .faq-q { color: var(--coral); }

    .section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--coral); margin-bottom: 0.4rem; }
    .section-title { font-size: 1.3rem; font-weight: 800; margin-bottom: 1rem; }

    /* Form */
    .support-form { background: #fff; border: 1px solid #e8e2d8; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 24px rgba(45,45,45,0.06); }
    .support-form h2 { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1rem; }
    label { display: block; font-size: 0.83rem; font-weight: 600; margin-bottom: 0.35rem; }
    input[type="text"], input[type="email"], select, textarea {
        width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid #ddd6c8; border-radius: 8px;
        font-size: 0.9rem; background: var(--bone); color: var(--charcoal); outline: none;
        transition: border-color 0.2s; font-family: inherit;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--coral); background: #fff; }
    textarea { resize: vertical; min-height: 110px; }
    .btn-submit { width: 100%; padding: 0.8rem; background: var(--coral); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
    .btn-submit:hover { background: var(--coral-dark); }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }
</style>
@endsection

@section('content')
<div class="contact-hero">
    <h1><em>Help</em> & Support</h1>
    <p>Need help with your order or account? We're here for you.</p>
</div>

<div class="contact-wrapper">
    <!-- Quick Help Cards -->
    <div class="quick-help">
        <a href="#form" class="help-card"><h3>Track My Order</h3><p>Check your delivery status</p></a>
        <a href="#form" class="help-card"><h3>Returns & Refunds</h3><p>Start a return request</p></a>
        <a href="#form" class="help-card"><h3>Payment Issues</h3><p>Billing & payment help</p></a>
        <a href="#form" class="help-card"><h3>Seller Support</h3><p>Help for sellers</p></a>
        <a href="#form" class="help-card"><h3>Account & Security</h3><p>Login & account issues</p></a>
    </div>

    <div class="contact-grid">
        <!-- FAQ -->
        <div>
            <div class="section-label">FAQ</div>
            <h2 class="section-title">Common Questions</h2>
            <div class="faq-list">
                @php
                $faqs = [
                    ['q'=>'How do I track my order?','a'=>'Go to your Dashboard → Orders and click "Track" next to your order. You\'ll get real-time updates from our delivery partners.'],
                    ['q'=>'What is the return policy?','a'=>'You can return eligible items within 30 days of delivery. Items must be unused and in original packaging. Initiate returns from your Orders page.'],
                    ['q'=>'How do I pay for my order?','a'=>'We accept GCash, Maya, credit/debit cards, and Cash on Delivery (COD) for eligible areas.'],
                    ['q'=>'Is it safe to buy on PickSell?','a'=>'Yes! All sellers are verified and every purchase is covered by our Buyer Protection Program. We hold payment until you confirm receipt.'],
                    ['q'=>'How do I become a seller?','a'=>'Register for a free account, go to your Dashboard, and click "Start Selling". Setup takes less than 5 minutes.'],
                ];
                @endphp
                @foreach($faqs as $faq)
                <div class="faq-item">
                    <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">
                        {{ $faq['q'] }}
                    </div>
                    <div class="faq-a">{{ $faq['a'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Contact Form -->
        <div id="form" class="support-form">
            <h2>Send Us a Message</h2>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="/contact">
                @csrf
                <div class="form-group">
                    <label>Issue Type</label>
                    <select name="type">
                        <option>Order / Delivery Issue</option>
                        <option>Return & Refund</option>
                        <option>Payment Problem</option>
                        <option>Account Issue</option>
                        <option>Seller Support</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="name" placeholder="Juan dela Cruz" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label>Order Number (optional)</label>
                    <input type="text" name="order" placeholder="e.g. PS-20240001">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="Describe your issue in detail..." required></textarea>
                </div>
                <button type="submit" class="btn-submit">Submit Request</button>
            </form>
        </div>
    </div>
</div>

<script>
</script>
@endsection
