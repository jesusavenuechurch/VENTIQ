{{-- First-visit greeting on the loading screen: a Mosotho man in a
     blanket lifts his mokorotlo and puts it back on ("Lumela!"). Plain SVG
     and CSS, nothing to download. Runs only when #page-loader has the
     `greet` class (set by the loader script on a first visit). --}}
<style>
    .lumela { display: none; }
    #page-loader.greet .lumela { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    #page-loader.greet .loader-dot { display: none; }
    .lumela svg { width: 132px; height: 145px; overflow: visible; }
    .lumela .who, .lumela .head, .lumela .hat { transform-box: fill-box; }
    .lumela .who { transform-origin: 50% 100%; }
    .lumela .head { transform-origin: 50% 100%; }
    .lumela .hat { transform-origin: 50% 85%; }
    .lumela .word { font: 900 15px/1 Inter, system-ui, sans-serif; letter-spacing: .02em; color: #fff; opacity: 0; }
    .lumela .word span { color: #F07F22; }

    #page-loader.greet .lumela .who  { animation: lumela-in .45s cubic-bezier(.2,.8,.2,1) both; }
    #page-loader.greet .lumela .hat  { animation: lumela-hat 1.5s .35s cubic-bezier(.45,0,.25,1) both; }
    #page-loader.greet .lumela .head { animation: lumela-nod 1.5s .35s ease-in-out both; }
    #page-loader.greet .lumela .word { animation: lumela-word .5s .6s ease-out both; }

    @keyframes lumela-in   { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    /* Up and tipped forward, a moment held, then back on. */
    @keyframes lumela-hat {
        0%   { transform: none; }
        28%  { transform: translate(10px, -38px) rotate(-18deg); }
        62%  { transform: translate(10px, -38px) rotate(-18deg); }
        92%  { transform: translate(0, 1px); }
        100% { transform: none; }
    }
    /* A small bow while the hat is off. */
    @keyframes lumela-nod {
        0%, 25%  { transform: none; }
        42%      { transform: translateY(3px) rotate(4deg); }
        60%      { transform: translateY(3px) rotate(4deg); }
        78%, 100%{ transform: none; }
    }
    @keyframes lumela-word { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    @media (prefers-reduced-motion: reduce) {
        #page-loader.greet .lumela .who, #page-loader.greet .lumela .hat, #page-loader.greet .lumela .head { animation: none; }
        #page-loader.greet .lumela .word { animation: none; opacity: 1; }
    }
</style>

<div class="lumela" aria-hidden="true">
    <svg viewBox="0 0 200 220" role="img">
        <defs>
            <clipPath id="lumela-blanket">
                <path d="M18 222 Q22 168 100 160 Q178 168 182 222 Z"/>
            </clipPath>
        </defs>

        <g class="who">
            {{-- Blanket: orange, with the navy and cream bands of a Basotho blanket --}}
            <path d="M18 222 Q22 168 100 160 Q178 168 182 222 Z" fill="#F07F22"/>
            <g clip-path="url(#lumela-blanket)">
                <rect x="0" y="182" width="200" height="9" fill="#132C4D"/>
                <rect x="0" y="194" width="200" height="3" fill="#F6E7C8"/>
                <rect x="0" y="208" width="200" height="9" fill="#132C4D"/>
                <path d="M60 182 l8 4.5 -8 4.5 -8 -4.5Z M100 182 l8 4.5 -8 4.5 -8 -4.5Z M140 182 l8 4.5 -8 4.5 -8 -4.5Z" fill="#F6E7C8"/>
            </g>
            {{-- Blanket fold over the shoulder --}}
            <path d="M100 160 Q132 168 150 222" fill="none" stroke="#C2570C" stroke-width="3" stroke-linecap="round"/>

            <g class="head">
                <rect x="89" y="138" width="22" height="26" rx="8" fill="#7A4A2A"/>
                <ellipse cx="73.5" cy="120" rx="5" ry="7" fill="#7A4A2A"/>
                <ellipse cx="126.5" cy="120" rx="5" ry="7" fill="#7A4A2A"/>
                <ellipse cx="100" cy="117" rx="26" ry="30" fill="#8D5A36"/>
                <ellipse cx="90" cy="115" rx="2.6" ry="3.2" fill="#1B1B1B"/>
                <ellipse cx="110" cy="115" rx="2.6" ry="3.2" fill="#1B1B1B"/>
                <path d="M86 108 q4 -3 8 0 M106 108 q4 -3 8 0" stroke="#3B2414" stroke-width="2" fill="none" stroke-linecap="round"/>
                <path d="M90 130 Q100 138 110 130" stroke="#3B2414" stroke-width="2.6" fill="none" stroke-linecap="round"/>
                <ellipse cx="85" cy="125" rx="4" ry="2.5" fill="#B8714A" opacity=".55"/>
                <ellipse cx="115" cy="125" rx="4" ry="2.5" fill="#B8714A" opacity=".55"/>

                {{-- Mokorotlo: woven cone, a band near the base, the knot on top --}}
                <g class="hat">
                    <ellipse cx="100" cy="97" rx="45" ry="8.5" fill="#B9862F"/>
                    <path d="M58 96 Q100 24 142 96 Q100 106 58 96 Z" fill="#E2B04F"/>
                    <path d="M67 85 Q100 77 133 85" stroke="#9C6E22" stroke-width="3.4" fill="none"/>
                    <path d="M74 73 Q100 67 126 73 M82 61 Q100 56 118 61 M90 49 Q100 46 110 49" stroke="#C4943A" stroke-width="1.6" fill="none"/>
                    <path d="M82 93 L95 48 M100 96 L100 44 M118 93 L105 48" stroke="#C4943A" stroke-width="1.2" fill="none" opacity=".7"/>
                    <rect x="97" y="31" width="6" height="9" rx="2" fill="#9C6E22"/>
                    <circle cx="100" cy="28" r="5" fill="none" stroke="#9C6E22" stroke-width="3"/>
                </g>
            </g>
        </g>
    </svg>
    <div class="word">Lumela<span>!</span></div>
</div>
