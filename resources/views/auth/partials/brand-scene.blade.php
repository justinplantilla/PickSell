{{-- Decorative motion layer for the auth brand panel: a delivery route that draws while the page switches between log in
     and register. Hidden on phones. --}}
<svg class="auth-route" viewBox="0 0 720 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path class="auth-route-path" id="authRoutePath" d="M110 600C260 470 440 790 590 660" pathLength="1"/>
    <circle class="auth-route-dot" r="7">
        <animateMotion dur="1s" begin="indefinite" fill="freeze" calcMode="spline" keyTimes="0;1" keySplines=".65 0 .35 1"><mpath href="#authRoutePath"/></animateMotion>
    </circle>
</svg>
