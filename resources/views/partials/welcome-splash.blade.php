{{--
    Full-screen welcome shown once, on the first page after a successful log in (the login controllers set a session flag
    and the layout pulls it). The exit is a CSS animation, so the dashboard is revealed even if the script never runs;
    welcome-splash.js only lets the user skip it and removes the node afterwards.
    brand: picksell | pickrush. name: first name. role: short label under the greeting.
--}}
@vite(['resources/css/components/welcome-splash.css', 'resources/js/components/welcome-splash.js'])
<div class="welcome-splash welcome-splash--{{ $brand }}" data-welcome-splash role="status" aria-live="polite">
    <span class="ws-orb" aria-hidden="true"></span>

    <svg class="ws-route" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <path class="ws-route-path" id="wsRoutePath" d="M-40 610C220 430 420 760 640 600S1010 420 1240 520" pathLength="1"/>
        <circle class="ws-route-dot" r="9">
            <animateMotion dur="1.3s" begin=".35s" fill="freeze" calcMode="spline" keyTimes="0;1" keySplines=".65 0 .35 1"><mpath href="#wsRoutePath"/></animateMotion>
        </circle>
    </svg>

    <div class="ws-body">
        @if($brand === 'pickrush')
            @include('pickrush.partials.logo-mark', ['class' => 'ws-logo', 'tone' => 'coral', 'size' => 96])
            <div class="ws-brand">Pickrush <span>Logistics</span></div>
        @else
            <img class="ws-logo" src="{{ asset('images/transparent logo.png') }}" alt="">
            <div class="ws-brand">Pick<span>Sell</span></div>
        @endif
        <p class="ws-greeting">Welcome back, {{ $name }}</p>
        <span class="ws-role">{{ $role }}</span>
    </div>

    <span class="ws-hint" aria-hidden="true">Click or press Esc to skip</span>
</div>
