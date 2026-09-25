@extends('layouts.app')
@section('title', 'Help & Support')

@section('styles')
@vite('resources/css/views/pages-contact.css')
@endsection

@section('content')
<div class="contact-hero">
    <h1><em>Help</em> & Support</h1>
    <p>Need help with your order or account? We're here for you.</p>
</div>

<div class="contact-wrapper">
    <!-- Quick Help Cards -->
    <div class="quick-help">
        <button type="button" class="help-card" data-help-type="Order / Delivery Issue" aria-pressed="false"><h3>Track My Order</h3><p>Check your delivery status</p></button>
        <button type="button" class="help-card" data-help-type="Return & Refund" aria-pressed="false"><h3>Returns & Refunds</h3><p>Start a return request</p></button>
        <button type="button" class="help-card" data-help-type="Payment Problem" aria-pressed="false"><h3>Payment Issues</h3><p>Billing and order help</p></button>
        <button type="button" class="help-card" data-help-type="Seller Support" aria-pressed="false"><h3>Seller Support</h3><p>Help for sellers</p></button>
        <button type="button" class="help-card" data-help-type="Account Issue" aria-pressed="false"><h3>Account & Security</h3><p>Login & account issues</p></button>
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
                    ['q'=>'How do I get help with an order?','a'=>'Choose the closest issue type above and send us the order details. Our support team will guide you through the next steps.'],
                    ['q'=>'Is it safe to buy on PickSell?','a'=>'Yes! All sellers are verified and every purchase is covered by our Buyer Protection Program. We hold payment until you confirm receipt.'],
                    ['q'=>'How do I become a seller?','a'=>'Register for a free account, go to your Dashboard, and click "Start Selling". Setup takes less than 5 minutes.'],
                ];
                @endphp
                @foreach($faqs as $faq)
                <div class="faq-item">
                    <div class="faq-q" data-toggle-parent-class="open">
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
                    <select name="type" id="issueTypeSelect">
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

@vite('resources/js/views/pages-contact.js')
@endsection
