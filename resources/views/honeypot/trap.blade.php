<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#000000">
    <title>Brread!</title>
    <noscript>
        <meta http-equiv="refresh" content="{{ $remaining + 1 }};url=/admin?resume=1">
        <style>#hz { display: none !important; } #hp-kewer { display: block !important; }</style>
    </noscript>
    @vite(['resources/css/honeypot.css', 'resources/js/honeypot.js'])
</head>
<body>
    <main id="hp" class="hp" data-phase="{{ $phase }}" data-elapsed="{{ $elapsed }}" data-horror="{{ $horror }}" data-kewer="{{ $kewer }}" data-ip="{{ request()->ip() }}" data-music="/audio/lockdown.mp3" data-audio="/audio/prank-fahh.mp3" data-error="/audio/prank-xperror.mp3" data-bsod="/video/prank-bsod.mp4">

        {{-- PHASE A: the horror intro. Built and driven by honeypot.js. --}}
        <section id="hz" class="hz" aria-hidden="true">
            <canvas id="hz-canvas" class="hz-canvas"></canvas>

            <div class="hz-figure" id="hz-figure">
                <svg viewBox="0 0 200 520" preserveAspectRatio="xMidYMax meet">
                    <defs>
                        <radialGradient id="hz-halo" cx="50%" cy="45%" r="50%">
                            <stop offset="0" stop-color="#a3121e" stop-opacity="0.85"/>
                            <stop offset="0.55" stop-color="#5a0710" stop-opacity="0.4"/>
                            <stop offset="1" stop-color="#000" stop-opacity="0"/>
                        </radialGradient>
                    </defs>
                    <ellipse class="hz-halo" cx="100" cy="230" rx="190" ry="330"/>
                    <g class="hz-body">
                        <path d="M100 18 C127 18 138 44 135 74 C132 100 120 113 111 119 L113 135 C142 141 168 152 177 178 C186 212 183 262 187 322 C189 362 193 420 197 470 L189 472 C183 432 177 382 171 332 L167 302 C165 362 161 422 157 520 L43 520 C39 422 35 362 33 302 L29 332 C23 382 17 432 11 472 L3 470 C7 420 11 362 13 322 C17 262 14 212 23 178 C32 152 58 141 87 135 L89 119 C80 113 68 100 65 74 C62 44 73 18 100 18 Z"/>
                        <path class="hz-fingers" d="M190 470 l3 34 M194 468 l9 30 M186 471 l-1 36 M182 470 l-6 30 M10 470 l-3 34 M6 468 l-9 30 M14 471 l1 36 M18 470 l6 30"/>
                    </g>
                    <g class="hz-stare">
                        <ellipse cx="87" cy="72" rx="6" ry="2.6"/>
                        <ellipse cx="113" cy="72" rx="6" ry="2.6"/>
                    </g>
                </svg>
            </div>


            <div class="hz-dark"></div>
            <div class="hz-pulse" id="hz-pulse"></div>

            <div class="hz-eyes" id="hz-eyes"></div>

            <div class="hz-blood" id="hz-blood">
                <svg viewBox="0 0 400 60" preserveAspectRatio="none"><path d="M0 60 L0 26 C30 14 55 36 90 24 C130 10 150 34 190 22 C230 10 260 34 300 20 C335 10 365 30 400 18 L400 60 Z"/></svg>
                <div></div>
            </div>

            <div class="hz-hands" id="hz-hands"></div>

            <div class="hz-center"><p id="hz-text" class="hz-text"></p></div>
        </section>

        {{-- PHASE B: Kewer. --}}
        <section id="hp-kewer" class="hp-kewer" hidden>
            <video id="hp-video" class="hp-video" src="/video/prank-kewer.mp4" loop playsinline preload="auto" disablepictureinpicture disableremoteplayback controlslist="nodownload nofullscreen noremoteplayback" aria-hidden="true"></video>
            <canvas id="hp-canvas" class="hp-canvas" aria-hidden="true"></canvas>
            <div class="hp-scan" aria-hidden="true"></div>
            <div class="hp-vignette" aria-hidden="true"></div>

            <div id="hp-windows" class="hp-windows" aria-hidden="true"></div>
            <div id="hp-stamps" class="hp-stamps" aria-hidden="true"></div>

            <div class="hp-center">
                <p id="hp-text" class="hp-text">mana mungkin seorang Vassell Enha membiarkan amatiran seperti anda bisa akses ke panel admin wkwkwk</p>
            </div>

            <p id="hp-count" class="hp-count" role="timer" aria-live="off">00:{{ str_pad((string) min($remaining, $kewer), 2, '0', STR_PAD_LEFT) }}</p>
        </section>

        <div id="hp-pops" class="hp-pops" aria-hidden="true"></div>
    </main>
</body>
</html>
