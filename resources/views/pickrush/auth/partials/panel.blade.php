{{-- The coral side panel of the sign-in and registration pages. Needs $tagline and $values ([svg path, text]). --}}
    <aside class="prl-panel">
        <span class="prl-orb" aria-hidden="true"></span>
        <a class="prl-back" href="{{ route('pickrush.dashboard') }}">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to home
        </a>
        <div class="prl-panel-body">
            @include('pickrush.partials.logo-mark', ['class' => 'prl-mark', 'tone' => 'coral', 'size' => 140])
            <div class="prl-name">PickSell <span>Logistics</span></div>
            <p class="prl-tagline">{{ $tagline }}</p>
            <ul class="prl-values">
                @foreach($values as [$icon, $text])
                    <li><span><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span>{{ $text }}</li>
                @endforeach
            </ul>
        </div>

        {{-- A rider loops along the road at the bottom of the panel. --}}
        <div class="prl-road" aria-hidden="true">
            <div class="prl-ride">
                <svg class="prl-rider" viewBox="0 4 90 86">
                    <g class="prp-bob">
                        <rect x="0" y="34" width="28" height="24" rx="3" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
                        <path d="M14 34v24" stroke="#E8472A" stroke-width="3"/>
                        <rect x="14" y="58" width="64" height="9" rx="4.5" fill="#1f2328"/>
                        <path d="M70 60l-7-28" stroke="#1f2328" stroke-width="5" stroke-linecap="round"/>
                        <path d="M56 32h15" stroke="#1f2328" stroke-width="4" stroke-linecap="round"/>
                        <path d="M38 56l8-28" stroke="#1f2328" stroke-width="9" stroke-linecap="round"/>
                        <path d="M46 32l16 2" stroke="#1f2328" stroke-width="5" stroke-linecap="round"/>
                        <circle cx="49" cy="17" r="9" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
                        <path d="M41 15a8 8 0 0 1 16-2h-5" fill="#1f2328"/>
                    </g>
                    <g class="prp-wheel"><circle cx="24" cy="76" r="11" fill="#f6f3ee" stroke="#1f2328" stroke-width="4"/></g>
                    <g class="prp-wheel"><circle cx="74" cy="76" r="11" fill="#f6f3ee" stroke="#1f2328" stroke-width="4"/></g>
                </svg>
            </div>
        </div>
    </aside>
