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

const state = { start: 0, until: 0, horrorMs: 20000, ip: root?.dataset.ip || '', phase: 'horror', tries: [], bsod: false, popAt: 0, raf: 0, ended: false };

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

// A night camera feed of a basement cold room: something is wrong, and the owner walks out of the dark.
// Sound is one-shot effects only: real silence between them, no music, no loops. Every visual is a class on #hz
// (b-*) or a pure function of the server's elapsed time, so joining late just snaps to the right frame.
const horror = (() => {
    const $ = (id) => document.getElementById(id);
    const hz = $('hz');
    const room = $('cr-room');
    const clock = $('cr-time');
    const grain = $('hz-canvas');
    const layers = { far: $('cr-far'), mid: $('cr-mid'), near: $('cr-near') };
    const leaf = $('cr-leaf');
    const doorLight = $('cr-doorlight');
    const scene = $('cr-scene');
    const spill = $('cr-spill');
    const stripes = $('cr-stripes');
    const bloom = $('cr-bloom');
    const fogs = $('cr-fogs');
    const dust = $('cr-dust');
    const sandWrap = $('cr-sand-wrap');
    const sandLit = $('cr-sand-lit');
    const figEl = $('cr-fig');
    const figCanvas = $('cr-fig-canvas');
    const shadowEl = $('cr-shadow');
    const reflectEl = $('cr-reflect');
    const threat = $('cr-threat');
    const noiseBars = Array.from(document.querySelectorAll('#cr-noise b'));
    const mapDot = $('cr-map-dot');
    const dlg = $('cr-dlg');
    const dlgText = $('cr-dlg-text');
    const rip = hz.querySelector('.cr-rip');
    const drop = hz.querySelector('.cr-drop');

    let ctx = null;
    let stopGrain = () => {};
    let raf = 0;
    let started = false;
    let k = 1;
    let last = 0;
    let lastU = 0;
    let noiseV = 0;
    let stampSec = -1;
    let W = 0;
    let H = 0;
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
        sting: () => hit((c, t) => {
            [55, 58.3, 82].forEach((freq) => {
                const o = c.createOscillator();
                o.type = 'sawtooth';
                o.frequency.value = freq;
                const f = lowpass(c, 900);
                f.frequency.setValueAtTime(900, t);
                f.frequency.exponentialRampToValueAtTime(120, t + 1.1);
                o.connect(f).connect(env(c, t, 0.11, 0.015, 1.1));
                o.start(t);
                o.stop(t + 1.3);
            });
        }),
        // The one heavy low thud under the face reveal.
        boom: () => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(70, t);
            o.frequency.exponentialRampToValueAtTime(24, t + 0.7);
            o.connect(env(c, t, 0.85, 0.01, 1.0));
            o.start(t);
            o.stop(t + 1.1);
            const n = noise(c, 0.25);
            n.connect(lowpass(c, 260)).connect(env(c, t, 0.4, 0.004, 0.22));
            n.start(t);
        }),
        slam: () => hit((c, t) => {
            const o = c.createOscillator();
            o.type = 'sine';
            o.frequency.setValueAtTime(120, t);
            o.frequency.exponentialRampToValueAtTime(28, t + 0.4);
            o.connect(env(c, t, 0.7, 0.003, 0.6));
            o.start(t);
            o.stop(t + 0.7);
            const n = noise(c, 0.3);
            n.connect(lowpass(c, 900)).connect(env(c, t, 0.45, 0.002, 0.25));
            n.start(t);
        }),
    };

    /* ---- small maths: everything is a pure function of the clock ---- */

    const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
    const smooth = (x) => { const v = clamp(x); return v * v * (3 - 2 * v); };
    const inOut = (x) => { const v = clamp(x); return v < 0.5 ? 4 * v * v * v : 1 - ((-2 * v + 2) ** 3) / 2; };
    const lerp = (a, b, x) => a + (b - a) * x;
    const flag = (name, on, target = hz) => {
        if (cls[name] === on) return;
        cls[name] = on;
        target.classList.toggle(name, on);
    };

    /* ---- beat sheet, in seconds of a 20 second shot (scaled by k when HORROR_SECONDS differs) ---- */

    const DOOR0 = 5.0;
    const DOOR1 = 7.2;
    const STEPS = reduced ? [[7.6, 0.5], [8.7, 0.5]] : [[7.6, 0.2], [8.5, 0.27], [9.4, 0.3], [10.2, 0.23]];
    const STEP_DUR = reduced ? 1.3 : 0.8;
    const PUSH0 = 7.5;
    const PUSH1 = 18.0;
    const P1 = [10.9, 13.7];
    const P2 = [14.2, 18.6];
    const REVEAL0 = 17.0;
    const REVEAL1 = 18.0;

    const EVENTS = [
        [0.9, () => SFX.buzz()],
        [3.6, () => { SFX.drip(); retrigger(rip); retrigger(drop); }],
        [5.0, () => SFX.creak()],
        [5.7, () => SFX.crunch()],
        [7.95, () => SFX.foot(0.3)],
        [8.85, () => SFX.foot(0.48)],
        [8.6, () => SFX.rustle()],
        [9.75, () => SFX.foot(0.7)],
        [10.1, () => SFX.rustle()],
        [10.55, () => SFX.foot(0.95)],
        [13.6, () => { SFX.drip(); retrigger(rip); retrigger(drop); }],
        [15.0, () => SFX.sting()],
        [18.0, () => SFX.boom()],
        [19.3, () => SFX.slam()],
    ];

    function retrigger(node) {
        if (!node || reduced) return;
        node.classList.remove('go');
        void node.getBoundingClientRect();
        node.classList.add('go');
    }

    const threatAt = (u) => {
        if (u < 6.2) return 0;
        if (u < 11) return lerp(0, 0.5, smooth((u - 6.2) / 4.8));
        if (u < 15) return lerp(0.5, 0.75, smooth((u - 11) / 4));
        return lerp(0.75, 1, smooth((u - 15) / 2.6));
    };

    // Walk progress: the sum of weighted steps, each its own eased move, with silence between them.
    const walkAt = (u) => {
        let p = 0;
        let bob = 0;
        STEPS.forEach(([start, weight]) => {
            const x = (u - start) / STEP_DUR;
            p += weight * smooth(x);
            if (x > 0 && x < 1) bob = Math.max(bob, Math.sin(Math.PI * x));
        });
        return { p: clamp(p), bob };
    };
    const lastStep = () => STEPS[STEPS.length - 1][0] + STEP_DUR;

    /* ---- the owner: one photo, sliced into strips that sway out of phase ---- */

    const PAD = 24;
    const GLINTS = [[232, 103], [280, 104]];
    const FACE = [254, 118];
    const fig = { img: null, ready: false, ctx: null, w: 0, h: 0, face: null, faceCtx: null, rim: null, strips: lite ? 9 : 16, drawAt: 0, silhouette: null };

    const silhouette = (target) => {
        const c = target.getContext('2d');
        target.width = fig.w + PAD * 2;
        target.height = fig.h;
        c.drawImage(fig.img, PAD, 0);
        c.globalCompositeOperation = 'source-in';
        c.fillStyle = '#000';
        c.fillRect(0, 0, target.width, target.height);
    };

    const loadFigure = () => {
        const img = new Image();
        img.onload = () => {
            fig.img = img;
            fig.w = img.naturalWidth;
            fig.h = img.naturalHeight;
            figCanvas.width = fig.w + PAD * 2;
            figCanvas.height = fig.h;
            fig.ctx = figCanvas.getContext('2d');
            silhouette(shadowEl);
            silhouette(reflectEl);
            fig.face = document.createElement('canvas');
            fig.face.width = 190;
            fig.face.height = 190;
            fig.faceCtx = fig.face.getContext('2d');
            const c = fig.ctx;
            fig.rim = c.createLinearGradient(PAD, 0, PAD + fig.w, 0);
            fig.rim.addColorStop(0, 'rgba(255,110,145,0.8)');
            fig.rim.addColorStop(0.12, 'rgba(255,110,145,0)');
            fig.rim.addColorStop(0.88, 'rgba(255,110,145,0)');
            fig.rim.addColorStop(1, 'rgba(255,110,145,0.8)');
            fig.ready = true;
        };
        img.src = hz.dataset.figure;
    };

    // Cloth keeps moving a while after he stops, then settles on a damped sine.
    const drawFigure = (t, u, D, R, walk) => {
        if (!fig.ready) return;
        const c = fig.ctx;
        const n = fig.strips;
        const sh = fig.h / n;
        const settle = u > lastStep() ? Math.exp(-(u - lastStep()) * 1.4) : 1;
        const moving = u < lastStep() ? clamp((u - 7.3) / 0.4) : settle;
        const amp = (0.5 + 2.6 * moving + 1.4 * walk.bob) * (reduced ? 0.4 : 1);
        c.clearRect(0, 0, figCanvas.width, figCanvas.height);
        for (let i = 0; i < n; i += 1) {
            const y = i * sh;
            const f = y / fig.h;
            const hem = smooth((f - 0.38) / 0.55);
            const dx = amp * (0.25 + 2.2 * hem) * Math.sin(t * 1.7 + i * 0.62) + Math.sin(t * 0.9 + i * 0.3) * 0.4 * (1 - f);
            c.drawImage(fig.img, 0, y, fig.w, sh + 1, PAD + dx, y, fig.w, sh + 1);
        }
        c.globalCompositeOperation = 'source-atop';
        // Dark by default: the body is a silhouette that only the door light can touch.
        const L = 0.05 + 0.1 * R;
        c.fillStyle = `rgba(2,2,3,${1 - L})`;
        c.fillRect(0, 0, figCanvas.width, figCanvas.height);
        if (D > 0.01) {
            c.globalAlpha = D;
            c.fillStyle = fig.rim;
            c.fillRect(0, 0, figCanvas.width, figCanvas.height);
            c.globalAlpha = 1;
        }
        c.globalCompositeOperation = 'source-over';
        if (R > 0.003) {
            // The reveal: a lit patch from below and the side, nothing else on him gets brighter.
            const f = fig.faceCtx;
            f.globalCompositeOperation = 'source-over';
            f.clearRect(0, 0, 190, 190);
            f.drawImage(fig.img, 150, 20, 190, 190, 0, 0, 190, 190);
            f.globalCompositeOperation = 'destination-in';
            const g = f.createRadialGradient(80, 112, 4, 84, 100, 86);
            g.addColorStop(0, 'rgba(0,0,0,1)');
            g.addColorStop(0.55, 'rgba(0,0,0,0.9)');
            g.addColorStop(1, 'rgba(0,0,0,0)');
            f.fillStyle = g;
            f.fillRect(0, 0, 190, 190);
            f.globalCompositeOperation = 'source-atop';
            f.fillStyle = 'rgba(255,128,152,0.16)';
            f.fillRect(0, 0, 190, 190);
            c.save();
            c.globalAlpha = R * 0.92;
            c.drawImage(fig.face, PAD + 150, 20);
            c.globalCompositeOperation = 'lighter';
            c.globalAlpha = R * 0.3;
            c.drawImage(fig.face, PAD + 150, 20);
            c.restore();
        }
        // Lens glints: two small points, always faintly alive, strong when the face is revealed.
        const ga = (0.18 + 0.2 * D) * (1 - R) + 0.95 * R;
        GLINTS.forEach(([gx, gy]) => {
            const g = c.createRadialGradient(PAD + gx, gy, 0, PAD + gx, gy, 6 + 5 * R);
            g.addColorStop(0, `rgba(235,252,255,${ga})`);
            g.addColorStop(1, 'rgba(235,252,255,0)');
            c.fillStyle = g;
            c.fillRect(PAD + gx - 14, gy - 14, 28, 28);
        });
    };

    /* ---- the dialogue card: per-letter reveal, driven by the clock ---- */

    const PARTS = [
        { at: P1[0], to: P1[1], rate: 0.022, lines: [[['Mana Mungkin Seorang '], ['"Vassell Enha"', true]]] },
        { at: P2[0], to: P2[1], rate: 0.016, lines: [[['Memberikan Akses Panel Admin']], [['ke Amatiran Seperti Anda.']]] },
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
            return;
        }
        const p = PARTS[part];
        const typed = Math.floor((u - p.at) / p.rate);
        chars.forEach((s, i) => s.classList.toggle('on', i < typed));
        flag('is-talking', typed < chars.length, dlg);
    };

    /* ---- the master frame ---- */

    let blurNow = -1;
    const frame = () => {
        raf = window.requestAnimationFrame(frame);
        const now = performance.now();
        const t = (Date.now() - state.start) / 1000;
        const u = t / k;
        const dt = Math.min(0.1, (now - last) / 1000 || 0.016);
        last = now;

        if (t >= state.horrorMs / 1000) {
            startKewer();
            return;
        }

        if (W !== hz.clientWidth || H !== hz.clientHeight) {
            W = hz.clientWidth;
            H = hz.clientHeight;
        }

        // SFX events: fire once as the clock passes them; anything we only joined late stays silent.
        EVENTS.forEach(([at, fn], i) => {
            if (fired.has(i) || u < at) return;
            fired.add(i);
            if ((u - at) * k < 0.8) fn();
        });
        lastU = u;

        // ---- light and world state
        const boot = smooth((u - 1.2) / 1.0);
        const A = inOut((u - DOOR0) / (DOOR1 - DOOR0));
        const D = smooth((u - 5.2) / 1.8);
        const walk = walkAt(u);
        const p = walk.p;
        const R = smooth((u - REVEAL0) / (REVEAL1 - REVEAL0));
        const cam = inOut((u - PUSH0) / (PUSH1 - PUSH0));
        const S = 1 + 0.2 * cam;

        room.style.opacity = boot.toFixed(3);
        scene.style.opacity = '1';
        leaf.style.transform = `scale(${(1 - 0.9 * A).toFixed(3)}, 1)`;
        doorLight.style.opacity = smooth((u - 5.1) / 1.4).toFixed(3);
        spill.style.opacity = D.toFixed(3);
        stripes.style.opacity = (D * 0.9).toFixed(3);
        bloom.style.opacity = (0.55 * D * (1 - 0.25 * cam)).toFixed(3);
        fogs.style.opacity = (0.05 + 0.24 * D).toFixed(3);
        dust.style.opacity = D.toFixed(3);
        sandLit.style.opacity = (0.55 * D).toFixed(3);

        // ---- the owner
        const fOpacity = smooth((u - 5.9) / 0.5);
        const scale = lerp(0.37, 1, p);
        const feetY = lerp(0.62, 0.955, p);
        const side = W >= 900 ? lerp(0, 0.09, p) * W : 0;
        const bobPx = walk.bob * (reduced ? 1 : 5) * scale;
        const ty = (feetY - 1) * H - bobPx;
        figEl.style.opacity = fOpacity.toFixed(3);
        figEl.style.transform = `translate3d(${side.toFixed(1)}px, ${ty.toFixed(1)}px, 0) scale(${scale.toFixed(4)})`;

        // The cast shadow and the reflection come from the same position value as the body.
        const len = lerp(2.4, 1.0, p);
        const lateral = (lerp(0.5, 0, p) - 0.5) * 0 + (side / Math.max(1, W)) * -2;
        shadowEl.style.opacity = (0.85 * D * fOpacity).toFixed(3);
        shadowEl.style.transform = `translate3d(${side.toFixed(1)}px, ${((feetY - 1) * H).toFixed(1)}px, 0) scale(${scale.toFixed(4)}, ${(-scale * len).toFixed(4)}) skewX(${(lateral * 32).toFixed(2)}deg)`;
        reflectEl.style.opacity = (0.28 * D * fOpacity).toFixed(3);
        reflectEl.style.transform = `translate3d(${side.toFixed(1)}px, ${((feetY - 1) * H + bobPx * 0.4).toFixed(1)}px, 0) scale(${(scale * 0.96).toFixed(4)}, ${(-scale * 0.85).toFixed(4)})`;

        if (now - fig.drawAt > 33) {
            fig.drawAt = now;
            drawFigure(t, u, D, R, walk);
        }

        // ---- camera: one shot, handheld drift on top, parallax by layer
        const drift = reduced ? 0 : 1;
        const dx = (Math.sin(t * 0.7) * 3 + Math.sin(t * 1.9) * 1.1) * drift;
        const dy = (Math.sin(t * 0.55 + 1) * 2.2 + Math.sin(t * 2.3) * 0.8) * drift;
        const faceX = W * 0.5 + side;
        const faceY = H * 0.2;
        const ox = lerp(W * 0.5, faceX, cam);
        const oy = lerp(H * 0.5, faceY, cam);
        const setLayer = (node, factor, shift) => {
            node.style.transformOrigin = `${ox.toFixed(1)}px ${oy.toFixed(1)}px`;
            node.style.transform = `translate3d(${(dx * shift).toFixed(2)}px, ${(dy * shift).toFixed(2)}px, 0) scale(${(S ** factor).toFixed(4)})`;
        };
        setLayer(layers.far, 0.6, 0.6);
        setLayer(layers.mid, 1, 1);
        setLayer(layers.near, 1.8, 1.8);

        // Depth of field: soft background while the push-in runs, sharp subject. Quantized so it is cheap.
        if (!lite && !reduced) {
            const blur = Math.round(3 * smooth((u - 11) / 6.5) * 2) / 2;
            if (blur !== blurNow) {
                blurNow = blur;
                layers.far.style.filter = blur ? `blur(${blur}px)` : '';
                sandWrap.style.filter = blur ? `blur(${blur * 0.8}px)` : '';
            }
        }

        // ---- HUD
        flag('b-on', u >= 0.6);
        flag('b-alert', u >= 6.2);
        flag('a1', u >= 6.2);
        flag('a2', u >= 9);
        flag('a3', u >= 17.6);
        flag('b-wrong', u >= 5.6);
        flag('b-bite', u >= 5.6);
        threat.style.transform = `scaleX(${threatAt(u).toFixed(3)})`;
        const want = u < 6.2 ? 0 : clamp(0.15 + 0.6 * walk.bob + 0.5 * smooth((u - 17) / 1) + (u > lastStep() ? 0 : 0.2), 0, 1);
        noiseV = want > noiseV ? want : Math.max(0, noiseV - dt * 1.4);
        const lit = Math.round(noiseV * noiseBars.length);
        
        noiseBars.forEach((b, i) => b.classList.toggle('on', i < lit));
        mapDot.style.left = `${(50 + (p - 0.5) * 6).toFixed(1)}%`;
        mapDot.style.top = `${(18 + 56 * p).toFixed(1)}%`;

        const sec = Math.floor(t);
        if (sec !== stampSec) {
            stampSec = sec;
            const d = new Date(Date.now());
            const pad = (v) => String(v).padStart(2, '0');
            clock.textContent = `CAM 03  ${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}  ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        }

        dialogue(u, (now - startedAt) < 400 && u > 11);

        // ---- signal tears: short, and only twice (replaced by nothing when reduced motion is on)
        const tearing = !reduced && ((u >= 5.6 && u < 5.75) || (u >= 18.95 && u < 19.3));
        flag('is-tear', tearing);
        flag('b-black', u >= 19.5);
    };

    let startedAt = 0;

    return {
        start() {
            if (started) return;
            started = true;
            hz.hidden = false;
            hz.classList.toggle('is-lite', lite);
            hz.classList.toggle('is-calm', reduced);
            k = state.horrorMs / 20000;
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

            stopGrain = startCanvas(grain, { bars: false });
            loadFigure();

            // Sound needs a gesture in most browsers: try now, and again on the first press, key or touch.
            audio();
            wake();
            ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, wake, { once: true, capture: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && ctx && ctx.state === 'running') ctx.suspend().catch(() => {});
                else wake();
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
    const horrorSeconds = Number(root.dataset.horror || 20);
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
