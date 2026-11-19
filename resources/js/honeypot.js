// The /admin honeypot, in two phases: a short cinematic horror intro (phase A), then a hard cut to the
// "Kewer Kewer" video (phase B). Forced fullscreen, no way out. The server keeps the real clock per
// address and hands over the elapsed seconds, so a reload resumes at the right point and never replays the intro. Everything is bounded: one canvas, a fixed pool of windows, stamps and pop-ups,
// transform and opacity only, one requestAnimationFrame loop that sleeps while the tab is hidden.
// Nothing flashes bright: the canvas carries dim grain and thin tinted bars, never a full-screen flash.

const KEY = 'brread-honeypot';
const SPAM_TRIES = 6;
const SPAM_WINDOW = 15000;

const root = document.getElementById('hp');
const video = document.getElementById('hp-video');
const canvas = document.getElementById('hp-canvas');
const text = document.getElementById('hp-text');
const count = document.getElementById('hp-count');

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const lite = (navigator.hardwareConcurrency || 8) <= 4 || window.innerWidth < 640;

const TERM_LINES = [
    '$ ls /admin', 'ls: cannot access: KEWER', '$ sudo su', 'password: ********', 'access denied (amatir)',
    '$ cat /etc/shadow', 'nice try wkwk', '$ nmap -sV brread', '0 open ports, 1 open Kewer', '$ ssh root@brread',
    'permission denied (publickey,kewer)', '$ whoami', 'amatiran', '$ rm -rf /skill', 'cannot remove: no skill found',
    'tracing route ...', 'hop 1: kewer.local', 'hop 2: kewer.kewer', '$ hack --admin', 'hack: command not found',
];
const STAMPS = ['KEWER', 'ACCESS DENIED', 'AMATIR', 'NICE TRY', 'WKWKWK', 'KEWER KEWER'];
const POPS = {
    back: ['Mau ke mana, bos?', 'Tombol Back-nya lagi kewer.', 'Back? Nggak ada Back di sini.'],
    reload: ['Refresh? Tetap di sini.', 'Reload cuma ngulang Kewer.', 'F5 nggak bakal nolong.'],
    key: ['Esc juga kewer.', 'Tombol itu nggak ada gunanya.', 'Ngetik apa pun nggak ngaruh.'],
    menu: ['Klik kanan? Nggak ada menu di sini.', 'Klik kanan dilarang wkwk.'],
    leave: ['Mau kabur? Sayang sekali.', 'Pintunya dikunci. Sama Kewer.'],
};

const state = { start: 0, until: 0, horrorMs: 22000, ip: root?.dataset.ip || '', phase: 'horror', tries: [], bsod: false, popAt: 0, raf: 0, ended: false };

const el = (tag, className, content) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (content !== undefined) node.textContent = content;
    return node;
};
const rand = (min, max) => min + Math.random() * (max - min);
const pick = (list) => list[Math.floor(Math.random() * list.length)];
const later = (fn, ms) => window.setTimeout(fn, ms);

/* ------------------------------------------------------------ hold kept in the browser */

function store() {
    const value = JSON.stringify({ until: state.until, path: '/admin?resume=1' });
    try {
        window.localStorage.setItem(KEY, value);
        window.sessionStorage.setItem(KEY, value);
    } catch {
        // Storage is blocked: the server still keeps the hold.
    }
}

function forget() {
    try {
        window.localStorage.removeItem(KEY);
        window.sessionStorage.removeItem(KEY);
    } catch {
        // Nothing stored.
    }
}

/* ------------------------------------------------------------ fullscreen, keys, sound */

function forceFullscreen() {
    const page = document.documentElement;
    if (document.fullscreenElement || !page.requestFullscreen) return;

    try {
        page.requestFullscreen({ navigationUI: 'hide' })
            .then(() => navigator.keyboard?.lock?.(['Escape']).catch(() => {}))
            .catch(() => {});
    } catch {
        // This browser has no fullscreen for pages.
    }
}

function leaveFullscreen() {
    try {
        navigator.keyboard?.unlock?.();
        if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    } catch {
        // Nothing to leave.
    }
}

function unmute() {
    if (video.muted || video.volume < 1) {
        video.muted = false;
        video.volume = 1;
    }
    if (state.phase === 'kewer' && video.paused && !document.hidden && !state.bsod) video.play().catch(() => {});
}

/** Sound is on and stays on: the video tries to start with sound, and any press unmutes it if the browser said no. */
function lockSound() {
    video.addEventListener('volumechange', unmute);
    video.addEventListener('pause', () => {
        if (!document.hidden && !state.ended && !state.bsod) video.play().catch(() => {});
    });

    video.muted = false;
    video.play().catch(() => {
        video.muted = true;
        video.play().catch(() => {});
    });
}

let sfx = null;
function blip(src) {
    try {
        if (!sfx) sfx = new Audio(src);
        sfx.currentTime = 0;
        sfx.volume = 0.8;
        sfx.play().catch(() => {});
    } catch {
        // No sound effect.
    }
}

/* ------------------------------------------------------------ the countdown */

function secondsLeft() {
    return Math.max(0, Math.ceil((state.until - Date.now()) / 1000));
}

function tick() {
    const elapsed = Date.now() - state.start;

    if (state.phase === 'horror' && elapsed >= state.horrorMs) startKewer();

    if (state.phase === 'kewer') {
        const label = `00:${String(secondsLeft()).padStart(2, '0')}`;
        if (count.textContent !== label) count.textContent = label;
    }

    if (Date.now() >= state.until && !state.ended) finish();
}

function finish() {
    state.ended = true;
    cancelAnimationFrame(state.raf);
    horror.stop();
    forget();
    leaveFullscreen();
    // A moment of grace so the server's clock is surely over too; if not, it just counts the last second.
    later(() => window.location.replace('/'), 500);
}

/* ------------------------------------------------------------ the line, typed in */

/** The line comes back as a short caption over the video, twice: near the start and around the middle. */
function caption() {
    const show = (ms) => {
        if (state.ended) return;
        text.classList.add('is-shown');
        later(() => text.classList.remove('is-shown'), ms);
    };
    later(() => show(6000), 800);
    later(() => show(6000), Math.max(8000, (state.until - Date.now()) / 2));
}

function glitchLoop() {
    if (reduced || state.ended) return;

    // One burst of 240 ms every few seconds: far below three a second.
    if (!document.hidden && state.phase === 'kewer') {
        text.classList.remove('is-glitching');
        void text.offsetWidth;
        text.classList.add('is-glitching');
        if (!lite) {
            root.classList.remove('is-shaking');
            void root.offsetWidth;
            root.classList.add('is-shaking');
        }
    }
    later(glitchLoop, rand(2600, 4200));
}

/* ------------------------------------------------------------ pools: windows, stamps, pop-ups */

const windows = [];
function buildWindows() {
    const host = document.getElementById('hp-windows');
    const total = lite ? 2 : 5;
    const w = window.innerWidth;
    const h = window.innerHeight;

    for (let i = 0; i < total; i += 1) {
        const win = el('div', 'hp-win');
        const bar = el('header');
        bar.append(el('i'), el('span', null, ['TERMINAL', 'root@kewer', 'nmap.exe', 'sshd', 'kewer.sh'][i % 5]));
        const body = el('pre');
        win.append(bar, body);
        win.style.setProperty('--x0', `${rand(0, w * 0.55)}px`);
        win.style.setProperty('--y0', `${rand(h * 0.04, h * 0.5)}px`);
        win.style.setProperty('--x1', `${rand(0, w * 0.6)}px`);
        win.style.setProperty('--y1', `${rand(h * 0.04, h * 0.55)}px`);
        win.style.setProperty('--dur', `${rand(13, 24).toFixed(1)}s`);
        win.style.setProperty('--delay', `${(-rand(0, 10)).toFixed(1)}s`);
        host.append(win);
        windows.push({ body, lines: [] });
    }
}

let lineIndex = 0;
function feedWindows() {
    if (state.ended) return;

    if (!document.hidden) {
        windows.forEach((entry, index) => {
            const next = index === 0 && lineIndex % 7 === 3 && state.ip ? `> trace ${state.ip} ... logged` : TERM_LINES[(lineIndex + index * 3) % TERM_LINES.length];
            entry.lines.push(next);
            if (entry.lines.length > 4) entry.lines.shift();
            entry.body.textContent = entry.lines.join('\n');
        });
        lineIndex += 1;
    }
    later(feedWindows, reduced ? 1600 : 850);
}

const stamps = [];
function buildStamps() {
    const host = document.getElementById('hp-stamps');
    for (let i = 0; i < 3; i += 1) {
        const stamp = el('div', 'hp-stamp');
        host.append(stamp);
        stamps.push(stamp);
    }
}

let stampIndex = 0;
function stampLoop() {
    if (state.ended) return;

    if (!document.hidden) {
        const stamp = stamps[stampIndex % stamps.length];
        stampIndex += 1;
        stamp.classList.remove('is-on');
        stamp.textContent = pick(STAMPS);
        stamp.style.setProperty('--x', `${rand(2, 55)}vw`);
        stamp.style.setProperty('--y', `${rand(8, 48)}vh`);
        stamp.style.setProperty('--r', `${rand(-14, 14).toFixed(1)}deg`);
        void stamp.offsetWidth;
        stamp.classList.add('is-on');
    }
    later(stampLoop, reduced ? 3600 : 2300);
}

const pops = [];
function buildPops() {
    const host = document.getElementById('hp-pops');
    for (let i = 0; i < 3; i += 1) {
        const pop = el('div', 'hp-pop');
        pop.append(el('header', null, 'SYSTEM'), el('p'));
        host.append(pop);
        pops.push(pop);
    }
}

let popIndex = 0;
/** An escape attempt gets its own pop-up (no more than one every 700 ms), and a run of them brings the blue screen. */
function taunt(kind) {
    if (state.ended || state.bsod) return;

    const now = Date.now();
    state.tries = state.tries.filter((at) => now - at < SPAM_WINDOW);
    state.tries.push(now);

    if (state.tries.length >= SPAM_TRIES && secondsLeft() > 4) {
        state.tries = [];
        bsod();
        return;
    }

    if (now - state.popAt < 700) return;
    state.popAt = now;

    const pop = pops[popIndex % pops.length];
    popIndex += 1;
    pop.classList.remove('is-on');
    pop.querySelector('p').textContent = pick(POPS[kind] || POPS.key);
    pop.style.setProperty('--x', `${rand(4, Math.max(8, window.innerWidth - 290))}px`);
    pop.style.setProperty('--y', `${rand(60, Math.max(100, window.innerHeight - 160))}px`);
    void pop.offsetWidth;
    pop.classList.add('is-on');
    blip(root.dataset.audio);
}

/* ------------------------------------------------------------ the blue screen */

function bsod() {
    state.bsod = true;
    const screen = el('div', 'hp-bsod');
    const clip = el('video');
    clip.src = root.dataset.bsod;
    clip.muted = true;
    clip.playsInline = true;
    clip.setAttribute('playsinline', '');
    screen.append(clip);
    document.body.append(screen);
    if (state.phase === 'kewer') video.pause();
    forceFullscreen();

    const err = new Audio(root.dataset.error);
    err.volume = 0.9;
    err.play().catch(() => {});
    clip.play().catch(() => {});

    // Never longer than the hold itself (and gone a moment before it ends).
    const ms = Math.min(8000, Math.max(1500, (secondsLeft() - 1) * 1000));
    later(() => {
        err.pause();
        screen.remove();
        state.bsod = false;
        if (!state.ended && state.phase === 'kewer') video.play().catch(() => {});
    }, ms);
}

/* ------------------------------------------------------------ the canvas: grain and thin tinted bars */

function startCanvas(target, { bars: withBars = true } = {}) {
    const ctx = target.getContext('2d', { alpha: true });
    if (!ctx) return () => {};

    const scale = lite ? 0.25 : 0.4;
    const interval = lite ? 1000 / 20 : 1000 / 30;
    let raf = 0;
    let stopped = false;
    let w = 0;
    let h = 0;
    const resize = () => {
        w = Math.max(80, Math.round(window.innerWidth * scale));
        h = Math.max(80, Math.round(window.innerHeight * scale));
        target.width = w;
        target.height = h;
    };
    resize();
    window.addEventListener('resize', resize);

    const tile = document.createElement('canvas');
    tile.width = 96;
    tile.height = 96;
    const tctx = tile.getContext('2d');
    const image = tctx.createImageData(96, 96);
    for (let i = 0; i < image.data.length; i += 4) {
        const v = Math.random() * 255;
        image.data[i] = v;
        image.data[i + 1] = v;
        image.data[i + 2] = v;
        image.data[i + 3] = 26;
    }
    tctx.putImageData(image, 0, 0);
    const grain = ctx.createPattern(tile, 'repeat');

    // A fixed pool of bars: red and cyan, thin, dim, each alive for a fraction of a second.
    const bars = Array.from({ length: withBars ? (lite ? 3 : 6) : 0 }, () => ({ life: 0, y: 0, h: 0, dx: 0, red: true }));
    let last = 0;
    let nextBar = 0;

    const draw = (time) => {
        if (stopped) return;
        raf = requestAnimationFrame(draw);
        if (document.hidden || time - last < interval) return;
        last = time;

        ctx.clearRect(0, 0, w, h);
        ctx.save();
        ctx.translate(-Math.floor(rand(0, 96)), -Math.floor(rand(0, 96)));
        ctx.fillStyle = grain;
        ctx.fillRect(0, 0, w + 96, h + 96);
        ctx.restore();

        if (time > nextBar && bars.length) {
            const bar = bars.find((item) => item.life <= 0);
            if (bar) {
                bar.life = rand(110, 260);
                bar.y = rand(0, h);
                bar.h = rand(h * 0.01, h * 0.07);
                bar.dx = rand(-8, 8);
                bar.red = Math.random() < 0.5;
            }
            nextBar = time + rand(420, 900);
        }

        bars.forEach((bar) => {
            if (bar.life <= 0) return;
            bar.life -= interval;
            ctx.fillStyle = bar.red ? 'rgba(255, 30, 80, 0.16)' : 'rgba(0, 220, 255, 0.14)';
            ctx.fillRect(bar.dx, bar.y, w, bar.h);
        });
    };

    const stop = () => {
        stopped = true;
        cancelAnimationFrame(raf);
        window.removeEventListener('resize', resize);
    };

    if (reduced) {
        // One still frame of grain, no animation.
        ctx.fillStyle = grain;
        ctx.fillRect(0, 0, w, h);
        return stop;
    }

    raf = requestAnimationFrame(draw);

    return stop;
}

/* ------------------------------------------------------------ the trap itself */

function arm() {
    const bait = () => {
        try {
            history.pushState({ hp: Date.now() }, '', window.location.href);
        } catch {
            // No history to fill.
        }
    };

    bait();
    bait();
    // Browsers skip history entries that never got a press, so one more goes in on the first touch.
    window.addEventListener('pointerdown', bait, { once: true });
    window.addEventListener('keydown', bait, { once: true });
    window.addEventListener('popstate', () => {
        bait();
        taunt('back');
    });

    window.addEventListener('keydown', (event) => {
        const key = event.key.toLowerCase();
        const mod = event.ctrlKey || event.metaKey;
        const reload = event.key === 'F5' || (mod && key === 'r');
        const back = (event.altKey && event.key === 'ArrowLeft') || (event.metaKey && event.key === '[') || event.key === 'BrowserBack';
        // Everything is swallowed; each press is an escape attempt that gets its own answer.
        event.preventDefault();
        event.stopPropagation();
        forceFullscreen();
        if (event.repeat) return;
        taunt(reload ? 'reload' : back ? 'back' : 'key');
    }, { capture: true });

    window.addEventListener('keyup', (event) => event.preventDefault(), { capture: true });

    document.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        taunt('menu');
    });
    ['dragstart', 'selectstart', 'copy', 'cut'].forEach((type) => document.addEventListener(type, (event) => event.preventDefault()));
    window.addEventListener('wheel', (event) => event.preventDefault(), { passive: false });
    window.addEventListener('pointerdown', forceFullscreen, { capture: true });
    // A press unmutes a video the browser started silent.
    window.addEventListener('pointerdown', unmute);
    window.addEventListener('keydown', unmute);

    document.addEventListener('fullscreenchange', () => {
        // Out of fullscreen (the browser's own exit): the next press or key goes straight back in.
        if (!document.fullscreenElement && !state.ended) taunt('leave');
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !state.ended && !state.bsod && state.phase === 'kewer') video.play().catch(() => {});
    });

    // Back from the browser's cache comes back to the real clock.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });
}

/* ------------------------------------------------------------ phase A: BRREAD! COLD ROOM 03 */

// A 30 second shot on a night camera in a basement cold room, built from the real art plates in /img/honeypot.
// ONE master clock (a single requestAnimationFrame with a delta time) drives every layer, the camera, the
// one-shot sounds and the HUD. Everything on screen is a pure function of that clock, so a refresh or a new tab
// simply seeks to the right frame. Sound is one-shot effects only: real silence between them, no loops.
const horror = (() => {
    const $ = (id) => document.getElementById(id);
    const hz = $('hz');
    const room = $('cr-room');
    const screen = $('cr-screen');
    const clock = $('cr-time');
    const grain = $('hz-canvas');
    const layers = { far: $('cr-far'), mid: $('cr-mid'), near: $('cr-near') };
    const openImg = $('cr-open');
    const spill = $('cr-spill');
    const doorline = $('cr-doorline');
    const bloom = $('cr-bloom');
    const fogs = $('cr-fogs');
    const dust = $('cr-dust');
    const sandWrap = $('cr-sand-wrap');
    const sand = $('cr-sand');
    const figEl = $('cr-fig');
    const figCanvas = $('cr-fig-canvas');
    const shadowEl = $('cr-shadow');
    const reflectEl = $('cr-reflect');
    const threat = $('cr-threat');
    const noiseBars = Array.from(document.querySelectorAll('#cr-noise b'));
    const mapDot = $('cr-map-dot');
    const dlg = $('cr-dlg');
    const dlgText = $('cr-dlg-text');
    const aim = $('cr-aim');
    const aimKey = $('cr-aim-key');
    const aimWarm = $('cr-aim-warm');
    const flashImg = $('cr-flash-img');
    const flashTwin = $('cr-flash-twin');
    const crackWrap = $('cr-crackwrap');
    const crackImg = $('cr-crack-img');
    const dark = hz.querySelector('.cr-dark');
    let lineEl = null;
    let dcEl = null;
    let nosig = null;
    let chips = [];
    let sparks = [];

    let ctx = null;
    let stopGrain = () => {};
    let raf = 0;
    let started = false;
    let k = 1;
    let last = 0;
    let vis = NaN;
    let noiseV = 0;
    let stampSec = -1;
    let W = 0;
    let H = 0;
    let PW = 0;
    let PH = 0;
    let geo = null;
    let duckUntil = 0;
    const cls = {};
    const fired = new Set();

    /* ---- one-shot sound effects (WebAudio, synthesized, no music, no loops) ---- */

    const audio = () => {
        if (ctx || !(window.AudioContext || window.webkitAudioContext)) return ctx;
        try {
            ctx = new (window.AudioContext || window.webkitAudioContext)();
        } catch (error) {
            ctx = null;
        }
        return ctx;
    };
    const wake = () => {
        const c = audio();
        if (c && c.state === 'suspended') c.resume().catch(() => {});
    };
    const noise = (c, seconds) => {
        const buffer = c.createBuffer(1, Math.max(1, Math.floor(c.sampleRate * seconds)), c.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
        const source = c.createBufferSource();
        source.buffer = buffer;
        return source;
    };
    const env = (c, at, peak, attack, release) => {
        const gain = c.createGain();
        gain.gain.setValueAtTime(0.0001, at);
        gain.gain.exponentialRampToValueAtTime(peak, at + attack);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + attack + release);
        gain.connect(c.destination);
        return gain;
    };
    const hit = (build) => {
        const c = ctx;
        if (!c || c.state !== 'running') return;
        try {
            build(c, c.currentTime + 0.02);
        } catch (error) {
            // A sound that fails must never touch the visuals.
        }
    };
    const lowpass = (c, f) => {
        const n = c.createBiquadFilter();
        n.type = 'lowpass';
        n.frequency.value = f;
        return n;
    };

    const SFX = {
        buzz: () => hit((c, t) => {
            const out = env(c, t, 0.06, 0.04, 0.8);
            [100, 200, 300].forEach((f, i) => {
                const o = c.createOscillator();
                o.type = 'sawtooth';
                o.frequency.value = f;
                const g = c.createGain();
                g.gain.value = 0.5 / (i + 1);
                o.connect(g).connect(out);
                o.start(t);
                o.stop(t + 1);
            });
        }),
        drip: () => hit((c, t) => {
            [0, 0.17].forEach((delay, i) => {
                const o = c.createOscillator();
                o.type = 'sine';
                o.frequency.setValueAtTime(900, t + delay);
                o.frequency.exponentialRampToValueAtTime(1500, t + delay + 0.05);
                o.connect(env(c, t + delay, 0.09 / (i + 1), 0.003, 0.12));
                o.start(t + delay);
                o.stop(t + delay + 0.2);
            });
        }),
        crunch: () => hit((c, t) => {
            [0, 0.07, 0.15].forEach((delay) => {
                const n = noise(c, 0.08);
                const f = c.createBiquadFilter();
                f.type = 'bandpass';
                f.frequency.value = 2600 + Math.random() * 1400;
                n.connect(f).connect(env(c, t + delay, 0.26, 0.002, 0.06));
                n.start(t + delay);
            });
        }),
        // A hinge that wants oil: a slow saw glide through a narrow band, with grit on top.
        creak: () => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(140, t);
            o.frequency.linearRampToValueAtTime(210, t + 1.0);
            o.frequency.linearRampToValueAtTime(170, t + 2.0);
            const bp = c.createBiquadFilter();
            bp.type = 'bandpass';
            bp.frequency.setValueAtTime(520, t);
            bp.frequency.linearRampToValueAtTime(900, t + 1.2);
            bp.Q.value = 6;
            const g = c.createGain();
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.1, t + 0.35);
            g.gain.exponentialRampToValueAtTime(0.05, t + 1.3);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 2.1);
            o.connect(bp).connect(g).connect(c.destination);
            o.start(t);
            o.stop(t + 2.2);
        }),
        // A heavy boot on concrete, duller and quieter while he is still far away.
        foot: (g = 0.5) => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(82, t);
            o.frequency.exponentialRampToValueAtTime(34, t + 0.24);
            o.connect(lowpass(c, 160 + 900 * g)).connect(env(c, t, 0.15 + 0.55 * g, 0.006, 0.34));
            o.start(t);
            o.stop(t + 0.5);
            const n = noise(c, 0.1);
            n.connect(lowpass(c, 220 + 600 * g)).connect(env(c, t, 0.06 + 0.3 * g, 0.004, 0.09));
            n.start(t);
        }),
        rustle: () => hit((c, t) => {
            const n = noise(c, 1);
            const f = c.createBiquadFilter();
            f.type = 'bandpass';
            f.frequency.value = 1500;
            f.Q.value = 0.8;
            const g = c.createGain();
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.06, t + 0.35);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.95);
            n.connect(f).connect(g).connect(c.destination);
            n.start(t);
        }),
        sting: (g = 1) => hit((c, t) => {
            [55, 58.3, 82].forEach((freq) => {
                const o = c.createOscillator();
                o.type = 'sawtooth';
                o.frequency.value = freq;
                const f = lowpass(c, 900);
                f.frequency.setValueAtTime(900, t);
                f.frequency.exponentialRampToValueAtTime(120, t + 1.1);
                o.connect(f).connect(env(c, t, 0.11 * g, 0.015, 1.1));
                o.start(t);
                o.stop(t + 1.3);
            });
        }),
        // The one heavy low thud under the face reveal.
        boom: (g = 1) => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(70, t);
            o.frequency.exponentialRampToValueAtTime(24, t + 0.7);
            o.connect(env(c, t, 0.85 * g, 0.01, 0.42));
            o.start(t);
            o.stop(t + 0.5);
            const n = noise(c, 0.25);
            n.connect(lowpass(c, 260)).connect(env(c, t, 0.4, 0.004, 0.22));
            n.start(t);
        }),
        // The shot: a hard crack, a low thump and a short tail. Nothing here is a weapon sound effect from media.
        shot: () => hit((c, t) => {
            const n = noise(c, 0.3);
            const bp = c.createBiquadFilter();
            bp.type = 'bandpass';
            bp.frequency.value = 1900;
            bp.Q.value = 0.5;
            n.connect(bp).connect(env(c, t, 0.7, 0.001, 0.09));
            n.start(t);
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(150, t);
            o.frequency.exponentialRampToValueAtTime(38, t + 0.22);
            o.connect(env(c, t, 0.75, 0.002, 0.3));
            o.start(t);
            o.stop(t + 0.4);
            const tail = noise(c, 0.7);
            tail.connect(lowpass(c, 700)).connect(env(c, t + 0.05, 0.22, 0.01, 0.55));
            tail.start(t + 0.05);
        }),
        glass: () => hit((c, t) => {
            [0, 0.06, 0.13, 0.21, 0.33, 0.47].forEach((delay, i) => {
                const n = noise(c, 0.06);
                const hp = c.createBiquadFilter();
                hp.type = 'highpass';
                hp.frequency.value = 3200 + i * 500;
                n.connect(hp).connect(env(c, t + delay, 0.24 - i * 0.025, 0.001, 0.05));
                n.start(t + delay);
                const o = c.createOscillator();
                o.type = 'sine';
                o.frequency.value = 3800 + i * 610;
                o.connect(env(c, t + delay, 0.05, 0.001, 0.12));
                o.start(t + delay);
                o.stop(t + delay + 0.15);
            });
        }),
        zap: () => hit((c, t) => {
            for (let i = 0; i < 7; i += 1) {
                const at = t + i * 0.028;
                const n = noise(c, 0.03);
                const hp = c.createBiquadFilter();
                hp.type = 'highpass';
                hp.frequency.value = 2400;
                n.connect(hp).connect(env(c, at, 0.17, 0.001, 0.024));
                n.start(at);
            }
            const o = c.createOscillator();
            o.type = 'square';
            o.frequency.setValueAtTime(90, t);
            o.frequency.exponentialRampToValueAtTime(1800, t + 0.22);
            o.connect(lowpass(c, 2400)).connect(env(c, t, 0.06, 0.004, 0.22));
            o.start(t);
            o.stop(t + 0.3);
        }),
        // The tube losing power: one falling electronic whine.
        whine: () => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(3200, t);
            o.frequency.exponentialRampToValueAtTime(55, t + 0.65);
            o.connect(env(c, t, 0.1, 0.01, 0.65));
            o.start(t);
            o.stop(t + 0.8);
        }),
        thud: () => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(62, t);
            o.frequency.exponentialRampToValueAtTime(26, t + 0.5);
            o.connect(env(c, t, 0.85, 0.006, 0.6));
            o.start(t);
            o.stop(t + 0.7);
        }),
    };

    /* ---- small maths: everything is a pure function of the clock ---- */

    const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
    const smooth = (x) => { const v = clamp(x); return v * v * (3 - 2 * v); };
    const inOut = (x) => { const v = clamp(x); return v < 0.5 ? 4 * v * v * v : 1 - ((-2 * v + 2) ** 3) / 2; };
    const outCubic = (x) => 1 - (1 - clamp(x)) ** 3;
    const lerp = (a, b, x) => a + (b - a) * x;
    const flag = (name, on, target = hz) => {
        if (cls[name] === on) return;
        cls[name] = on;
        target.classList.toggle(name, on);
    };
    // A short event: quick rise, slow fall. Used for the thin red line and the noise meter.
    const pulse = (u, at, rise, fall) => {
        const x = u - at;
        if (x < 0 || x > rise + fall) return 0;
        return x < rise ? x / rise : 1 - (x - rise) / fall;
    };

    /* ---- beat sheet, in seconds of the 30 second shot (scaled by k when HORROR_SECONDS differs) ---- */

    const T = {
        boot: 2.2, // the room fades up from black
        knock: 5.4, // one heavy thud behind the door
        door0: 7.0, // the door opens, slowly
        door1: 10.0,
        appear: 8.3, // the owner stands in the doorway
        alert: 8.6,
        reveal0: 24.0, // match cut to the aim plate
        key0: 24.2, // the key light ramps up on his face
        key1: 25.4,
        shot: 26.6, // silence before it: 26.2 to 26.6
        black: 29.3,
    };
    // Weighted steps: each one an eased move with silence between them; he stops once, in total silence, around 13 s.
    const STEPS = [[10.0, 0.12], [10.85, 0.14], [11.7, 0.14], [14.0, 0.2], [14.75, 0.2], [15.4, 0.2]];
    const STEP_DUR = 0.8;
    const LAST_STEP = STEPS[STEPS.length - 1][0] + STEP_DUR;
    const P1 = [16.1, 19.0];
    const P2 = [19.8, 24.0];
    const QUIET = [[12.5, 14.0], [26.1, 26.55], [T.black, 31]]; // windows where the cue is faded to nothing

    const EVENTS = [
        [0.9, () => SFX.buzz()],
        [3.6, () => SFX.drip()],
        [T.knock, () => SFX.boom(1)],
        [6.95, () => SFX.creak()],
        [10.3, () => SFX.foot(0.3)],
        [10.9, () => SFX.rustle()],
        [11.15, () => SFX.foot(0.42)],
        [12.0, () => SFX.foot(0.52)],
        [14.3, () => SFX.foot(0.62)],
        [15.05, () => SFX.foot(0.78)],
        [15.7, () => SFX.foot(0.95)],
        [16.35, () => SFX.rustle()],
        [16.7, () => SFX.sting(0.55)],
        [19.35, () => SFX.drip()],
        [21.15, () => SFX.sting(1)],
        [24.05, () => SFX.boom(0.6)],
        [T.shot, () => SFX.shot()],
        [26.72, () => SFX.glass()],
        [27.15, () => SFX.zap()],
        [28.1, () => SFX.whine()],
        [29.55, () => SFX.thud()],
    ];

    const threatAt = (u) => {
        if (u < T.knock) return 0;
        if (u < T.alert) return lerp(0, 0.08, smooth((u - T.knock) / 1.5));
        if (u < 16) return lerp(0.08, 0.62, smooth((u - T.alert) / 7.4));
        if (u < 19.8) return lerp(0.62, 0.74, smooth((u - 16) / 3.8));
        if (u < 24) return lerp(0.74, 0.92, smooth((u - 19.8) / 4.2));
        return lerp(0.92, 1, smooth((u - 24) / 1.2));
    };

    const walkAt = (u) => {
        if (reduced) return { p: 1, bob: 0 };
        let p = 0;
        let bob = 0;
        STEPS.forEach(([start, weight]) => {
            const x = (u - start) / STEP_DUR;
            p += weight * smooth(x);
            if (x > 0 && x < 1) bob = Math.max(bob, Math.sin(Math.PI * x));
        });
        return { p: clamp(p), bob };
    };

    /* ---- the art plates: a need-ordered manifest; nothing starts until what the current moment needs is decoded ---- */

    const ART = '/img/honeypot/';
    const ASSETS = [
        { key: 'closed', src: `${ART}room-closed.webp`, from: 0, until: 10.5 },
        { key: 'sand', src: `${ART}sandwich-hero.webp`, from: 2.2, until: 24.2 },
        { key: 'fog', src: `${ART}fog.webp`, from: 2.2, until: 24.2 },
        { key: 'pipes', src: `${ART}foreground-pipes.webp`, from: 2.2, until: 24.2 },
        { key: 'open', src: `${ART}room-open.webp`, from: 6.6, until: 24.2 },
        { key: 'spill', src: `${ART}light-spill.webp`, from: 6.6, until: 24.2 },
        { key: 'char', src: hz.dataset.figure, from: 8.0, until: 24.2 },
        { key: 'aim', src: `${ART}aim-pose.webp`, from: 23.6, until: 28.6 },
        { key: 'flash', src: `${ART}muzzle-flash.webp`, from: 26.0, until: 27.4 },
        { key: 'crack', src: `${ART}glass-crack.webp`, from: 26.0, until: 28.6 },
    ];
    const asset = (key) => ASSETS.find((a) => a.key === key);
    const needed = (a, u) => a.from <= u + 0.25 && a.until > u;
    // True when every plate the moment u needs is decoded (a plate that failed to load counts: the scene just stays dark there).
    const gate = (u) => ASSETS.every((a) => a.ready || !needed(a, u));
    const loadAsset = (a) => new Promise((resolve) => {
        const img = new Image();
        img.decoding = 'async';
        img.src = a.src;
        const done = () => {
            a.img = img;
            a.ready = true;
            if (img.naturalWidth) hz.querySelectorAll(`[data-asset="${a.key}"]`).forEach((n) => { n.src = a.src; });
            resolve();
        };
        (img.decode ? img.decode() : new Promise((ok) => { img.onload = ok; img.onerror = ok; })).then(done, done);
    });
    // Two at a time, most urgent first, so the closed room is up before the rest is even asked for.
    const preload = (u) => {
        const queue = ASSETS.filter((a) => a.until > u).sort((a, b) => a.from - b.from);
        let at = 0;
        const next = () => {
            if (at >= queue.length) return;
            const a = queue[at];
            at += 1;
            loadAsset(a).then(next);
        };
        next();
        next();
    };

    /* ---- layout: the plates are 3:2 sheets laid out "cover"; the aim plate is composed for the shape of the screen ---- */

    const AIM_MUZZLE = [0.347, 0.533];
    const AIM_FACE = [0.53, 0.38];
    const layout = () => {
        W = hz.clientWidth || window.innerWidth;
        H = hz.clientHeight || window.innerHeight;
        PW = Math.max(W, 1.5 * H);
        PH = PW / 1.5;
        hz.style.setProperty('--pw', `${PW.toFixed(1)}px`);
        hz.style.setProperty('--ph', `${PH.toFixed(1)}px`);

        // Portrait phones take the plate natively (cover by height). On landscape the plate is shown at about 0.7 of
        // its height (face and muzzle in frame, never upscaled past 1.5x) and the dark red sides are extended by a mask.
        const ah = Math.min(clamp(1.5 * W, H, 1.47 * H), 2304);
        const aw = ah / 1.5;
        const tt = clamp((ah / H - 1) / 0.47);
        const at = H / 2 - (0.5 - 0.14 * tt) * ah;
        aim.style.setProperty('--aw', `${aw.toFixed(1)}px`);
        aim.style.setProperty('--ah', `${ah.toFixed(1)}px`);
        aim.style.setProperty('--at', `${at.toFixed(1)}px`);
        const mx = W / 2 + (AIM_MUZZLE[0] - 0.5) * aw;
        const my = at + AIM_MUZZLE[1] * ah;
        const afx = W / 2 + (AIM_FACE[0] - 0.5) * aw;
        const afy = at + AIM_FACE[1] * ah;
        geo = { mx, my, afx, afy, aw, ah };
        aim.style.transformOrigin = `${mx.toFixed(1)}px ${my.toFixed(1)}px`;
        aim.style.setProperty('--fx', `${afx.toFixed(0)}px`);
        aim.style.setProperty('--fy', `${afy.toFixed(0)}px`);

        const fw = aw * 0.95;
        const fh = (fw * 524) / 850;
        flashImg.style.width = `${fw.toFixed(1)}px`;
        flashImg.style.left = `${(mx - 0.474 * fw).toFixed(1)}px`;
        flashImg.style.top = `${(my - 0.922 * fh).toFixed(1)}px`;
        flashImg.style.transformOrigin = '47.4% 92.2%';
        flashTwin.style.width = `${fw.toFixed(1)}px`;
        flashTwin.style.left = `${(mx - 0.474 * fw).toFixed(1)}px`;
        flashTwin.style.top = `${(my - 0.922 * fh).toFixed(1)}px`;
        flashTwin.style.transformOrigin = '47.4% 92.2%';

        const cw = Math.max(W, 1.5 * H) * 1.3;
        crackImg.style.width = `${cw.toFixed(1)}px`;
        crackImg.style.left = `${(mx - 0.507 * cw).toFixed(1)}px`;
        crackImg.style.top = `${(my - (0.346 * cw) / 1.5).toFixed(1)}px`;

        // The sandwich sits on the left of his path; on a narrow screen it moves in so it stays in frame.
        if (W / H < 1) {
            sandWrap.style.left = '35.5%';
            sandWrap.style.width = '11.5%';
            sandWrap.style.top = '70%';
        } else {
            sandWrap.style.left = '';
            sandWrap.style.width = '';
            sandWrap.style.top = '';
        }
    };

    /* ---- the owner: one cutout, sliced into strips that sway out of phase. His own photo, never retouched. ---- */

    const PAD = 24;
    const GLINTS = [[316, 106], [365, 106]];
    const FACE_C = [342, 112];
    const fig = { ready: false, w: 0, h: 0, cw: 0, lit: null, dark: null, ctx: null, strips: lite ? 9 : 16, drawAt: 0 };

    const silhouette = (target, img) => {
        const c = target.getContext('2d');
        target.width = fig.cw;
        target.height = fig.h;
        c.drawImage(img, PAD, 0);
        c.globalCompositeOperation = 'source-in';
        c.fillStyle = '#000';
        c.fillRect(0, 0, target.width, target.height);
    };

    const buildFigure = () => {
        const a = asset('char');
        if (fig.ready || !a.ready || !a.img || !a.img.naturalWidth) return;
        const img = a.img;
        fig.w = img.naturalWidth;
        fig.h = img.naturalHeight;
        fig.cw = fig.w + PAD * 2;
        figCanvas.width = fig.cw;
        figCanvas.height = fig.h;
        fig.ctx = figCanvas.getContext('2d');
        fig.lit = document.createElement('canvas');
        fig.lit.width = fig.w;
        fig.lit.height = fig.h;
        fig.lit.getContext('2d').drawImage(img, 0, 0);
        // The crushed grade: the body drops to near black, only the red rim light keeps its strength.
        fig.dark = document.createElement('canvas');
        fig.dark.width = fig.w;
        fig.dark.height = fig.h;
        const dc = fig.dark.getContext('2d');
        const data = fig.lit.getContext('2d').getImageData(0, 0, fig.w, fig.h);
        const px = data.data;
        for (let i = 0; i < px.length; i += 4) {
            const rim = smooth((px[i] - 70) / 110);
            const gain = 0.085 + 0.9 * rim;
            px[i] *= gain;
            px[i + 1] *= gain;
            px[i + 2] *= gain;
        }
        dc.putImageData(data, 0, 0);
        silhouette(shadowEl, img);
        silhouette(reflectEl, img);
        fig.ready = true;
    };

    // Where a point of the cutout lands on screen, given the current walk position.
    const figGeom = (p, bob) => {
        const x0 = 0.49 * PW;
        const y0 = 0.708 * PH;
        const h0 = 0.3 * PH;
        const side = W >= 900 ? 0.09 * W : 0.05 * PW;
        const x1 = PW / 2 + side;
        const y1 = (PH - H) / 2 + 0.955 * H;
        const h1 = 0.9 * H;
        const hgt = lerp(h0, h1, p);
        const sc = hgt / (fig.h || 1180);
        return { x: lerp(x0, x1, p), y: lerp(y0, y1, p) - bob * (reduced ? 0 : 5) * sc, sc, p };
    };
    const toScreen = (g, cx, cy) => ({
        x: (W - PW) / 2 + g.x + (cx + PAD - fig.cw / 2) * g.sc,
        y: (H - PH) / 2 + g.y - (fig.h - cy) * g.sc,
    });

    // Cloth keeps moving a while after he stops, then settles on a damped sine. The head turns a little during the stop.
    const clothAt = (u) => {
        if (u < STEPS[0][0]) return 0;
        let lastEnd = -99;
        let active = false;
        STEPS.forEach(([start]) => {
            if (u >= start && u < start + STEP_DUR) active = true;
            if (u >= start + STEP_DUR) lastEnd = Math.max(lastEnd, start + STEP_DUR);
        });
        return active ? 1 : Math.exp(-(u - lastEnd) * 1.4);
    };
    const turnAt = (u) => (reduced ? 0 : smooth((u - 12.9) / 0.5) * (1 - 0.35 * smooth((u - 13.8) / 0.4)) * (1 - smooth((u - 14.2) / 0.5)));

    const drawFigure = (t, u, D, body, walk) => {
        if (!fig.ready) return;
        const c = fig.ctx;
        const n = fig.strips;
        const sh = fig.h / n;
        const amp = (0.5 + 2.6 * clothAt(u) + 1.4 * walk.bob) * (reduced ? 0.4 : 1);
        const turn = turnAt(u);
        c.clearRect(0, 0, fig.cw, fig.h);
        for (let i = 0; i < n; i += 1) {
            const y = i * sh;
            const f = y / fig.h;
            const hem = smooth((f - 0.38) / 0.55);
            let dx = amp * (0.25 + 2.2 * hem) * Math.sin(t * 1.7 + i * 0.62) + Math.sin(t * 0.9 + i * 0.3) * 0.4 * (1 - f);
            let sw = fig.w;
            let off = 0;
            if (f < 0.2 && turn > 0) {
                const w = 1 - f / 0.2;
                dx += turn * 3.5 * w;
                sw = fig.w * (1 - 0.045 * turn * w);
                off = (fig.w - sw) / 2;
            }
            c.globalAlpha = 1;
            c.drawImage(fig.dark, 0, y, fig.w, sh + 1, PAD + dx + off, y, sw, sh + 1);
            if (body > 0.02) {
                c.globalAlpha = body;
                c.drawImage(fig.lit, 0, y, fig.w, sh + 1, PAD + dx + off, y, sw, sh + 1);
            }
        }
        c.globalAlpha = 1;
        // Lens glints: two small points, faintly alive; they catch the light as the door opens.
        const ga = 0.1 + 0.32 * D;
        GLINTS.forEach(([gx, gy]) => {
            const g = c.createRadialGradient(PAD + gx, gy, 0, PAD + gx, gy, 5);
            g.addColorStop(0, `rgba(235,252,255,${ga})`);
            g.addColorStop(1, 'rgba(235,252,255,0)');
            c.fillStyle = g;
            c.fillRect(PAD + gx - 14, gy - 14, 28, 28);
        });
    };

    /* ---- the dialogue card: per-letter reveal, driven by the clock ---- */

    const PARTS = [
        { at: P1[0], to: P1[1], rate: 0.024, lines: [[['Mana Mungkin Seorang '], ['"Vassell Enha"', true]]] },
        { at: P2[0], to: P2[1], rate: 0.026, lines: [[['Memberikan Akses Panel Admin']], [['ke Amatiran Seperti Anda.']]] },
    ];
    let shownPart = -1;
    let chars = [];
    const buildPart = (index, instant) => {
        dlgText.textContent = '';
        chars = [];
        dlg.classList.toggle('instant', instant);
        if (index < 0) return;
        PARTS[index].lines.forEach((line, li) => {
            if (li > 0) dlgText.append(document.createElement('br'));
            line.forEach(([str, hl]) => {
                const holder = hl ? el('span', 'hl cr-w') : dlgText;
                str.split(/(\s+)/).forEach((word) => {
                    if (!word) return;
                    if (/^\s+$/.test(word)) {
                        holder.append(document.createTextNode(' '));
                        return;
                    }
                    const w = hl ? holder : el('span', 'cr-w');
                    Array.from(word).forEach((ch) => {
                        const s = el('span', 'cr-ch', ch);
                        w.append(s);
                        chars.push(s);
                    });
                    if (!hl) holder.append(w);
                });
                if (hl) dlgText.append(holder);
            });
        });
    };
    const dialogue = (u, late) => {
        let part = -1;
        PARTS.forEach((p, i) => { if (u >= p.at && u < p.to) part = i; });
        if (part !== shownPart) {
            shownPart = part;
            buildPart(part, late);
        }
        flag('on', part >= 0, dlg);
        if (part < 0) {
            flag('is-talking', false, dlg);
            return false;
        }
        const p = PARTS[part];
        const typed = Math.floor((u - p.at) / p.rate);
        chars.forEach((s, i) => s.classList.toggle('on', i < typed));
        flag('is-talking', typed < chars.length, dlg);
        return typed < chars.length;
    };

    /* ---- the ending: the shot, the cracked lens, the tube dying ---- */

    const rng = (seed) => () => { seed = (seed * 1664525 + 1013904223) % 4294967296; return seed / 4294967296; };

    const buildEnding = () => {
        const r = rng(7);
        for (let i = 0; i < 6; i += 1) {
            const node = el('i', 'cr-chip');
            const a = r() * Math.PI * 2;
            const sp = 90 + r() * 260;
            const size = 5 + r() * 8;
            node.style.width = `${size}px`;
            node.style.height = `${size}px`;
            screen.insertBefore(node, document.getElementById('hz-canvas'));
            chips.push({ node, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - 140, spin: (r() - 0.5) * 12, at: 26.95 + i * 0.035 });
        }
        for (let i = 0; i < 5; i += 1) {
            const node = el('i', 'cr-spark');
            screen.insertBefore(node, document.getElementById('hz-canvas'));
            sparks.push({ node, a: r() * Math.PI * 2, sp: 220 + r() * 260 });
        }
        lineEl = el('div', 'cr-crtline');
        dcEl = el('div', 'cr-dc cr-keep', 'DISCONNECTED');
        nosig = el('div', 'cr-nosig', 'CAM 3  NO SIGNAL');
        screen.append(nosig);
        hz.append(lineEl, dcEl);
    };

    // The muzzle flash: one event, 2 to 3 frames, low peak, local to the face and the haze. Reduced motion gets a soft ramp.
    const flashAt = (u) => {
        const x = u - T.shot;
        if (x < 0) return 0;
        if (reduced) return x < 0.2 ? 0.4 * smooth(x / 0.2) : 0.4 * Math.max(0, 1 - (x - 0.2) / 0.5);
        const v = x < 0.016 ? x / 0.016 : Math.exp(-(x - 0.016) / 0.05);
        return v < 0.02 ? 0 : v;
    };
    const tearOn = (u) => !reduced && ((u >= 27.2 && u < 27.3) || (u >= 27.5 && u < 27.62) || (u >= 27.95 && u < 28.05));
    const sigOn = (u) => (reduced ? u >= 27.4 && u < 28.1 : (u >= 27.35 && u < 27.5) || (u >= 27.75 && u < 27.95) || (u >= 28.05 && u < 28.1));
    let chipFlag = false;

    const shotAt = (u) => {
        const x = u - T.shot;
        // ---- the lens: glass cracks outward from the muzzle point, a few chips fall, sparks late
        const c = u - 26.7;
        if (c < 0) {
            if (cls.crackOn !== false) {
                cls.crackOn = false;
                crackWrap.style.opacity = '0';
            }
        } else {
            cls.crackOn = true;
            const alpha = x < 0.5 ? 0.3 : 0.3 + 0.62 * smooth((x - 0.5) / 0.9);
            crackWrap.style.opacity = alpha.toFixed(3);
            const cm = Math.round((outCubic(c / 1.5) * 118) / 2) * 2;
            if (cm !== cls.cm) {
                cls.cm = cm;
                crackImg.style.setProperty('--cm', `${cm}%`);
            }
        }
        chips.forEach((ch) => {
            const tt = u - ch.at;
            if (tt < 0 || tt > 1.1 || reduced) {
                if (ch.on) { ch.on = false; ch.node.style.opacity = '0'; }
                return;
            }
            ch.on = true;
            const px = geo.mx + ch.vx * tt;
            const py = geo.my + ch.vy * tt + 0.5 * 1700 * tt * tt;
            ch.node.style.transform = `translate3d(${px.toFixed(1)}px, ${py.toFixed(1)}px, 0) rotate(${(ch.spin * tt * 57).toFixed(0)}deg)`;
            ch.node.style.opacity = ((1 - tt / 1.1) * 0.9).toFixed(2);
        });
        sparks.forEach((sp) => {
            const tt = u - 27.15;
            if (tt < 0 || tt > 0.45 || reduced) {
                if (sp.on) { sp.on = false; sp.node.style.opacity = '0'; }
                return;
            }
            sp.on = true;
            const d = sp.sp * tt;
            const px = geo.mx + Math.cos(sp.a) * d;
            const py = geo.my + Math.sin(sp.a) * d + 160 * tt * tt;
            sp.node.style.transform = `translate3d(${px.toFixed(1)}px, ${py.toFixed(1)}px, 0) rotate(${((sp.a * 180) / Math.PI + 90).toFixed(0)}deg)`;
            sp.node.style.opacity = (1 - tt / 0.45).toFixed(2);
        });

        // ---- the signal: HUD dies first with a stutter, then NO SIGNAL flickers
        flag('hud-off', u >= 27.15 && (u >= 27.45 || (Math.floor((u - 27.15) / 0.06) * 7) % 5 < 3));
        flag('sig', sigOn(u), nosig);
        flag('is-tear', tearOn(u));

        // ---- picture slip: jitter, then one vertical roll, then a short freeze
        let sx = 0;
        let ry = 0;
        if (!reduced) {
            if (u >= 27.2 && u < 27.7) sx = (((Math.floor(u * 28) * 37) % 23) - 11) * 1.6;
            if (u >= 27.7 && u < 27.95) ry = H * 0.25 * Math.sin((Math.PI * (u - 27.7)) / 0.25);
        }
        const kick = !reduced && x > 0 && x < 0.3 ? 7 * Math.exp(-x / 0.05) : 0;
        const shift = `translate3d(${(sx + kick * 0.4).toFixed(1)}px, ${(ry + kick).toFixed(1)}px, 0)`;

        // ---- the CRT power-off: scaleY to a line, scaleX to a dot, a soft glow, then nothing
        if (u >= 28.1) {
            const s1 = inOut((u - 28.1) / 0.3);
            const s2 = inOut((u - 28.4) / 0.2);
            screen.style.transform = `${shift} scale(${(1 - 0.99 * s2).toFixed(4)}, ${(1 - 0.994 * s1).toFixed(4)})`;
            const lineOp = 0.8 * smooth((u - 28.25) / 0.12) * (1 - smooth((u - 28.55) / 0.3));
            lineEl.style.opacity = lineOp.toFixed(3);
            lineEl.style.transform = `translateY(-50%) scale(${(1 - 0.985 * s2).toFixed(4)}, ${(1 + 2.4 * s2).toFixed(3)})`;
        } else {
            screen.style.transform = shift;
            lineEl.style.opacity = '0';
        }
        dcEl.style.opacity = u >= 28.8 && u < 29.3 ? '0.5' : '0';
        flag('b-dead', u >= 28.8);
    };

    /* ---- the optional cue track: played once with the master clock, ducked under sound effects and dialogue ---- */

    const CUE = '/audio/honeypot-cue.mp3';
    let cue = null;
    let cueVol = 0;
    let cueTry = 0;
    const syncCue = (now, u, dt, talking) => {
        if (!cue) return;
        if (u < 0 || u > 29.7 || document.hidden) {
            if (!cue.paused) cue.pause();
            return;
        }
        let want = 0.5;
        QUIET.forEach(([a, b]) => { if (u >= a && u < b) want = 0; });
        if (now < duckUntil || talking) want *= 0.38;
        cueVol += (want - cueVol) * Math.min(1, dt * 7);
        cue.volume = clamp(cueVol);
        if (cue.paused) {
            if (now - cueTry > 900) {
                cueTry = now;
                try { cue.currentTime = u; } catch (error) { /* not seekable yet */ }
                const run = cue.play();
                if (run && run.catch) run.catch(() => {});
            }
        } else if (Math.abs(cue.currentTime - u) > 0.4) {
            try { cue.currentTime = u; } catch (error) { /* not seekable yet */ }
        }
    };

    /* ---- the master frame: one rAF, one delta clock ---- */

    let blurNow = -1;
    let waiting = true;
    let startedAt = 0;
    let grainNow = -1;
    const frame = () => {
        raf = window.requestAnimationFrame(frame);
        const now = performance.now();
        const t = (Date.now() - state.start) / 1000;
        const target = t / k;
        const dt = Math.min(0.1, (now - last) / 1000 || 0.016);
        last = now;

        if (t >= state.horrorMs / 1000) {
            startKewer();
            return;
        }

        if (W !== hz.clientWidth || H !== hz.clientHeight) layout();

        // The visual clock follows the server's clock. It waits for the plates the next moment needs, then catches up a little faster.
        if (Number.isNaN(vis)) {
            if (gate(target)) vis = target;
        } else {
            const gap = target - vis;
            if (gap > 4) {
                if (gate(target)) vis = target;
            } else if (gap > 0.0005 && gate(vis + dt)) {
                vis += Math.min(gap, dt * (gap > 0.03 ? 1.4 : 1));
            } else if (gap < 0) {
                vis = target;
            }
        }
        waiting = Number.isNaN(vis);
        const u = waiting ? 0 : vis;
        const ut = u * k;
        if (!fig.ready) buildFigure();

        // One-shot sounds: fire once as the clock passes them; anything we only joined late stays silent.
        if (!waiting) {
            EVENTS.forEach(([at, fn], i) => {
                if (fired.has(i) || u < at) return;
                fired.add(i);
                if ((u - at) * k < 0.8) {
                    fn();
                    duckUntil = now + 800;
                }
            });
        }

        // ---- light and world state
        const bootOp = smooth((u - 1.7) / 1.4);
        const cut = smooth((u - T.reveal0) / 0.2);
        const roomOn = u < T.reveal0 + 0.3;
        const D = smooth((u - 7.2) / 2.8);
        const door = smooth((u - T.door0) / (T.door1 - T.door0));
        const walk = walkAt(u);
        const p = walk.p;
        const cam1 = inOut((u - 7.5) / 8.7);
        const cam2 = inOut((u - 19.0) / (T.reveal0 - 19.0));
        const S = lerp(1 + 0.12 * cam1, 2.7, cam2);

        if (roomOn) {
            room.style.opacity = (bootOp * (1 - cut)).toFixed(3);
            room.style.transform = cut > 0 ? `translate3d(${(-cut * 0.06 * W).toFixed(1)}px, 0, 0)` : '';
            openImg.style.opacity = door.toFixed(3);
            spill.style.opacity = (D * (1 - 0.3 * p)).toFixed(3);
            bloom.style.opacity = (0.5 * D * (1 - 0.3 * cam1)).toFixed(3);
            fogs.style.opacity = (0.06 + 0.26 * D).toFixed(3);
            dust.style.opacity = D.toFixed(3);
            doorline.style.opacity = (0.5 * Math.max(pulse(u, 5.5, 0.12, 0.32), pulse(u, 6.0, 0.12, 0.3), pulse(u, 6.5, 0.12, 0.34)) * (1 - door)).toFixed(3);

            // ---- the owner
            const fOpacity = reduced ? smooth((u - 9) / 2.5) : smooth((u - T.appear) / 0.7);
            const g = figGeom(p, walk.bob);
            const cw = fig.cw || 724;
            const ch = fig.h || 1180;
            figEl.style.opacity = fOpacity.toFixed(3);
            figCanvas.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch).toFixed(1)}px, 0) scale(${g.sc.toFixed(4)})`;
            // The cast shadow and the reflection come from the same position value as the body.
            const len = lerp(2.4, 1.0, p);
            const skew = ((g.x - 0.49 * PW) / PH) * -26;
            shadowEl.style.opacity = (0.85 * D * fOpacity).toFixed(3);
            shadowEl.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch).toFixed(1)}px, 0) scale(${g.sc.toFixed(4)}, ${(-g.sc * len).toFixed(4)}) skewX(${skew.toFixed(2)}deg)`;
            reflectEl.style.opacity = (0.18 * D * fOpacity).toFixed(3);
            reflectEl.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch + walk.bob * 2).toFixed(1)}px, 0) scale(${(g.sc * 0.97).toFixed(4)}, ${(-g.sc * 0.82).toFixed(4)})`;
            // sandwich: cold rim first, then the red light reaches it
            sandWrap.firstElementChild.style.opacity = '1';
            if (sandLit) sandLit.style.opacity = (0.6 * D).toFixed(3);

            if (now - fig.drawAt > (lite ? 50 : 33)) {
                fig.drawAt = now;
                drawFigure(ut, u, D, 0.05 + 0.1 * cam2, walk);
            }

            // ---- camera: one move, handheld drift on top, parallax by layer
            const drift = reduced ? 0 : 1;
            const dx = (Math.sin(ut * 0.7) * 3 + Math.sin(ut * 1.9) * 1.1) * drift;
            const dy = (Math.sin(ut * 0.55 + 1) * 2.2 + Math.sin(ut * 2.3) * 0.8) * drift;
            const face = toScreen(g, FACE_C[0], FACE_C[1]);
            const ox = lerp(W / 2, face.x, cam2);
            const oy = lerp(H / 2, face.y, cam2);
            const panX = (geo.afx - face.x) * cam2;
            const panY = (geo.afy - face.y) * cam2;
            const setLayer = (node, factor, shift) => {
                node.style.transformOrigin = `${ox.toFixed(1)}px ${oy.toFixed(1)}px`;
                node.style.transform = `translate3d(${(dx * shift + panX * Math.min(1, shift)).toFixed(2)}px, ${(dy * shift + panY * Math.min(1, shift)).toFixed(2)}px, 0) scale(${(S ** factor).toFixed(4)})`;
            };
            setLayer(layers.far, 0.6, 0.6);
            setLayer(layers.mid, 1, 1);
            setLayer(layers.near, 1.8, 1.8);

            // Depth of field: the background softens while the camera pushes in; quantized so it stays cheap.
            if (!lite && !reduced) {
                const blur = Math.round(3 * smooth((u - 16.5) / 7) * 2) / 2;
                if (blur !== blurNow) {
                    blurNow = blur;
                    layers.far.style.filter = blur ? `blur(${blur}px)` : '';
                    sandWrap.style.filter = blur ? `blur(${(blur * 0.8).toFixed(1)}px)` : '';
                }
            }
        }

        // ---- the aim plate: match cut, key light on the face, the shot
        if (u >= T.reveal0 - 0.05) {
            const F = flashAt(u);
            const x = u - T.shot;
            const recoil = !reduced && x > 0 && x < 0.6 ? Math.exp(-x / 0.07) : 0;
            aim.style.opacity = cut.toFixed(3);
            aim.style.transform = `translate3d(${((1 - cut) * 0.05 * W).toFixed(1)}px, ${(recoil * 5).toFixed(1)}px, 0) scale(${(1 + 0.05 * recoil).toFixed(4)}) rotate(${(-1.2 * recoil).toFixed(2)}deg)`;
            const ks = smooth((u - T.key0) / (T.key1 - T.key0));
            const s = lerp(0.22, 1.9, ks) + 0.7 * smooth((u - T.key1) / 1.2);
            aimKey.style.transform = `translate3d(${(geo.afx - W / 2).toFixed(1)}px, ${(geo.afy - H / 2).toFixed(1)}px, 0) scale(${s.toFixed(3)})`;
            aimWarm.style.opacity = (F * 0.55).toFixed(3);
            flashImg.style.opacity = (F * 0.85).toFixed(3);
            // The gun points at the lens, so the burst is a full ring: the plate's fan, and its mirror image, on the same bore.
            const fs = (0.7 + 0.5 * F).toFixed(3);
            flashImg.style.transform = F > 0 ? `scale(${fs})` : '';
            flashTwin.style.opacity = (F * 0.85).toFixed(3);
            flashTwin.style.transform = F > 0 ? `scale(${fs}, -${fs})` : '';
        }

        // ---- grain rises with the push-in
        const grainOp = u < 2 ? 0.9 - 0.2 * smooth(u / 2) : 0.5 + 0.32 * cam2;
        const gq = Math.round(grainOp * 50) / 50;
        if (gq !== grainNow) {
            grainNow = gq;
            grain.style.opacity = String(gq);
        }

        // ---- HUD
        flag('b-on', u >= 0.4);
        flag('b-alert', u >= T.alert);
        flag('a1', u >= T.alert);
        flag('a2', u >= 12);
        flag('a3', u >= P2[0]);
        threat.style.transform = `scaleX(${threatAt(u).toFixed(3)})`;
        const base = u < T.knock ? 0 : 0.1;
        const want = clamp(base + 0.6 * pulse(u, T.knock, 0.1, 1.1) + 0.55 * walk.bob + 0.45 * smooth((u - P2[0]) / 2) * (u < T.reveal0 ? 1 : 0.5) + (u >= T.shot ? 1 : 0) * pulse(u, T.shot, 0.05, 0.8));
        noiseV = want > noiseV ? want : Math.max(0, noiseV - dt * 1.4);
        const lit = Math.round(noiseV * noiseBars.length);
        noiseBars.forEach((b, i) => b.classList.toggle('on', i < lit));
        mapDot.style.left = `${(50 + (p - 0.5) * 6).toFixed(1)}%`;
        mapDot.style.top = `${(18 + 56 * p).toFixed(1)}%`;

        const sec = Math.floor(Date.now() / 1000);
        if (sec !== stampSec) {
            stampSec = sec;
            const d = new Date();
            const pad = (v) => String(v).padStart(2, '0');
            clock.textContent = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}  ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        }

        const talking = dialogue(u, !waiting && now - startedAt < 600 && u > 12);
        shotAt(u);
        flag('b-black', u >= T.black);
        syncCue(now, waiting ? -1 : u, dt, talking);
    };

    let sandLit = null;

    return {
        start(elapsedMs = 0) {
            if (started) return;
            started = true;
            hz.hidden = false;
            hz.classList.toggle('is-lite', lite);
            hz.classList.toggle('is-calm', reduced);
            k = state.horrorMs / 30000;
            startedAt = performance.now();
            last = startedAt;

            // The brand fonts must really be applied before the card is read; otherwise the fallback stack carries it.
            hz.dataset.fonts = 'pending';
            if (document.fonts && document.fonts.load) {
                Promise.all([document.fonts.load('800 24px "Bagel Fat One"'), document.fonts.load('700 12px "Space Mono"')])
                    .then(() => document.fonts.ready)
                    .then(() => {
                        const loaded = (name) => Array.from(document.fonts).some((face) => face.family.replace(/"/g, '') === name && face.status === 'loaded');
                        hz.dataset.fonts = loaded('Bagel Fat One') && loaded('Space Mono') ? 'brand' : 'fallback';
                    })
                    .catch(() => { hz.dataset.fonts = 'fallback'; });
            } else {
                hz.dataset.fonts = 'fallback';
            }

            layout();
            window.addEventListener('resize', layout);
            // A lit copy of the sandwich fades in over the dim one when the red light arrives.
            sandLit = sand.cloneNode(false);
            sandLit.removeAttribute('id');
            sandLit.className = 'cr-sand cr-sand-lit';
            sandWrap.append(sandLit);
            buildEnding();
            grain.style.transition = 'none';
            stopGrain = startCanvas(grain, { bars: false });
            preload(elapsedMs / 1000 / k);

            cue = new Audio(CUE);
            cue.preload = 'auto';
            cue.volume = 0;

            // Sound needs a gesture in most browsers: try now, and again on the first press, key or touch.
            audio();
            wake();
            ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, () => { wake(); cueTry = 0; }, { once: true, capture: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (ctx && ctx.state === 'running') ctx.suspend().catch(() => {});
                    if (cue) cue.pause();
                } else {
                    wake();
                    cueTry = 0;
                }
            });

            raf = window.requestAnimationFrame(frame);
        },
        stop() {
            window.cancelAnimationFrame(raf);
        },
        end() {
            window.cancelAnimationFrame(raf);
            stopGrain();
            hz.hidden = true;
            if (cue) cue.pause();
            if (ctx && ctx.state !== 'closed') ctx.close().catch(() => {});
        },
    };
})();

/* ------------------------------------------------------------ phase B: Kewer */

let kewerStarted = false;
function startKewer() {
    if (kewerStarted) return;
    kewerStarted = true;
    state.phase = 'kewer';
    document.getElementById('hp-kewer').hidden = false;
    horror.end();

    lockSound();
    buildWindows();
    buildStamps();
    buildPops();
    state.stopKewerCanvas = startCanvas(canvas);
    feedWindows();
    later(stampLoop, 900);
    later(glitchLoop, 2600);
    caption();
    forceFullscreen();
}

function boot() {
    if (!root) return;

    const elapsed = Number(root.dataset.elapsed || 0);
    const horrorSeconds = Number(root.dataset.horror || 30);
    const kewerSeconds = Number(root.dataset.kewer || 30);

    // The server's elapsed seconds are the clock: a reload lands at the same point and the intro never replays.
    state.start = Date.now() - elapsed * 1000;
    state.horrorMs = horrorSeconds * 1000;
    state.until = state.start + (horrorSeconds + kewerSeconds) * 1000;

    root.classList.toggle('is-lite', lite);
    root.classList.toggle('is-calm', reduced);

    store();
    arm();

    if (elapsed < horrorSeconds) horror.start(elapsed * 1000);
    else startKewer();

    tick();
    window.setInterval(tick, 250);
    forceFullscreen();
}

boot();
