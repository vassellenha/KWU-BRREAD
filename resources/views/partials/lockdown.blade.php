@php
    $open = $open ?? false;
    $hasBackground = file_exists(public_path('img/lockdown-bg.jpg'));
    $hasMusic = file_exists(public_path('audio/lockdown.mp3'));
@endphp
{{-- The decoration around a locked sign-in form. The banner and the locked button live in the form itself. --}}
<div
    id="lockdown"
    class="lockdown"
    data-autostart="{{ $open ? '1' : '0' }}"
    data-preview="{{ ($preview ?? false) ? '1' : '0' }}"
    data-seconds="{{ $seconds ?? 0 }}"
    data-name="{{ $name ?? '' }}"
    data-ip="{{ ($open || ($preview ?? false)) ? request()->ip() : '' }}"
    data-trace="{{ isset($trace) ? json_encode($trace) : '' }}"
    @unless ($open) hidden @endunless
>
    {{-- Shared filters: a rough, wet edge for blood and a soft glow for eyes. --}}
    <svg class="lockdown-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <filter id="lockdown-rough" x="-20%" y="-20%" width="140%" height="140%">
                <feTurbulence type="fractalNoise" baseFrequency="0.05" numOctaves="3" seed="7"/>
                <feDisplacementMap in="SourceGraphic" scale="14"/>
            </filter>
            <filter id="lockdown-glow" x="-200%" y="-200%" width="500%" height="500%">
                <feGaussianBlur stdDeviation="3" result="blur"/>
                <feMerge><feMergeNode in="blur"/><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
            <radialGradient id="lockdown-halo" cx="50%" cy="45%" r="50%">
                <stop offset="0" stop-color="#a3121e" stop-opacity="0.85"/>
                <stop offset="0.55" stop-color="#5a0710" stop-opacity="0.4"/>
                <stop offset="1" stop-color="#000" stop-opacity="0"/>
            </radialGradient>
            <radialGradient id="lockdown-blood-wet" cx="45%" cy="40%" r="65%">
                <stop offset="0" stop-color="#b3101c"/>
                <stop offset="0.7" stop-color="#7a0610"/>
                <stop offset="1" stop-color="#3d0207"/>
            </radialGradient>
        </defs>
    </svg>

    <div class="lockdown-backdrop" aria-hidden="true" @if ($hasBackground) style="--lockdown-bg: url('{{ asset('img/lockdown-bg.jpg') }}')" @endif>
        <span class="lockdown-door"></span>

        {{-- The thing that keeps getting closer. --}}
        <div class="lockdown-stalker" data-stalker>
            <svg viewBox="0 0 200 520" preserveAspectRatio="xMidYMax meet">
                <ellipse class="lockdown-stalker-halo" cx="100" cy="230" rx="190" ry="330"/>
                <g class="lockdown-stalker-body">
                    <path d="M100 18 C127 18 138 44 135 74 C132 100 120 113 111 119 L113 135 C142 141 168 152 177 178 C186 212 183 262 187 322 C189 362 193 420 197 470 L189 472 C183 432 177 382 171 332 L167 302 C165 362 161 422 157 520 L43 520 C39 422 35 362 33 302 L29 332 C23 382 17 432 11 472 L3 470 C7 420 11 362 13 322 C17 262 14 212 23 178 C32 152 58 141 87 135 L89 119 C80 113 68 100 65 74 C62 44 73 18 100 18 Z"/>
                    <path class="lockdown-stalker-fingers" d="M190 470 l3 34 M194 468 l9 30 M186 471 l-1 36 M182 470 l-6 30 M10 470 l-3 34 M6 468 l-9 30 M14 471 l1 36 M18 470 l6 30"/>
                </g>
                <g class="lockdown-stalker-eyes" filter="url(#lockdown-glow)">
                    <ellipse cx="87" cy="72" rx="6" ry="2.6"/>
                    <ellipse cx="113" cy="72" rx="6" ry="2.6"/>
                </g>
            </svg>
        </div>

        <div class="lockdown-eyes" data-eyes></div>

        @foreach (['left', 'right'] as $side)
            <svg class="lockdown-blood lockdown-blood--{{ $side }}" viewBox="0 0 200 260" preserveAspectRatio="xMidYMin meet">
                <defs>
                    <filter id="blood-rough-{{ $side }}">
                        <feTurbulence type="fractalNoise" baseFrequency="0.06" numOctaves="2" seed="{{ $side === 'left' ? 3 : 8 }}"/>
                        <feDisplacementMap in="SourceGraphic" scale="16"/>
                    </filter>
                    <linearGradient id="blood-fill-{{ $side }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#d0141f"/>
                        <stop offset="1" stop-color="#6e050b"/>
                    </linearGradient>
                </defs>
                <g filter="url(#blood-rough-{{ $side }})" fill="url(#blood-fill-{{ $side }})">
                    <path d="M14 40 C22 18 52 22 66 12 C86 0 104 20 122 10 C146 0 172 18 184 38 C194 56 172 60 158 58 C142 66 126 56 108 64 C90 72 76 58 58 66 C38 74 20 66 12 56 C6 50 8 46 14 40 Z"/>
                    <g class="lockdown-drips">
                        <path d="M30 60 h7 v54 a3.5 3.5 0 0 1 -7 0 Z"/>
                        <path d="M50 62 h11 v128 a5.5 5.5 0 0 1 -11 0 Z"/>
                        <path d="M78 62 h4 v70 a2 2 0 0 1 -4 0 Z"/>
                        <path d="M100 60 h13 v34 a6.5 6.5 0 0 1 -13 0 Z"/>
                        <path d="M124 58 h6 v190 a3 3 0 0 1 -6 0 Z"/>
                        <path d="M146 56 h10 v96 a5 5 0 0 1 -10 0 Z"/>
                        <path d="M168 52 h5 v40 a2.5 2.5 0 0 1 -5 0 Z"/>
                    </g>
                </g>
                {{-- Drops that keep falling off the longest drips. --}}
                <g fill="#9e0a14">
                    <ellipse class="lockdown-drop" cx="55.5" cy="196" rx="4" ry="5.5" style="--delay: 0.4s; --every: 3.1s"/>
                    <ellipse class="lockdown-drop" cx="127" cy="252" rx="3" ry="4.5" style="--delay: 1.7s; --every: 2.3s"/>
                    <ellipse class="lockdown-drop" cx="151" cy="156" rx="3.5" ry="5" style="--delay: 2.6s; --every: 3.7s"/>
                </g>
            </svg>
        @endforeach
    </div>

    <p class="lockdown-whisper" data-whisper aria-hidden="true"></p>

    <pre class="lockdown-log lockdown-log--left" aria-hidden="true"></pre>
    <pre class="lockdown-log lockdown-log--right" aria-hidden="true"></pre>

    {{-- Blood that slowly fills the screen from the bottom while the countdown runs. --}}
    <div class="lockdown-pool" aria-hidden="true">
        <svg class="lockdown-pool-wave" viewBox="0 0 1200 60" preserveAspectRatio="none">
            <path d="M0 30 C100 6 200 54 300 30 C400 6 500 54 600 30 C700 6 800 54 900 30 C1000 6 1100 54 1200 30 V60 H0 Z"/>
        </svg>
        <div class="lockdown-pool-body"></div>
    </div>

    <div class="lockdown-hands" data-hands aria-hidden="true"></div>
    <div class="lockdown-glow" data-glow aria-hidden="true"></div>
    <div class="lockdown-vignette" aria-hidden="true"><div class="lockdown-vignette-choke"><div class="lockdown-vignette-pulse" data-pulse></div></div></div>
    <canvas class="lockdown-noise" aria-hidden="true"></canvas>
    <div class="lockdown-tracking" aria-hidden="true"></div>

    {{-- The face. Shown for under a second, now and then. --}}
    <div class="lockdown-scare" data-scare aria-hidden="true">
        <svg viewBox="0 0 400 480">
            <defs>
                <radialGradient id="scare-face" cx="50%" cy="72%" r="62%">
                    <stop offset="0" stop-color="#6b4648"/>
                    <stop offset="0.55" stop-color="#33201f"/>
                    <stop offset="1" stop-color="#070304"/>
                </radialGradient>
                <radialGradient id="scare-socket">
                    <stop offset="0.45" stop-color="#000"/>
                    <stop offset="1" stop-color="#000" stop-opacity="0"/>
                </radialGradient>
                <radialGradient id="scare-mouth" cx="50%" cy="45%" r="55%">
                    <stop offset="0" stop-color="#2a0306"/>
                    <stop offset="0.6" stop-color="#050001"/>
                    <stop offset="1" stop-color="#000"/>
                </radialGradient>
                <linearGradient id="scare-shadow" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#000"/>
                    <stop offset="0.38" stop-color="#000" stop-opacity="0"/>
                    <stop offset="0.8" stop-color="#000" stop-opacity="0"/>
                    <stop offset="1" stop-color="#000" stop-opacity="0.85"/>
                </linearGradient>
                <filter id="scare-skin" x="-10%" y="-10%" width="120%" height="120%">
                    <feTurbulence type="fractalNoise" baseFrequency="0.75" numOctaves="3" seed="4" result="grain"/>
                    <feColorMatrix in="grain" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 0.7 -0.1" result="spots"/>
                    <feComposite in="spots" in2="SourceGraphic" operator="in" result="dirt"/>
                    <feMerge><feMergeNode in="SourceGraphic"/><feMergeNode in="dirt"/></feMerge>
                </filter>
            </defs>
            <g filter="url(#lockdown-rough)">
                <path d="M200 10 C282 10 330 92 326 200 C322 302 290 400 238 462 C222 478 178 478 162 462 C110 400 78 302 74 200 C70 92 118 10 200 10 Z" fill="url(#scare-face)" filter="url(#scare-skin)"/>
                <ellipse cx="140" cy="196" rx="62" ry="74" fill="url(#scare-socket)"/>
                <ellipse cx="260" cy="196" rx="62" ry="74" fill="url(#scare-socket)"/>
                <ellipse cx="142" cy="200" rx="30" ry="44" transform="rotate(14 142 200)" fill="#000"/>
                <ellipse cx="258" cy="200" rx="30" ry="44" transform="rotate(-14 258 200)" fill="#000"/>
                <path d="M146 300 C166 286 234 286 254 300 C274 350 262 438 200 456 C138 438 126 350 146 300 Z" fill="url(#scare-mouth)"/>
                <path class="lockdown-scare-teeth" d="M154 318 L159.1 343 L161 318 Z M162 318 L164.0 344 L169 318 Z M170 318 L171.9 348 L177 318 Z M178 318 L181.4 348 L183 318 Z M183 318 L188.4 351 L192 318 Z M194 318 L196.5 332 L199 318 Z M199 318 L200.3 337 L205 318 Z M206 318 L209.4 349 L213 318 Z M214 318 L217.3 345 L221 318 Z M222 318 L227.4 352 L231 318 Z M232 318 L234.3 337 L238 318 Z M238 318 L243.8 340 L246 318 Z M166 436 L168.4 412 L175 436 Z M175 436 L181.5 418 L184 436 Z M185 436 L188.4 416 L190 436 Z M191 436 L195.0 421 L196 436 Z M197 436 L198.5 422 L203 436 Z M203 436 L207.3 423 L211 436 Z M212 436 L213.5 414 L218 436 Z M219 436 L220.7 419 L225 436 Z M225 436 L228.8 413 L234 436 Z M236 436 L240.1 420 L242 436 Z"/>
                <path class="lockdown-scare-tears" d="M136 242 C134 270 140 296 132 340 M146 244 C150 262 146 278 150 300 M264 242 C268 276 260 300 268 352 M254 244 C250 260 254 270 250 290"/>
            </g>
            <path d="M0 0 H400 V480 H0 Z" fill="url(#scare-shadow)"/>
            <g class="lockdown-scare-hair" fill="none" stroke="#000" stroke-linecap="round">
                <path d="M221 23 C254 104 290 167 284 307" stroke-width="5.1"/><path d="M126 14 C68 119 101 217 81 392" stroke-width="6.0"/><path d="M230 7 C258 139 249 254 246 460" stroke-width="9.5"/><path d="M85 3 C64 157 23 184 8 393" stroke-width="6.4"/><path d="M238 5 C287 86 298 183 307 391" stroke-width="5.3"/><path d="M96 6 C76 102 49 205 57 322" stroke-width="10.3"/><path d="M229 11 C285 158 262 226 276 386" stroke-width="4.1"/><path d="M126 27 C78 157 107 162 95 268" stroke-width="3.1"/><path d="M263 2 C305 139 317 250 333 330" stroke-width="11.0"/><path d="M113 21 C57 150 55 202 37 461" stroke-width="5.8"/><path d="M305 17 C349 86 349 224 374 397" stroke-width="6.1"/><path d="M93 17 C56 147 40 168 23 345" stroke-width="8.9"/><path d="M213 21 C253 129 259 252 258 366" stroke-width="4.8"/><path d="M189 5 C133 133 161 204 139 409" stroke-width="4.6"/><path d="M246 24 C303 150 296 198 306 304" stroke-width="6.4"/><path d="M155 25 C107 156 137 188 141 369" stroke-width="9.7"/><path d="M260 19 C299 105 286 244 310 307" stroke-width="6.3"/><path d="M140 1 C92 126 126 191 108 267" stroke-width="10.0"/><path d="M212 25 C242 91 230 165 242 360" stroke-width="3.2"/><path d="M141 29 C94 96 93 208 97 404" stroke-width="9.5"/><path d="M211 8 C245 136 250 212 261 417" stroke-width="4.4"/><path d="M107 3 C50 138 73 173 67 434" stroke-width="10.2"/><path d="M279 26 C331 156 343 206 356 343" stroke-width="7.6"/><path d="M167 22 C139 152 114 258 90 440" stroke-width="8.1"/><path d="M269 26 C303 87 327 204 336 330" stroke-width="10.3"/><path d="M105 2 C53 94 66 194 49 266" stroke-width="9.5"/>
            </g>
            <g filter="url(#lockdown-glow)" class="lockdown-scare-pupils">
                <circle cx="146" cy="206" r="4.5"/>
                <circle cx="254" cy="206" r="4.5"/>
            </g>
        </svg>
    </div>

    <p class="lockdown-toast" role="status">
        Your IP address has been recorded. Do not try again.
        <span class="lockdown-toast-trace" data-trace-line></span>
    </p>
    <p class="lockdown-corner" data-corner aria-hidden="true">no access</p>

    <template data-hand-template>
        <svg class="lockdown-hand" viewBox="0 0 140 200">
            <g filter="url(#lockdown-rough)" fill="url(#lockdown-blood-wet)">
                <ellipse cx="72" cy="118" rx="38" ry="44"/>
                <rect x="34" y="34" width="15" height="62" rx="7.5" transform="rotate(-14 41 96)"/>
                <rect x="53" y="16" width="16" height="74" rx="8" transform="rotate(-4 61 90)"/>
                <rect x="73" y="14" width="16" height="76" rx="8" transform="rotate(4 81 90)"/>
                <rect x="92" y="30" width="14" height="64" rx="7" transform="rotate(13 99 94)"/>
                <rect x="100" y="96" width="16" height="52" rx="8" transform="rotate(48 108 122)"/>
                <rect x="56" y="150" width="7" height="44" rx="3.5"/>
                <rect x="78" y="152" width="5" height="34" rx="2.5"/>
                <rect x="40" y="140" width="4" height="26" rx="2"/>
            </g>
        </svg>
    </template>

    @if ($hasMusic)
        <audio id="lockdown-music" src="{{ asset('audio/lockdown.mp3') }}" loop preload="none"></audio>
    @endif
</div>
