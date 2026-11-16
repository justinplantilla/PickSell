@extends('layouts.app')
@section('title', 'Help Center')

@section('styles')
@vite(['resources/css/views/pages-legal.css', 'resources/css/views/pages-help.css'])
@endsection

@section('content')
{{--
    The footer's quick links land on the sections below. Days and limits come from the same config
    the order flow uses, so this page changes when HQ changes a policy.
--}}
@php
    $returnDays = (int) config('pickrush.policies.return_window_days');
    $confirmDays = \App\Services\Orders\OrderReceipt::AUTO_CONFIRM_DAYS;
    $attempts = (int) config('pickrush.policies.max_delivery_attempts', 3);
    $days = config('pickrush.shipping.delivery_days');
    $eta = fn (int $level) => isset($days[$level]) ? $days[$level][0].'–'.$days[$level][1].' days' : '—';
    $trackUrl = \Illuminate\Support\Facades\Route::has('pickrush.track') ? route('pickrush.track') : null;
@endphp
<article class="legal-page help-page">
    <h1>Help Center</h1>
    <p class="legal-updated">Shipping, returns and everything about your PickSell orders.</p>

    <nav class="help-quicklinks" aria-label="Help topics">
        <a href="#shipping"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>Shipping &amp; Delivery</a>
        <a href="#returns"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6M4 10a8 8 0 1 1 2 5.3"/></svg>Returns &amp; Refunds</a>
        <a href="#cancellations"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM8.5 8.5l7 7M15.5 8.5l-7 7"/></svg>Cancellations</a>
        <a href="#faq"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.01"/></svg>FAQ</a>
        <a href="#selling"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9h16l-1 11H5L4 9ZM8 9V7a4 4 0 0 1 8 0v2"/></svg>Selling on PickSell</a>
    </nav>

    <section id="shipping">
        <h2>Shipping &amp; Delivery</h2>
        <p>Every PickSell order is delivered by a Pickrush logistics partner: a rider picks it up from the seller, it passes through the partner's hubs, and a local rider brings it to your door.</p>
        <h3>How long it takes</h3>
        <div class="help-table-wrap">
            <table class="help-table">
                <thead><tr><th>From the seller to you</th><th>Estimated delivery</th></tr></thead>
                <tbody>
                    <tr><td>Same city or municipality</td><td>{{ $eta(0) }}</td></tr>
                    <tr><td>Same region</td><td>{{ $eta(1) }}</td></tr>
                    <tr><td>North Luzon ↔ South Luzon</td><td>{{ $eta(2) }}</td></tr>
                    <tr><td>Luzon ↔ Visayas or Mindanao</td><td>{{ $eta(3) }}</td></tr>
                    <tr><td>Remote islands</td><td>{{ $eta(4) }}</td></tr>
                </tbody>
            </table>
        </div>
        <p>Days are counted from checkout and are estimates; you see the estimate for your address before you place the order.</p>
        <h3>Shipping fee</h3>
        <ul>
            <li>The fee depends on where the parcel goes and on its size and weight. It is shown at checkout before you order.</li>
            <li>Items you buy from the same store ship together in one parcel, so you pay one shipping fee per store.</li>
            <li>PickSell shipping vouchers come off the fee at checkout.</li>
        </ul>
        <h3>Tracking and delivery attempts</h3>
        <ul>
            <li>Open <strong>My Orders</strong> and tap <strong>View delivery tracking</strong>@if($trackUrl), or enter your tracking number on the <a href="{{ $trackUrl }}">Pickrush tracking page</a>@endif.</li>
            <li>The rider tries up to {{ $attempts }} times. If the parcel still can't be delivered, it goes back to the seller and the order is marked returned.</li>
            <li>Please have the exact cash ready: orders are paid by Cash on Delivery.</li>
        </ul>
    </section>

    <section id="returns">
        <h2>Returns &amp; Refunds</h2>
        <p>You can ask for a return or refund within <strong>{{ $returnDays }} {{ Str::plural('day', $returnDays) }} after delivery</strong>.</p>
        <ol>
            <li>Go to <strong>My Orders</strong> and find the delivered order.</li>
            <li>Tap <strong>Return / Refund</strong>, choose the reason (damaged, wrong item, wrong size or not as described), add photos of the item and the GCash number for your refund.</li>
            <li>The seller reviews your request. If it is approved, a Pickrush rider picks the item up from you; there is nothing to pay.</li>
            <li>Once the seller receives and checks the item, PickSell sends the refund to your GCash.</li>
        </ol>
        <p>If you and the seller can't agree, PickSell reviews the case and decides.</p>
        <h3>Confirming you received your order</h3>
        <p>Once your parcel arrives, check the item, then tap <strong>Order Received</strong>. Then you can rate the product, the seller and the delivery. After you confirm, the regular return is closed (contact Support if something is wrong). If you don't confirm, the order is confirmed for you {{ $confirmDays }} days after delivery, unless you asked for a return.</p>
    </section>

    <section id="cancellations">
        <h2>Cancellations</h2>
        <ul>
            <li>You can cancel from <strong>My Orders</strong> while the order is still <strong>Placed</strong> or <strong>Confirmed</strong>.</li>
            <li>Once the seller starts preparing it, the order can no longer be cancelled. If the seller doesn't ship it by the "Ships by" time shown on the order, it is cancelled for you.</li>
        </ul>
    </section>

    <section id="faq">
        <h2>Frequently asked questions</h2>
        <details class="help-faq">
            <summary>What payment methods can I use?</summary>
            <p>Cash on Delivery. You pay the rider when your parcel arrives.</p>
        </details>
        <details class="help-faq">
            <summary>Can I change my delivery address after ordering?</summary>
            <p>Not on an order that's already placed. Cancel it while it is still Placed or Confirmed and order again with the right address. You can save several addresses in <strong>Profile &amp; settings</strong>.</p>
        </details>
        <details class="help-faq">
            <summary>I ordered from two stores. Why are there two shipping fees?</summary>
            <p>Each store ships its own parcel from its own location, so each parcel has its own fee.</p>
        </details>
        <details class="help-faq">
            <summary>My parcel says delivered but I didn't get it.</summary>
            <p>Check the delivery photo under <strong>Details</strong> in My Orders. If it isn't yours, open <strong>View delivery tracking</strong> and use <strong>Problem with the delivery?</strong> right away. Don't tap Order Received.</p>
        </details>
        <details class="help-faq">
            <summary>How do I contact the seller?</summary>
            <p>Tap <strong>Chat</strong> on the order or on the store's page.</p>
        </details>
    </section>

    <section id="selling">
        <h2>Selling on PickSell</h2>
        <p>Open a store, list your products and let Pickrush pick up your parcels. Sellers use their own account, separate from a buyer account.</p>
        @guest
            <p><a class="help-cta" href="/register?role=seller">Create a seller account</a></p>
        @else
            <p>To sell, log out and create a seller account with a different email address.</p>
        @endguest
    </section>

    <div class="help-contact">
        <div>
            <strong>Still need help?</strong>
            <span>Send us a message and our team will get back to you.</span>
        </div>
        <a class="help-cta" href="{{ route('contact') }}">Contact us</a>
    </div>
</article>
@endsection
