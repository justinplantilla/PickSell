{{-- Footer for Pickrush's public pages (landing and parcel tracking). --}}
<footer class="prp-footer">
    <div class="prp-wrap prp-footer-inner">
        <span class="prp-footer-brand">@include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 28]){{ config('pickrush.portal_name') }} · Delivery partners of PickSell</span>
        <nav aria-label="Footer">
            <a href="{{ route('pickrush.track') }}">Track a parcel</a>
            <a href="{{ route('pickrush.register') }}">Register your company</a>
            <a href="{{ route('pickrush.login') }}">Staff sign-in</a>
            <a href="{{ config('app.url') }}">PickSell shop</a>
        </nav>
        <span>&copy; {{ now()->year }} PickSell</span>
    </div>
</footer>
