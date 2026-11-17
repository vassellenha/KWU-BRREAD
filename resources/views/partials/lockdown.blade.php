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
    data-ip="{{ ($open || ($preview ?? false)) ? request()->ip() : '' }}"
    data-trace="{{ isset($trace) ? json_encode($trace) : '' }}"
    @unless ($open) hidden @endunless
>
    <div class="lockdown-backdrop" aria-hidden="true" @if ($hasBackground) style="--lockdown-bg: url('{{ asset('img/lockdown-bg.jpg') }}')" @endif>
        <span class="lockdown-figure lockdown-figure--left"></span>
        <span class="lockdown-figure lockdown-figure--right"></span>
        <span class="lockdown-door"></span>
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
            </svg>
        @endforeach
    </div>

    <pre class="lockdown-log lockdown-log--left" aria-hidden="true"></pre>
    <pre class="lockdown-log lockdown-log--right" aria-hidden="true"></pre>
    <canvas class="lockdown-noise" aria-hidden="true"></canvas>

    <p class="lockdown-toast" role="status">
        IP Anda telah direkam. Jangan mencoba lagi.
        <span class="lockdown-toast-trace" data-trace-line></span>
    </p>
    <p class="lockdown-corner" aria-hidden="true">tidak ada akses</p>

    <button type="button" id="lockdown-sound" class="lockdown-sound" aria-pressed="false" aria-label="Mute sound">
        <span class="material-symbols-outlined" data-icon-on>volume_up</span>
        <span class="material-symbols-outlined" data-icon-off hidden>volume_off</span>
    </button>

    @if ($hasMusic)
        <audio id="lockdown-music" src="{{ asset('audio/lockdown.mp3') }}" loop preload="none"></audio>
    @endif
</div>
