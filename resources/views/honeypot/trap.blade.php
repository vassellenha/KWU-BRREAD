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
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Bagel+Fat+One&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <noscript>
        <meta http-equiv="refresh" content="{{ $remaining + 1 }};url=/admin?resume=1">
        <style>#hz { display: none !important; } #hp-kewer { display: block !important; }</style>
    </noscript>
    @vite(['resources/css/honeypot.css', 'resources/js/honeypot.js'])
</head>
<body>
    <main id="hp" class="hp" data-phase="{{ $phase }}" data-elapsed="{{ $elapsed }}" data-horror="{{ $horror }}" data-kewer="{{ $kewer }}" data-ip="{{ request()->ip() }}" data-audio="/audio/prank-fahh.mp3" data-error="/audio/prank-xperror.mp3" data-bsod="/video/prank-bsod.mp4">

        {{-- PHASE A: "BRREAD! COLD ROOM 03", a night security camera feed that goes wrong. Built and driven by honeypot.js. --}}
        <section id="hz" class="hz" aria-hidden="true" data-figure="/img/honeypot/vassell-enha.webp">

            {{-- One continuous shot: a dark basement cold room, one door at the back, one motivated light. Driven by honeypot.js on a single clock. --}}
            <div class="cr-room" id="cr-room">
                <div class="cr-layer cr-far" id="cr-far">
                    <svg class="cr-scene" id="cr-scene" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" focusable="false">
                        <defs>
                            <linearGradient id="cr-ceil" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#020202"/><stop offset="1" stop-color="#0d0a09"/></linearGradient>
                            <linearGradient id="cr-wallL" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#050404"/><stop offset="1" stop-color="#16110f"/></linearGradient>
                            <linearGradient id="cr-wallR" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#050404"/><stop offset="1" stop-color="#16110f"/></linearGradient>
                            <linearGradient id="cr-back" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#140f0d"/><stop offset="1" stop-color="#0a0807"/></linearGradient>
                            <linearGradient id="cr-floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0f1517"/><stop offset="1" stop-color="#030505"/></linearGradient>
                            <linearGradient id="cr-hall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c24a68"/><stop offset="0.35" stop-color="#8e2d47"/><stop offset="1" stop-color="#2e0b17"/></linearGradient>
                            <radialGradient id="cr-bulbc" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#9fd0d8" stop-opacity="0.5"/><stop offset="0.4" stop-color="#6aa0ad" stop-opacity="0.14"/><stop offset="1" stop-color="#6aa0ad" stop-opacity="0"/></radialGradient>
                        </defs>
                        <polygon points="0,0 1600,0 1100,210 500,210" fill="url(#cr-ceil)"/>
                        <polygon points="0,0 500,210 500,560 0,900" fill="url(#cr-wallL)"/>
                        <polygon points="1600,0 1100,210 1100,560 1600,900" fill="url(#cr-wallR)"/>
                        <rect x="500" y="210" width="600" height="350" fill="url(#cr-back)"/>
                        <g stroke="rgba(255,255,255,0.03)" stroke-width="2"><path d="M500 300H1100M500 430H1100M570 210V560M1030 210V560"/></g>
                        <polygon points="500,560 1100,560 1600,900 0,900" fill="url(#cr-floor)"/>
                        <g stroke="rgba(150,200,210,0.04)" stroke-width="3"><path d="M650 560L250 900M730 560L520 900M800 560V900M870 560L1080 900M950 560L1350 900"/></g>

                        {{-- the door: a dark steel leaf now, a glowing opening later --}}
                        <rect x="640" y="250" width="320" height="320" fill="#1b1411" stroke="#2c211c" stroke-width="10"/>
                        <g id="cr-doorlight" opacity="0"><rect x="655" y="262" width="290" height="298" fill="url(#cr-hall)"/><circle cx="800" cy="290" r="9" fill="#ffd9e2"/><rect x="655" y="520" width="290" height="40" fill="#ff8aa6" opacity="0.5"/></g>
                        <g id="cr-leaf"><rect x="655" y="262" width="290" height="298" fill="#241a16" stroke="#0d0907" stroke-width="4"/><rect x="680" y="290" width="240" height="110" fill="none" stroke="#33261f" stroke-width="4"/><rect x="680" y="420" width="240" height="120" fill="none" stroke="#33261f" stroke-width="4"/><circle cx="915" cy="420" r="8" fill="#4a3a30"/></g>
                        <g class="cr-sign"><rect x="708" y="214" width="184" height="30" rx="7" fill="#150a0d" stroke="#f7a3b6" stroke-opacity="0.45" stroke-width="2"/><text x="800" y="236" text-anchor="middle" class="cr-sign-text">Brread!</text></g>

                        <g class="cr-wall">
<polygon points="10.0,377.6 475.0,358.0 475.0,363.3 10.0,390.0" fill="#2a1f18" opacity="0.95"/>
<polygon points="10.0,377.6 475.0,358.0 475.0,359.2 10.0,380.2" fill="#ff6f8e" opacity="0.55"/>
<polygon points="25.0,265.2 79.5,270.6 79.5,374.7 25.0,376.9" fill="#52202f" opacity="0.95"/>
<polygon points="25.0,265.2 35.9,266.3 35.9,376.5 25.0,376.9" fill="#34131d" opacity="0.95"/>
<polygon points="77.5,270.4 79.5,270.6 79.5,374.7 77.5,374.7" fill="#ff6f8e" opacity="0.4"/>
<text x="55" y="322" text-anchor="middle" font-size="22" class="cr-stencil">BRREAD!</text>
<polygon points="91.6,268.3 159.9,275.4 159.9,371.3 91.6,374.2" fill="#34403f" opacity="0.95"/>
<polygon points="91.6,268.3 105.3,269.8 105.3,373.6 91.6,374.2" fill="#222a29" opacity="0.95"/>
<polygon points="157.9,275.2 159.9,275.4 159.9,371.3 157.9,371.4" fill="#ff6f8e" opacity="0.4"/>
<text x="129" y="322" text-anchor="middle" font-size="20" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="105" y="278" width="28" height="16" fill="#f7a3b6" opacity="0.6"/><path d="M134 285l-8 3l8 5Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="168.4,283.1 253.2,291.0 253.2,367.4 168.4,370.9" fill="#5a4f3d" opacity="0.95"/>
<polygon points="168.4,283.1 185.3,284.6 185.3,370.2 168.4,370.9" fill="#3a3226" opacity="0.95"/>
<polygon points="251.2,290.8 253.2,291.0 253.2,367.4 251.2,367.4" fill="#ff6f8e" opacity="0.4"/>
<text x="215" y="328" text-anchor="middle" font-size="17" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="185" y="291" width="26" height="14" fill="#f7a3b6" opacity="0.6"/><path d="M211 298l-7 3l7 5Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="267.8,285.4 318.8,290.8 318.8,364.6 267.8,366.8" fill="#5a4f3d" opacity="0.95"/>
<polygon points="267.8,285.4 278.0,286.4 278.0,366.3 267.8,366.8" fill="#3a3226" opacity="0.95"/>
<polygon points="316.8,290.5 318.8,290.8 318.8,364.6 316.8,364.7" fill="#ff6f8e" opacity="0.4"/>
<text x="296" y="327" text-anchor="middle" font-size="14" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="278" y="293" width="22" height="12" fill="#f7a3b6" opacity="0.6"/><path d="M300 298l-6 2l6 4Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="324.0,309.4 400.1,314.7 400.1,361.2 324.0,364.4" fill="#5a4f3d" opacity="0.95"/>
<polygon points="324.0,309.4 339.3,310.5 339.3,363.8 324.0,364.4" fill="#3a3226" opacity="0.95"/>
<polygon points="398.1,314.5 400.1,314.7 400.1,361.2 398.1,361.3" fill="#ff6f8e" opacity="0.4"/>
<text x="366" y="337" text-anchor="middle" font-size="12" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="339" y="315" width="20" height="11" fill="#f7a3b6" opacity="0.6"/><path d="M359 320l-5 2l5 4Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="416.8,312.4 494.8,318.5 494.8,357.2 416.8,360.5" fill="#5a4f3d" opacity="0.95"/>
<polygon points="416.8,312.4 432.4,313.6 432.4,359.8 416.8,360.5" fill="#3a3226" opacity="0.95"/>
<polygon points="492.8,318.3 494.8,318.5 494.8,357.2 492.8,357.3" fill="#ff6f8e" opacity="0.4"/>
<text x="460" y="337" text-anchor="middle" font-size="9" class="cr-stencil">BRREAD!</text>
<polygon points="10.0,590.9 475.0,448.6 475.0,453.9 10.0,603.4" fill="#2a1f18" opacity="0.95"/>
<polygon points="10.0,590.9 475.0,448.6 475.0,449.8 10.0,593.6" fill="#ff6f8e" opacity="0.55"/>
<polygon points="25.0,451.6 98.6,441.6 98.6,563.8 25.0,586.4" fill="#34403f" opacity="0.95"/>
<polygon points="25.0,451.6 39.7,449.6 39.7,581.8 25.0,586.4" fill="#222a29" opacity="0.95"/>
<polygon points="96.6,441.8 98.6,441.6 98.6,563.8 96.6,564.5" fill="#ff6f8e" opacity="0.4"/>
<text x="65" y="510" text-anchor="middle" font-size="22" class="cr-stencil">BRREAD!</text>
<polygon points="112.2,482.5 195.8,466.0 195.8,534.1 112.2,559.7" fill="#52202f" opacity="0.95"/>
<polygon points="112.2,482.5 128.9,479.2 128.9,554.5 112.2,559.7" fill="#34131d" opacity="0.95"/>
<polygon points="193.8,466.4 195.8,466.0 195.8,534.1 193.8,534.7" fill="#ff6f8e" opacity="0.4"/>
<text x="158" y="510" text-anchor="middle" font-size="19" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="129" y="485" width="28" height="15" fill="#f7a3b6" opacity="0.6"/><path d="M157 492l-8 3l8 5Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="202.8,425.3 256.5,418.1 256.5,515.5 202.8,531.9" fill="#34403f" opacity="0.95"/>
<polygon points="202.8,425.3 213.6,423.8 213.6,528.7 202.8,531.9" fill="#222a29" opacity="0.95"/>
<polygon points="254.5,418.4 256.5,418.1 256.5,515.5 254.5,516.1" fill="#ff6f8e" opacity="0.4"/>
<text x="232" y="472" text-anchor="middle" font-size="16" class="cr-stencil">BRREAD!</text>
<polygon points="274.3,421.3 336.2,412.5 336.2,491.1 274.3,510.1" fill="#5a4f3d" opacity="0.95"/>
<polygon points="274.3,421.3 286.7,419.5 286.7,506.3 274.3,510.1" fill="#3a3226" opacity="0.95"/>
<polygon points="334.2,412.8 336.2,412.5 336.2,491.1 334.2,491.7" fill="#ff6f8e" opacity="0.4"/>
<text x="308" y="458" text-anchor="middle" font-size="14" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="287" y="426" width="21" height="12" fill="#f7a3b6" opacity="0.6"/><path d="M308 432l-6 2l6 4Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="350.0,408.0 418.3,398.6 418.3,466.0 350.0,486.9" fill="#4a3f31" opacity="0.95"/>
<polygon points="350.0,408.0 363.6,406.1 363.6,482.7 350.0,486.9" fill="#2f2820" opacity="0.95"/>
<polygon points="416.3,398.8 418.3,398.6 418.3,466.0 416.3,466.6" fill="#ff6f8e" opacity="0.4"/>
<text x="388" y="439" text-anchor="middle" font-size="11" class="cr-stencil">BRREAD!</text>
<polygon points="438.2,417.5 510.0,403.6 510.0,437.9 438.2,459.9" fill="#52202f" opacity="0.95"/>
<polygon points="438.2,417.5 452.6,414.7 452.6,455.5 438.2,459.9" fill="#34131d" opacity="0.95"/>
<polygon points="508.0,403.9 510.0,403.6 510.0,437.9 508.0,438.5" fill="#ff6f8e" opacity="0.4"/>
<text x="478" y="429" text-anchor="middle" font-size="8" class="cr-stencil">BRREAD!</text>
<polygon points="10.0,777.6 475.0,527.9 475.0,533.2 10.0,790.1" fill="#2a1f18" opacity="0.95"/>
<polygon points="10.0,777.6 475.0,527.9 475.0,529.1 10.0,780.3" fill="#ff6f8e" opacity="0.55"/>
<polygon points="25.0,647.5 92.8,621.5 92.8,733.2 25.0,769.6" fill="#33231a" opacity="0.95"/>
<polygon points="25.0,647.5 38.6,642.3 38.6,762.3 25.0,769.6" fill="#221710" opacity="0.95"/>
<polygon points="90.8,622.3 92.8,621.5 92.8,733.2 90.8,734.3" fill="#ff6f8e" opacity="0.4"/>
<text x="62" y="691" text-anchor="middle" font-size="22" class="cr-stencil">BRREAD!</text>
<polygon points="112.6,645.9 168.3,622.1 168.3,692.6 112.6,722.5" fill="#34403f" opacity="0.95"/>
<polygon points="112.6,645.9 123.7,641.2 123.7,716.6 112.6,722.5" fill="#222a29" opacity="0.95"/>
<polygon points="166.3,622.9 168.3,622.1 168.3,692.6 166.3,693.7" fill="#ff6f8e" opacity="0.4"/>
<text x="143" y="669" text-anchor="middle" font-size="19" class="cr-stencil">BRREAD!</text>
<polygon points="188.1,580.8 236.7,562.5 236.7,655.9 188.1,682.0" fill="#34403f" opacity="0.95"/>
<polygon points="188.1,580.8 197.8,577.1 197.8,676.8 188.1,682.0" fill="#222a29" opacity="0.95"/>
<polygon points="234.7,563.2 236.7,562.5 236.7,655.9 234.7,657.0" fill="#ff6f8e" opacity="0.4"/>
<text x="215" y="619" text-anchor="middle" font-size="17" class="cr-stencil">BRREAD!</text>
<polygon points="242.0,577.6 304.0,552.4 304.0,619.7 242.0,653.1" fill="#4a3f31" opacity="0.95"/>
<polygon points="242.0,577.6 254.4,572.6 254.4,646.4 242.0,653.1" fill="#2f2820" opacity="0.95"/>
<polygon points="302.0,553.2 304.0,552.4 304.0,619.7 302.0,620.8" fill="#ff6f8e" opacity="0.4"/>
<text x="276" y="599" text-anchor="middle" font-size="15" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="254" y="578" width="23" height="12" fill="#f7a3b6" opacity="0.6"/><path d="M277 584l-6 2l6 4Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="318.3,534.9 365.1,517.0 365.1,587.0 318.3,612.1" fill="#52202f" opacity="0.95"/>
<polygon points="318.3,534.9 327.6,531.4 327.6,607.1 318.3,612.1" fill="#34131d" opacity="0.95"/>
<polygon points="363.1,517.8 365.1,517.0 365.1,587.0 363.1,588.0" fill="#ff6f8e" opacity="0.4"/>
<text x="344" y="562" text-anchor="middle" font-size="13" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="328" y="537" width="20" height="11" fill="#f7a3b6" opacity="0.6"/><path d="M347 542l-6 2l6 4Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="383.9,526.0 440.1,502.4 440.1,546.7 383.9,576.9" fill="#4a3f31" opacity="0.95"/>
<polygon points="383.9,526.0 395.1,521.3 395.1,570.8 383.9,576.9" fill="#2f2820" opacity="0.95"/>
<polygon points="438.1,503.2 440.1,502.4 440.1,546.7 438.1,547.8" fill="#ff6f8e" opacity="0.4"/>
<text x="415" y="537" text-anchor="middle" font-size="10" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="395" y="525" width="17" height="9" fill="#f7a3b6" opacity="0.6"/><path d="M412 529l-5 2l5 3Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="12.5,200.2 18.5,201.3 18.5,887.4 12.5,891.5" fill="#1f1712" opacity="0.95"/>
<polygon points="235.0,239.8 241.0,240.9 241.0,736.1 235.0,740.2" fill="#1f1712" opacity="0.95"/>
<polygon points="460.0,279.9 466.0,280.9 466.0,583.1 460.0,587.2" fill="#1f1712" opacity="0.95"/>
<polygon points="1575.0,621.2 1350.0,542.5 1350.0,730.0 1575.0,883.0" fill="#2c3534" opacity="0.95"/>
<polygon points="1575.0,586.4 1350.0,517.5 1350.0,555.0 1575.0,638.7" fill="#3b4745" opacity="0.95"/>
<polygon points="1325.0,569.6 1150.0,496.8 1150.0,594.0 1325.0,713.0" fill="#262e2d" opacity="0.95"/>
<polygon points="1325.0,545.7 1150.0,480.6 1150.0,504.9 1325.0,581.6" fill="#35403e" opacity="0.95"/>
<polygon points="1575.0,586.4 1565.0,583.3 1565.0,876.2 1575.0,883.0" fill="#ff6f8e" opacity="0.35"/>
<text x="1480" y="665" text-anchor="middle" font-size="21" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="1502" y="622" width="28" height="15" fill="#f7a3b6" opacity="0.6"/><path d="M1502 629l-9 6l9 9Z" fill="#150c0e" opacity="0.9"/></g>
<text x="1240" y="580" text-anchor="middle" font-size="14" class="cr-stencil">BRREAD!</text>
<g class="cr-label"><rect x="1252" y="556" width="28" height="15" fill="#f7a3b6" opacity="0.6"/><path d="M1252 563l-9 6l9 9Z" fill="#150c0e" opacity="0.9"/></g>
<polygon points="1600.0,90.0 1100.0,245.0 1100.0,255.5 1600.0,117.0" fill="#1c2325" opacity="0.95"/>
<polygon points="1600.0,90.0 1100.0,245.0 1100.0,247.1 1600.0,95.4" fill="#ff6f8e" opacity="0.35"/>
<polygon points="1600.0,180.0 1100.0,280.0 1100.0,290.5 1600.0,207.0" fill="#1c2325" opacity="0.95"/>
<polygon points="1600.0,180.0 1100.0,280.0 1100.0,282.1 1600.0,185.4" fill="#ff6f8e" opacity="0.35"/>
<circle cx="1450" cy="177" r="17" fill="none" stroke="#4a1f29" stroke-width="5"/>
<circle cx="1250" cy="227" r="13" fill="none" stroke="#4a1f29" stroke-width="5"/>
                        </g>

                        <g class="cr-bulbs">
                            <path d="M330 0V104" stroke="#050404" stroke-width="3"/>
                            <circle class="cr-glow" cx="330" cy="116" r="170" fill="url(#cr-bulbc)"/>
                            <circle cx="330" cy="116" r="9" fill="#bfe3ea" opacity="0.85"/>
                        </g>
                        <ellipse cx="800" cy="800" rx="40" ry="8" fill="#010202"/>
                        <g stroke="#0a1011" stroke-width="3"><path d="M772 800H828M778 796V804M792 794V806M806 794V806M820 796V804"/></g>
                        <ellipse class="cr-rip" cx="800" cy="800" rx="14" ry="3" fill="none" stroke="#9fc2c9" stroke-width="2"/>
                        <ellipse class="cr-drop" cx="800" cy="560" rx="3" ry="6" fill="#9fc2c9"/>
                    </svg>

                    <svg class="cr-spill" id="cr-spill" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" focusable="false">
                        <defs>
                            <linearGradient id="cr-ray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff5f86" stop-opacity="0.26"/><stop offset="0.6" stop-color="#d63a63" stop-opacity="0.09"/><stop offset="1" stop-color="#d63a63" stop-opacity="0"/></linearGradient>
                            <linearGradient id="cr-floorlit" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff6f8e" stop-opacity="0.62"/><stop offset="1" stop-color="#b8324f" stop-opacity="0.1"/></linearGradient>
                            <linearGradient id="cr-reflg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff7d9a" stop-opacity="0.55"/><stop offset="1" stop-color="#ff7d9a" stop-opacity="0"/></linearGradient>
                            <filter id="cr-blur6" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="6"/></filter>
                        </defs>
                        <polygon points="655,560 945,560 1480,900 120,900" fill="url(#cr-floorlit)"/>
                        <g class="cr-refl-door" filter="url(#cr-blur6)"><rect x="690" y="574" width="220" height="190" fill="url(#cr-reflg)"/></g>
                        <g class="cr-rays" id="cr-rays" filter="url(#cr-blur6)"><polygon points="652,300 678,300 236,900 122,900" fill="url(#cr-ray)"/><polygon points="702,300 728,300 466,900 352,900" fill="url(#cr-ray)"/><polygon points="752,300 778,300 696,900 582,900" fill="url(#cr-ray)"/><polygon points="802,300 828,300 926,900 812,900" fill="url(#cr-ray)"/><polygon points="852,300 878,300 1156,900 1042,900" fill="url(#cr-ray)"/><polygon points="902,300 928,300 1386,900 1272,900" fill="url(#cr-ray)"/></g>
                    </svg>
                    <svg class="cr-stripes" id="cr-stripes" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" focusable="false">
                        <g fill="#000" opacity="0.62" filter="url(#cr-blur6)"><polygon points="705,560 735,560 470,900 330,900"/><polygon points="880,560 915,560 1250,900 1100,900"/><polygon points="640,566 668,566 40,900 -60,900"/></g>
                    </svg>
                </div>

                <div class="cr-layer cr-mid" id="cr-mid">
                    <div class="cr-sand-wrap" id="cr-sand-wrap">
                        <div class="cr-sand" id="cr-sand">
                            <img class="cr-sand-bread cr-sand-bottom" src="/img/cutout/bread-white.webp" alt="" draggable="false">
                            <img class="cr-sand-ice" src="/img/cutout/ice-strawberry.webp" alt="" draggable="false">
                            <img class="cr-sand-bread cr-sand-top" src="/img/cutout/bread-white.webp" alt="" draggable="false">
                            <svg class="cr-sand-drips" viewBox="0 0 100 60" preserveAspectRatio="none" focusable="false">
                                <path d="M12 0H30V22Q30 30 24 30Q18 30 18 22V12H12Z M46 0H64V34Q64 42 57 42Q50 42 50 34V10H46Z M74 0H90V18Q90 25 84 25Q78 25 78 18V8H74Z" fill="#8a2b40"/>
                            </svg>
                            <div class="cr-sand-lit" id="cr-sand-lit"></div>
                        </div>
                        <div class="cr-ped"><i></i><i></i></div>
                    </div>

                    <canvas class="cr-cast cr-shadow" id="cr-shadow"></canvas>
                    <canvas class="cr-cast cr-reflect" id="cr-reflect"></canvas>
                    <div class="cr-fig" id="cr-fig">
                        <canvas id="cr-fig-canvas" class="cr-fig-canvas"></canvas>
                    </div>
                </div>

                <div class="cr-bloom" id="cr-bloom"></div>
                <div class="cr-fogs" id="cr-fogs"><div class="cr-fog cr-fog-a"></div><div class="cr-fog cr-fog-b"></div><div class="cr-fog cr-fog-c"></div></div>
                <div class="cr-dust" id="cr-dust"><i style="--x:84%;--y:68%;--s:1.6px;--d:10.8s;--w:-5.7s"></i><i style="--x:86%;--y:50%;--s:1.8px;--d:9.4s;--w:-12.1s"></i><i style="--x:48%;--y:57%;--s:3.3px;--d:17.1s;--w:-5.3s"></i><i style="--x:66%;--y:67%;--s:2.2px;--d:16.8s;--w:-9.5s"></i><i style="--x:21%;--y:73%;--s:3.3px;--d:16.3s;--w:-3.8s"></i><i style="--x:89%;--y:80%;--s:2.9px;--d:17.4s;--w:-6.1s"></i><i style="--x:41%;--y:67%;--s:2.1px;--d:12.1s;--w:-11.0s"></i><i style="--x:82%;--y:54%;--s:1.5px;--d:14.5s;--w:-8.9s"></i><i style="--x:15%;--y:74%;--s:2.7px;--d:13.2s;--w:-9.5s"></i><i style="--x:53%;--y:72%;--s:2.8px;--d:15.6s;--w:-0.3s"></i><i style="--x:15%;--y:77%;--s:1.5px;--d:12.3s;--w:-8.8s"></i><i style="--x:46%;--y:71%;--s:2.6px;--d:10.6s;--w:-2.6s"></i><i style="--x:55%;--y:72%;--s:2.0px;--d:16.1s;--w:-1.5s"></i><i style="--x:11%;--y:70%;--s:2.8px;--d:10.2s;--w:-7.0s"></i></div>

                <svg class="cr-melt" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMin slice" focusable="false">
                    <defs><linearGradient id="cr-melt-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b5566c"/><stop offset="0.7" stop-color="#8a2b40"/><stop offset="1" stop-color="#3a0f1a"/></linearGradient></defs>
                    <g class="cr-drip" style="--d: 0s; --h: 1"><path d="M196 0H224V380Q224 410 210 410Q196 410 196 380Z" fill="url(#cr-melt-fill)"/></g>
                    <g class="cr-drip" style="--d: 0.9s; --h: 0.62"><path d="M556 0H580V220Q580 246 568 246Q556 246 556 220Z" fill="url(#cr-melt-fill)"/></g>
                    <g class="cr-drip" style="--d: 0.4s; --h: 0.85"><path d="M1030 0H1056V300Q1056 330 1043 330Q1030 330 1030 300Z" fill="url(#cr-melt-fill)"/></g>
                    <g class="cr-drip" style="--d: 1.5s; --h: 0.5"><path d="M1340 0H1362V170Q1362 192 1351 192Q1340 192 1340 170Z" fill="url(#cr-melt-fill)"/></g>
                </svg>

                <div class="cr-layer cr-near" id="cr-near">
                    <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" focusable="false">
                        <path d="M0 30L420 -10L420 40L0 100Z" fill="#020202"/><path d="M0 100L420 40" stroke="#ff6f8e" stroke-opacity="0.18" stroke-width="2"/>
                        <path d="M1600 120L1180 70L1180 130L1600 190Z" fill="#020202"/><path d="M1600 190L1180 130" stroke="#ff6f8e" stroke-opacity="0.18" stroke-width="2"/>
                        <g class="cr-chain" transform="translate(1440 0)"><path d="M0 0V340" stroke="#050607" stroke-width="5" stroke-dasharray="16 7"/><ellipse cx="0" cy="350" rx="10" ry="16" fill="none" stroke="#050607" stroke-width="5"/></g>
                        <path d="M0 900V760L140 900Z" fill="#010101"/><path d="M1600 900V800L1430 900Z" fill="#010101"/>
                    </svg>
                </div>

                <div class="cr-night"></div>
                <div class="cr-tube" id="cr-tube"></div>
                <svg class="cr-frost" viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">
                    <defs>
                        <filter id="cr-frost-filter" x="0" y="0" width="100%" height="100%">
                            <feTurbulence type="fractalNoise" baseFrequency="0.035 0.05" numOctaves="3" seed="11" result="n"/>
                            <feColorMatrix in="n" type="matrix" values="0 0 0 0 0.78  0 0 0 0 0.92  0 0 0 0 0.96  0 0 0 2.4 -0.95"/>
                        </filter>
                        <radialGradient id="cr-frost-mask" cx="50%" cy="50%" r="72%"><stop offset="0.45" stop-color="#000"/><stop offset="1" stop-color="#fff"/></radialGradient>
                        <mask id="cr-frost-edge"><rect width="100" height="100" fill="url(#cr-frost-mask)"/></mask>
                    </defs>
                    <rect width="100" height="100" filter="url(#cr-frost-filter)" mask="url(#cr-frost-edge)"/>
                </svg>
            </div>

            {{-- Camera overlay --}}
            <canvas id="hz-canvas" class="hz-canvas"></canvas>
            <div class="cr-scan"></div>
            <div class="cr-roll"></div>
            <div class="cr-tear cr-tear-a"></div>
            <div class="cr-tear cr-tear-b"></div>
            <div class="cr-vig"></div>

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
                <p class="cr-objective">OBJECTIVE: LEAVE THE PANEL ALONE</p>
                <div class="cr-hud cr-hud-bottom">
                    <div class="cr-bl"><span id="cr-time">--</span><span>X 41.7  Y 08.2  -18.0 C</span></div>
                    <div class="cr-map" aria-hidden="true"><i class="cr-map-dot" id="cr-map-dot"></i></div>
                </div>
            </div>

            {{-- The owner speaks: a dialogue card, no voice --}}
            <div class="cr-dlg" id="cr-dlg">
                <div class="cr-dlg-tag"><span>VASSELL ENHA</span><i class="cr-wave"><b></b><b></b><b></b><b></b><b></b></i></div>
                <span class="cr-dlg-bar"></span>
                <p class="cr-dlg-text" id="cr-dlg-text"></p>
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

            <div class="hp-center">
                <p id="hp-text" class="hp-text">Mana Mungkin Seorang &quot;Vassell Enha&quot; Memberikan Akses Panel Admin ke Amatiran Seperti Anda.</p>
            </div>

            <p id="hp-count" class="hp-count" role="timer" aria-live="off">00:{{ str_pad((string) min($remaining, $kewer), 2, '0', STR_PAD_LEFT) }}</p>
        </section>

        <div id="hp-pops" class="hp-pops" aria-hidden="true"></div>
    </main>
</body>
</html>
