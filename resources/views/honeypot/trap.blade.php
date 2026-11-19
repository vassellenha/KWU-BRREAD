<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#000000">
    <title>Brread!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Bagel+Fat+One&family=Space+Mono:wght@400;700&family=Oswald:wght@600&family=IBM+Plex+Mono:wght@700&display=swap" rel="stylesheet">
    <noscript>
        <meta http-equiv="refresh" content="{{ $remaining + 1 }};url=/admin?resume=1">
        <style>#hz { display: none !important; } #hp-kewer { display: block !important; }</style>
    </noscript>
    @vite(['resources/css/honeypot.css', 'resources/js/honeypot.js'])
</head>
<body>
    <main id="hp" class="hp" data-phase="{{ $phase }}" data-elapsed="{{ $elapsed }}" data-horror="{{ $horror }}" data-kewer="{{ $kewer }}" data-ip="{{ request()->ip() }}" data-samples="{{ json_encode($samples ?? [], JSON_UNESCAPED_SLASHES) }}" data-audio="/audio/prank-fahh.mp3" data-error="/audio/prank-xperror.mp3" data-bsod="/video/prank-bsod.mp4">

        {{-- PHASE A: "BRREAD! COLD ROOM 03", a night security camera feed that goes wrong. Built and driven by honeypot.js. --}}
        <section id="hz" class="hz" aria-hidden="true" data-figure="/img/honeypot/character-fullbody.webp">

            <div class="cr-screen" id="cr-screen">
            {{-- One continuous shot: a dark basement cold room, one door at the back, one motivated light. Driven by honeypot.js on a single clock. --}}
            <div class="cr-room" id="cr-room">
                {{-- far layer: the cold room plates. Under everything is the open room without its door; the closed plate lies over it with a hole where its door leaf is, and the leaf swings from an atlas on one canvas (frame by frame, 15 fps). The shelf sandwich lives here too, so it follows the room. --}}
                <div class="cr-layer cr-far" id="cr-far">
                    <div class="cr-plate">
                        <img class="cr-img cr-open" id="cr-open" data-asset="opn" alt="" decoding="async">
                        <i class="cr-hole" id="cr-hole"></i>
                        <img class="cr-img cr-closed" id="cr-closed" data-asset="closed" alt="" decoding="async">
                        <div class="cr-egg" id="cr-egg" aria-hidden="true">
                            <img class="cr-egg-img" data-asset="egg" alt="" decoding="async">
                            <img class="cr-egg-img cr-egg-lit" id="cr-egg-lit" data-asset="eggLit" alt="" decoding="async">
                            <i class="cr-egg-glint" id="cr-egg-glint"><b></b></i>
                        </div>
                        <canvas class="cr-door" id="cr-door" aria-hidden="true"></canvas>
                        <i class="cr-doorline" id="cr-doorline"></i>
                        <img class="cr-img cr-spill" id="cr-spill" data-asset="spill" alt="" decoding="async">
                        
                    </div>
                </div>

                {{-- mid layer: the owner (one canvas, drawn from the walk atlas), his cast shadow and reflection, fog --}}
                <div class="cr-layer cr-mid" id="cr-mid">
                    <canvas class="cr-cast cr-shadow" id="cr-shadow"></canvas>
                    <canvas class="cr-cast cr-reflect" id="cr-reflect"></canvas>
                    <div class="cr-fig" id="cr-fig"><canvas class="cr-fig-canvas" id="cr-fig-canvas"></canvas></div>
                    <div class="cr-plate cr-fogs" id="cr-fogs">
                        <img class="cr-fog cr-fog-a" data-asset="fog" alt="" decoding="async"><img class="cr-fog cr-fog-b" data-asset="fog" alt="" decoding="async">
                    </div>
                    <div class="cr-dust" id="cr-dust"><i style="--x:84%;--y:68%;--s:1.6px;--d:10.8s;--w:-5.7s"></i><i style="--x:86%;--y:50%;--s:1.8px;--d:9.4s;--w:-12.1s"></i><i style="--x:48%;--y:57%;--s:3.3px;--d:17.1s;--w:-5.3s"></i><i style="--x:66%;--y:67%;--s:2.2px;--d:16.8s;--w:-9.5s"></i><i style="--x:21%;--y:73%;--s:3.3px;--d:16.3s;--w:-3.8s"></i><i style="--x:89%;--y:80%;--s:2.9px;--d:17.4s;--w:-6.1s"></i><i style="--x:41%;--y:67%;--s:2.1px;--d:12.1s;--w:-11.0s"></i><i style="--x:82%;--y:54%;--s:1.5px;--d:14.5s;--w:-8.9s"></i><i style="--x:15%;--y:74%;--s:2.7px;--d:13.2s;--w:-9.5s"></i><i style="--x:53%;--y:72%;--s:2.8px;--d:15.6s;--w:-0.3s"></i><i style="--x:15%;--y:77%;--s:1.5px;--d:12.3s;--w:-8.8s"></i><i style="--x:46%;--y:71%;--s:2.6px;--d:10.6s;--w:-2.6s"></i><i style="--x:55%;--y:72%;--s:2.0px;--d:16.1s;--w:-1.5s"></i><i style="--x:11%;--y:70%;--s:2.8px;--d:10.2s;--w:-7.0s"></i></div>
                </div>

                <div class="cr-bloom" id="cr-bloom"></div>

                {{-- near layer: pipes and a hanging chain, the strongest parallax --}}
                <div class="cr-layer cr-near" id="cr-near">
                    <div class="cr-plate"><img class="cr-img" data-asset="pipes" alt="" decoding="async"></div>
                </div>

                <div class="cr-night"></div>
            </div>

            {{-- The face reveal and the shot: one plate, cut to by a match cut. The pistol only ever points at the lens. --}}
            <div class="cr-aim-bg" id="cr-aim-bg"></div>
            <div class="cr-aim" id="cr-aim">
                <img class="cr-aim-img" id="cr-aim-img" data-asset="aimclean" alt="" decoding="async">
                {{-- The pistol comes up from below the frame as its own layer (the hand and the pistol cut out of the plate, with two fading trails for motion blur) over a clean plate, then the whole plate takes over. --}}
                <img class="cr-aim-img cr-aim-gun cr-aim-trail" id="cr-aim-gun3" data-asset="aimgun" alt="" decoding="async">
                <img class="cr-aim-img cr-aim-gun cr-aim-trail" id="cr-aim-gun2" data-asset="aimgun" alt="" decoding="async">
                <img class="cr-aim-img cr-aim-gun" id="cr-aim-gun" data-asset="aimgun" alt="" decoding="async">
                <img class="cr-aim-img cr-aim-full" id="cr-aim-full" data-asset="aim" alt="" decoding="async">
                <i class="cr-aim-key" id="cr-aim-key"></i>
                <i class="cr-aim-eyes" id="cr-aim-eyes"></i>
                <i class="cr-aim-warm" id="cr-aim-warm"></i>
            </div>
            <img class="cr-flash-img" id="cr-flash-img" data-asset="flash" alt="" decoding="async">
            <img class="cr-flash-img cr-flash-twin" id="cr-flash-twin" data-asset="flash" alt="" decoding="async">
            <div class="cr-crackwrap" id="cr-crackwrap"><img class="cr-crack-img" id="cr-crack-img" data-asset="crack" alt="" decoding="async"></div>

            {{-- Camera overlay --}}
            <canvas id="hz-canvas" class="hz-canvas"></canvas>
            <div class="cr-scan"></div>
            <div class="cr-roll"></div>
            <div class="cr-tear cr-tear-a"></div>
            <div class="cr-tear cr-tear-b"></div>
            <div class="cr-vig"></div>
            </div>

            {{-- Game HUD, dimmed so the scene leads --}}
            <div class="cr-hudl">
                <i class="cr-br cr-br-tl"></i><i class="cr-br cr-br-tr"></i><i class="cr-br cr-br-bl"></i><i class="cr-br cr-br-brr"></i>
                <div class="cr-hud cr-hud-top"><span class="cr-rec"><i></i>REC</span><span>BRREAD! COLD ROOM 03</span><span>CAM 3</span></div>
                <div class="cr-side">
                    <p class="cr-status"><span class="cr-s-none">MOTION: NONE</span><span class="cr-s-int">INTRUDER DETECTED</span></p>
                    <div class="cr-meter"><em>THREAT</em><span class="cr-bar"><i id="cr-threat"></i></span></div>
                    <div class="cr-meter"><em>NOISE</em><span class="cr-noise" id="cr-noise"><b></b><b></b><b></b><b></b><b></b><b></b><b></b><b></b></span></div>
                </div>
                <div class="cr-alert"><em>ALERT</em><span><i></i><i></i><i></i></span></div>
                <div class="cr-hud cr-hud-bottom">
                    <div class="cr-bl"><span id="cr-time">--</span><span>X 41.7  Y 08.2  -18.0 C</span></div>
                    <div class="cr-map" aria-hidden="true"><i class="cr-map-dot" id="cr-map-dot"></i></div>
                </div>
            </div>

            {{-- The owner speaks: a dialogue card, no voice --}}
            <div class="cr-dlg" id="cr-dlg">
                <div class="cr-dlg-tag"><i class="cr-dlg-rec"></i><span>AUDIO INTERCEPT / MIC 2 / V. ENHA</span></div>
                <p class="cr-dlg-text" id="cr-dlg-text"></p>
                <i class="cr-dlg-mk cr-dlg-mk-a"></i><i class="cr-dlg-mk cr-dlg-mk-b"></i>
            </div>

            <div class="cr-dark"></div>
        </section>

        {{-- PHASE B: Kewer. --}}
        <section id="hp-kewer" class="hp-kewer" hidden>
            <video id="hp-video" class="hp-video" src="/video/prank-kewer.mp4" loop playsinline preload="auto" disablepictureinpicture disableremoteplayback controlslist="nodownload nofullscreen noremoteplayback" aria-hidden="true"></video>
            <canvas id="hp-canvas" class="hp-canvas" aria-hidden="true"></canvas>
            <div class="hp-scan" aria-hidden="true"></div>
            <div class="hp-vignette" aria-hidden="true"></div>

            <div id="hp-windows" class="hp-windows" aria-hidden="true"></div>
            <div id="hp-stamps" class="hp-stamps" aria-hidden="true"></div>

            {{-- The owner's line: the same terminal window as the floating ones, typed in twice (JS builds the letters). --}}
            <div id="hp-say" class="hp-win hp-say" role="status" aria-live="off">
                <header><i></i><i></i><i></i><span>vassell@brread:~$ say</span></header>
                <p id="hp-text" class="hp-say-text">Mana Mungkin Seorang &quot;Vassell Enha&quot; Memberikan Akses Panel Admin ke Amatiran Seperti Anda.</p>
            </div>

            <p id="hp-count" class="hp-count" role="timer" aria-live="off">00:{{ str_pad((string) min($remaining, $kewer), 2, '0', STR_PAD_LEFT) }}</p>
        </section>

        <div id="hp-pops" class="hp-pops" aria-hidden="true"></div>
    </main>
</body>
</html>
