@extends('layouts.app')
@section('title', 'Terms of Service')

@section('styles')
@vite('resources/css/views/pages-legal.css')
@endsection

@section('content')
<article class="legal-page">
    <h1>Terms of Service</h1>
    <p class="legal-updated">Last updated: October 15, 2026</p>

    <p>These terms govern your use of PickSell, an online marketplace that connects buyers, sellers, couriers and logistics partners in the Philippines. By creating an account you agree to them.</p>

    <h2>1. Your account</h2>
    <ul>
        <li>You must be at least 18 years old and give accurate, complete information, including a valid government ID.</li>
        <li>You must verify your email address with the one-time code we send before your registration is reviewed.</li>
        <li>PickSell reviews every registration and may approve or disapprove it. Approved accounts may later be suspended or deactivated for breaking these terms.</li>
        <li>Keep your password private. You are responsible for activity on your account.</li>
    </ul>

    <h2>2. Buying and selling</h2>
    <ul>
        <li>Sellers are responsible for the accuracy of their listings, prices and stock, and for packing orders for pickup.</li>
        <li>Buyers agree to pay for orders they place and to provide a delivery address that couriers can reach.</li>
        <li>Orders may be cancelled before pickup. Returns and refunds follow the process shown on each order.</li>
    </ul>

    <h2>3. Delivery</h2>
    <p>Parcels are collected from sellers and delivered by PickSell's logistics partners and riders. Delivery times are estimates. After repeated failed delivery attempts a parcel may be returned to the seller.</p>

    <h2>4. Prohibited conduct</h2>
    <ul>
        <li>Listing illegal, counterfeit or unsafe items.</li>
        <li>Giving false information, impersonating others, or creating multiple accounts to get around a suspension.</li>
        <li>Harassing other users or interfering with the platform.</li>
    </ul>

    <h2>5. Commissions and fees</h2>
    <p>PickSell charges sellers a commission on completed orders at the rate shown in the seller portal.</p>

    <h2>6. Changes and contact</h2>
    <p>We may update these terms; the date above shows the latest version. Questions? <a href="{{ route('contact') }}">Contact us</a>. See also our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
</article>
@endsection
