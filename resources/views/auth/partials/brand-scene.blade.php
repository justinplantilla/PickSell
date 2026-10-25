{{-- Decorative motion layer for the auth brand panel: four floating marketplace objects (they trade places when the
     page switches between log in and register) and a delivery route that draws during the switch. Hidden on phones. --}}
<div class="auth-floats" aria-hidden="true">
    <span class="auth-float auth-float--bag" style="--depth: 26">
        <span class="auth-float-par"><span class="auth-float-obj">
            <svg viewBox="0 0 80 88" fill="none">
                <defs><linearGradient id="authBag" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff8a6e"/><stop offset="1" stop-color="#d63d21"/></linearGradient></defs>
                <path d="M14 30h52l4 50a6 6 0 0 1-6 6H16a6 6 0 0 1-6-6z" fill="url(#authBag)"/>
                <path d="M66 30l4 50a6 6 0 0 1-2.2 4.6L61 31z" fill="#a92d17" opacity=".55"/>
                <path d="M28 40V27a12 12 0 0 1 24 0v13" stroke="#fff" stroke-width="4.5" stroke-linecap="round"/>
                <circle cx="28" cy="40" r="3" fill="#fff"/><circle cx="52" cy="40" r="3" fill="#fff"/>
                <path d="M18 76h22" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".3"/>
            </svg>
        </span></span>
    </span>
    <span class="auth-float auth-float--tag" style="--depth: 18">
        <span class="auth-float-par"><span class="auth-float-obj">
            <svg viewBox="0 0 80 80" fill="none">
                <defs><linearGradient id="authTag" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fffaf3"/><stop offset="1" stop-color="#e6ddcf"/></linearGradient></defs>
                <path d="M11 17a6 6 0 0 1 6-6h26l32 32a6 6 0 0 1 0 8.5L51.5 75a6 6 0 0 1-8.5 0L11 43z" fill="#b9ad9c"/>
                <path d="M8 14a6 6 0 0 1 6-6h26l32 32a6 6 0 0 1 0 8.5L48.5 72a6 6 0 0 1-8.5 0L8 40z" fill="url(#authTag)"/>
                <circle cx="22" cy="22" r="5" fill="#2a2e34"/>
                <text x="45" y="53" fill="#E8472A" font-family="Manrope, sans-serif" font-size="22" font-weight="800" text-anchor="middle" transform="rotate(45 45 46)">₱</text>
            </svg>
        </span></span>
    </span>
    <span class="auth-float auth-float--pin" style="--depth: 34">
        <span class="auth-float-par"><span class="auth-float-obj">
            <svg viewBox="0 0 70 92" fill="none">
                <defs><linearGradient id="authPin" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff8a6e"/><stop offset="1" stop-color="#c93a20"/></linearGradient></defs>
                <ellipse cx="35" cy="86" rx="14" ry="4" fill="#000" opacity=".28"/>
                <path d="M35 6a26 26 0 0 1 26 26c0 19-26 46-26 46S9 51 9 32A26 26 0 0 1 35 6z" fill="url(#authPin)"/>
                <path d="M35 6a26 26 0 0 1 26 26c0 19-26 46-26 46" fill="#a92d17" opacity=".35"/>
                <circle cx="35" cy="32" r="10" fill="#fff"/>
                <path d="M20 22a17 17 0 0 1 9-8" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".45"/>
            </svg>
        </span></span>
    </span>
    <span class="auth-float auth-float--box" style="--depth: 22">
        <span class="auth-float-par"><span class="auth-float-obj">
            <svg viewBox="0 0 90 90" fill="none">
                <path d="M45 10l35 18-35 18-35-18z" fill="#f3c995"/>
                <path d="M10 28l35 18v38L10 66z" fill="#dba56b"/>
                <path d="M80 28L45 46v38l35-18z" fill="#c38a52"/>
                <path d="M27.5 19l35 18v10" stroke="#fff" stroke-width="7" stroke-linejoin="round" opacity=".55"/>
                <path d="M18 46l14 7v9l-14-7z" fill="#fff" opacity=".7"/>
            </svg>
        </span></span>
    </span>
</div>

<svg class="auth-route" viewBox="0 0 720 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path class="auth-route-path" id="authRoutePath" d="M110 600C260 470 440 790 590 660" pathLength="1"/>
    <circle class="auth-route-dot" r="7">
        <animateMotion dur="1s" begin="indefinite" fill="freeze" calcMode="spline" keyTimes="0;1" keySplines=".65 0 .35 1"><mpath href="#authRoutePath"/></animateMotion>
    </circle>
</svg>
