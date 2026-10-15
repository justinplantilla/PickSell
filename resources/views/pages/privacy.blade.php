@extends('layouts.app')
@section('title', 'Privacy Policy')

@section('styles')
@vite('resources/css/views/pages-legal.css')
@endsection

@section('content')
<article class="legal-page">
    <h1>Privacy Policy</h1>
    <p class="legal-updated">Last updated: October 15, 2026</p>

    <p>This policy explains what personal information PickSell collects, why, and how it is handled, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> name, sex, date of birth, email address, mobile number and password (stored only in encrypted form).</li>
        <li><strong>Address:</strong> province, municipality, barangay, street and house number, used to route and deliver orders.</li>
        <li><strong>Documents:</strong> government ID, and for sellers, couriers and logistics partners, business permits or vehicle papers, used only to review registrations.</li>
        <li><strong>Activity:</strong> orders, messages, reviews, complaints and delivery records.</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
        <li>To verify your email and review your registration.</li>
        <li>To process orders, route parcels to the right station hub, and let riders deliver them.</li>
        <li>To send account, order and delivery notifications by email.</li>
        <li>To handle complaints, returns, refunds and to keep the marketplace safe.</li>
    </ul>

    <h2>Who can see it</h2>
    <p>Sellers see the name and delivery address for orders they fulfil. Logistics partners and riders see what they need to deliver a parcel. PickSell administrators can see account details to review registrations and resolve issues. We do not sell your personal information.</p>

    <h2>Your rights</h2>
    <p>You may view and update your details under My Account, and delete your account there. You may also ask us to access, correct or erase your data, or object to its processing, by <a href="{{ route('contact') }}">contacting us</a>.</p>

    <h2>Security and retention</h2>
    <p>Passwords and verification codes are stored hashed. We keep order and delivery records as long as needed for disputes, accounting and legal obligations.</p>

    <p>See also our <a href="{{ route('terms') }}">Terms of Service</a>.</p>
</article>
@endsection
