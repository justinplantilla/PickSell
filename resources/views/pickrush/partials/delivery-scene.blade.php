{{--
    Hero animation, one 10s story (timed in public.css): a parcel hops from the PickSell shop onto the scooter, the rider crosses town
    (background layers pan for depth), sets the parcel at the door, the door opens, the windows light and a check pops.
    Shapes are drawn with a top and a side face so the scene reads as 2.5D; landing.js tilts the card and shifts the
    [data-depth] layers with the pointer.
--}}
@php
    // A box with a lit top and a shaded side; front face at ($x, $y), 52×46.
    $box = fn (int $x, int $y) => <<<SVG
        <path d="M{$x} {$y} l10 -8 h52 l-10 8 z" fill="#ffffff" stroke="#1f2328" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M{$x} {$y} m52 0 l10 -8 v46 l-10 8 z" fill="#d6cfc2" stroke="#1f2328" stroke-width="2.5" stroke-linejoin="round"/>
        <rect x="{$x}" y="{$y}" width="52" height="46" rx="3" fill="url(#prs-box)" stroke="#1f2328" stroke-width="2.5"/>
        <path d="M{$x} {$y} m14 0 v46 M{$x} {$y} m14 0 l10 -8" stroke="#E8472A" stroke-width="4"/>
    SVG;
    // Far skyline: [x, top, width]; each block gets a lit roof and a shaded right side.
    $skyline = [[-60, 264, 70], [20, 214, 50], [80, 284, 80], [170, 234, 60], [240, 264, 90], [340, 204, 50], [400, 274, 80], [490, 224, 60], [560, 254, 90], [660, 214, 50], [720, 274, 80], [810, 234, 60]];
@endphp
<div class="prs-status" role="status" aria-label="Parcel status">
    <span class="prs-chip prs-c1"><i></i>Picked up</span>
    <span class="prs-chip prs-c2"><i></i>In transit</span>
    <span class="prs-chip prs-c3"><i></i>Out for delivery</span>
    <span class="prs-chip prs-c4 is-done"><i></i>Delivered</span>
</div>
<svg class="prs" viewBox="0 0 800 460" role="img" aria-label="A Pickrush rider picks up a parcel at a PickSell shop, rides across town and delivers it to a house">
    <defs>
        <radialGradient id="prs-sun" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(130 40) scale(420)">
            <stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
        </radialGradient>
        <linearGradient id="prs-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffaf2"/><stop offset="1" stop-color="#e9e2d6"/></linearGradient>
        <linearGradient id="prs-box" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fffdf8"/><stop offset="1" stop-color="#ebe4d8"/></linearGradient>
        <linearGradient id="prs-road" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b3036"/><stop offset="1" stop-color="#181b1f"/></linearGradient>
        <linearGradient id="prs-body" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#465160"/><stop offset="1" stop-color="#1a1d21"/></linearGradient>
        <radialGradient id="prs-helmet" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#d9d2c6"/></radialGradient>
        <radialGradient id="prs-tree" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#cf4422"/><stop offset="1" stop-color="#9c2b14"/></radialGradient>
        <filter id="prs-soft" x="-50%" y="-200%" width="200%" height="500%"><feGaussianBlur stdDeviation="5"/></filter>
    </defs>

    <rect width="800" height="460" fill="url(#prs-sun)"/>

    <g data-depth style="--d: 2">
        <g class="prs-cloud" fill="#f6f3ee" opacity=".32"><ellipse cx="300" cy="70" rx="46" ry="13"/><ellipse cx="332" cy="61" rx="28" ry="13"/></g>
        <g class="prs-cloud prs-cloud-slow" fill="#f6f3ee" opacity=".24"><ellipse cx="560" cy="48" rx="38" ry="11"/><ellipse cx="586" cy="40" rx="22" ry="11"/></g>
    </g>

    <g data-depth style="--d: 5">
        <g class="prs-far">
            @foreach($skyline as [$bx, $by, $bw])
                <path d="M{{ $bx }} {{ $by }} l12 -9 h{{ $bw }} l-12 9 z" fill="#e2593b"/>
                <path d="M{{ $bx + $bw }} {{ $by }} l12 -9 V384 h-12 z" fill="#b5351a"/>
                <rect x="{{ $bx }}" y="{{ $by }}" width="{{ $bw }}" height="{{ 384 - $by }}" fill="#d0401f"/>
            @endforeach
        </g>
    </g>

    <g data-depth style="--d: 9">
        <g class="prs-mid">
            @foreach([240, 410, 540, 870] as $tx)
                <ellipse cx="{{ $tx + 6 }}" cy="384" rx="22" ry="5" fill="#000" opacity=".18" filter="url(#prs-soft)"/>
                <rect x="{{ $tx - 4 }}" y="330" width="8" height="54" fill="#8c2712"/>
                <circle cx="{{ $tx }}" cy="318" r="24" fill="url(#prs-tree)"/>
            @endforeach
            <g stroke="#1f2328" stroke-width="4" stroke-linecap="round" fill="none"><path d="M320 384 V300 h14"/><path d="M480 384 V300 h14"/><path d="M800 384 V300 h14"/></g>
            <g fill="#ffd27a"><circle cx="336" cy="304" r="5"/><circle cx="496" cy="304" r="5"/><circle cx="816" cy="304" r="5"/></g>
        </g>
    </g>

    <g data-depth style="--d: 14">
        <path class="prs-route" d="M110 206 Q 400 40 690 200" fill="none" stroke="#1f2328" stroke-width="3" stroke-linecap="round" opacity=".5"/>
        <path d="M110 214 c-10 -12 -14 -18 -14 -24 a14 14 0 0 1 28 0 c0 6 -4 12 -14 24 z" fill="#1f2328"/><circle cx="110" cy="190" r="5" fill="#f6f3ee"/>
        <path d="M690 208 c-10 -12 -14 -18 -14 -24 a14 14 0 0 1 28 0 c0 6 -4 12 -14 24 z" fill="#1f2328"/><circle cx="690" cy="184" r="5" fill="#f6f3ee"/>
        <circle r="7" fill="#f6f3ee" stroke="#1f2328" stroke-width="3">
            <animateMotion dur="10s" repeatCount="indefinite" path="M110 206 Q 400 40 690 200" keyPoints="0;0;1;1" keyTimes="0;0.18;0.65;1" calcMode="linear"/>
        </circle>

        {{-- road: far shoulder, surface, near edge --}}
        <path d="M0 384 L20 370 H800 V384 Z" fill="#3a434f"/>
        <rect x="0" y="384" width="800" height="52" fill="url(#prs-road)"/>
        <rect x="0" y="436" width="800" height="12" fill="#101215"/>
        <line x1="14" y1="410" x2="786" y2="410" stroke="#f6f3ee" stroke-width="3" stroke-dasharray="26 18" opacity=".5"/>

        {{-- shop --}}
        <g>
            <ellipse cx="112" cy="386" rx="92" ry="7" fill="#000" opacity=".25" filter="url(#prs-soft)"/>
            <path d="M30 268 l20 -14 h150 l-20 14 z" fill="#fffaf2" stroke="#1f2328" stroke-width="3" stroke-linejoin="round"/>
            <path d="M180 268 l20 -14 v116 l-20 14 z" fill="#d6cfc2" stroke="#1f2328" stroke-width="3" stroke-linejoin="round"/>
            <rect x="30" y="268" width="150" height="116" fill="url(#prs-wall)" stroke="#1f2328" stroke-width="3"/>
            <path d="M178 240 l20 -14 l10 30 l-20 14 z" fill="#2f3741"/>
            <path d="M22 270 h166 l-10 -30 h-146 z" fill="#1f2328"/>
            <path d="M44 240 l-8 30 M76 240 l-5 30 M105 240 v30 M134 240 l5 30 M163 240 l8 30" stroke="#E8472A" stroke-width="7"/>
            <path d="M160 214 l8 -6 v24 l-8 6 z" fill="#2f3741"/>
            <rect x="50" y="214" width="110" height="24" rx="3" fill="#1f2328"/>
            <text x="105" y="231" text-anchor="middle" font-family="Manrope, sans-serif" font-size="14" font-weight="800" fill="#f6f3ee">PickSell</text>
            <rect x="64" y="314" width="52" height="70" fill="#1f2328"/>
            <rect x="128" y="300" width="40" height="36" rx="3" fill="#ffd27a" opacity=".55" stroke="#1f2328" stroke-width="3"/>
        </g>
        <g class="prs-hop">{!! $box(64, 338) !!}</g>

        {{-- house --}}
        <g>
            <ellipse cx="700" cy="386" rx="96" ry="7" fill="#000" opacity=".25" filter="url(#prs-soft)"/>
            <path d="M758 288 l20 -14 v96 l-20 14 z" fill="#d6cfc2" stroke="#1f2328" stroke-width="3" stroke-linejoin="round"/>
            <rect x="622" y="288" width="136" height="96" fill="url(#prs-wall)" stroke="#1f2328" stroke-width="3"/>
            <path d="M690 222 l20 -14 l90 68 l-20 14 z" fill="#2f3741"/>
            <path d="M600 290 L690 222 L780 290 Z" fill="#1f2328"/>
            <rect x="676" y="326" width="28" height="58" fill="#ffd27a"/>
            <rect class="prs-door" x="676" y="326" width="28" height="58" fill="#1f2328"/>
            <rect x="636" y="304" width="28" height="26" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
            <rect class="prs-light" x="638" y="306" width="24" height="22" fill="#ffd27a"/>
            <rect x="718" y="304" width="28" height="26" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
            <rect class="prs-light" x="720" y="306" width="24" height="22" fill="#ffd27a"/>
        </g>
        <g class="prs-check">
            <circle cx="722" cy="200" r="22" fill="#1f2328"/>
            <path d="M711 200 l7 7 l15 -15" fill="none" stroke="#7ee2a8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        </g>

        {{-- rider --}}
        <g class="prs-ride">
            <ellipse cx="262" cy="390" rx="88" ry="7" fill="#000" opacity=".35" filter="url(#prs-soft)"/>
            <circle class="prs-puff" cx="166" cy="354" r="6" fill="#f6f3ee"/>
            <circle class="prs-puff prs-p2" cx="166" cy="354" r="6" fill="#f6f3ee"/>
            <circle class="prs-puff prs-p3" cx="166" cy="354" r="6" fill="#f6f3ee"/>
            <circle cx="195" cy="359" r="22" fill="#0e1013"/>
            <circle cx="315" cy="359" r="22" fill="#0e1013"/>
            <g class="prs-bob">
                <g class="prs-rack">
                    {!! $box(166, 266) !!}
                    @include('pickrush.partials.logo-mark', ['tone' => 'light', 'size' => 22, 'x' => 190, 'y' => 278])
                </g>
                <path d="M176 350 C176 326 194 314 218 314 H256 C266 314 272 322 270 332 L266 350 Z" fill="url(#prs-body)"/>
                <path d="M190 322 C200 316 212 316 230 316" stroke="#6b7685" stroke-width="3" stroke-linecap="round" fill="none" opacity=".6"/>
                <rect x="232" y="342" width="74" height="10" rx="5" fill="#1f2328"/>
                <path d="M302 350 L320 294" stroke="#1f2328" stroke-width="9" stroke-linecap="round"/>
                <path d="M296 358 A26 26 0 0 1 344 358" stroke="#1f2328" stroke-width="6" fill="none" stroke-linecap="round"/>
                <path d="M308 292 H334" stroke="#1f2328" stroke-width="7" stroke-linecap="round"/>
                <circle cx="330" cy="306" r="6" fill="#ffd27a"/>
                <path d="M336 306 l40 -10 v20 z" fill="#ffd27a" opacity=".25"/>
                <rect x="204" y="306" width="50" height="10" rx="5" fill="#2f3741"/>
                <path d="M234 310 L264 318 L272 342" stroke="#2f3741" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <path d="M228 310 L244 260" stroke="#1f2328" stroke-width="22" stroke-linecap="round"/>
                <path d="M232 300 L242 268" stroke="#3a434f" stroke-width="5" stroke-linecap="round"/>
                <path d="M246 268 L294 288 L308 292" stroke="#1f2328" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <circle cx="250" cy="238" r="17" fill="url(#prs-helmet)" stroke="#1f2328" stroke-width="3"/>
                <path d="M254 232 h14 a6 6 0 0 1 0 12 h-14 z" fill="#1f2328"/>
                <path d="M258 234 h6" stroke="#6b7685" stroke-width="2" stroke-linecap="round"/>
                <path d="M236 230 a17 17 0 0 1 26 -8" stroke="#E8472A" stroke-width="4" fill="none" stroke-linecap="round"/>
            </g>
            <g class="prs-wheel"><circle cx="200" cy="362" r="22" fill="#1f2328"/><circle cx="200" cy="362" r="11" fill="#f6f3ee"/><path d="M200 351 v22 M189 362 h22" stroke="#1f2328" stroke-width="2.5"/></g>
            <g class="prs-wheel"><circle cx="320" cy="362" r="22" fill="#1f2328"/><circle cx="320" cy="362" r="11" fill="#f6f3ee"/><path d="M320 351 v22 M309 362 h22" stroke="#1f2328" stroke-width="2.5"/></g>
        </g>

        <g class="prs-drop">{!! $box(456, 266) !!}</g>
    </g>
</svg>
