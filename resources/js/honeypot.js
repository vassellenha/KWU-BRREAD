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
    const aimEyes = $('cr-aim-eyes');
    const gun = $('cr-aim-gun');
    const cover = $('cr-aim-cover');
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

    /* ---- sound: one chain for everything.
       voice -> low-pass (distance) -> stereo pan -> duck -> master -> compressor -> output.
       A procedural cold-room reverb takes a 15 to 35 percent send from each voice. Real recordings in
       public/audio/honeypot/ replace a synthesized voice when they exist (the server lists them in data-samples);
       a missing or broken file simply keeps the layered synthesis. Nothing here may ever touch the visuals. ---- */

    const SAMPLES = (() => {
        try {
            return JSON.parse(root.dataset.samples || '{}') || {};
        } catch (error) {
            return {};
        }
    })();
    const FILE = { buzz: 'fluorescent-buzz', drip: 'drip', boom: 'heavy-thud', creak: 'door-creak', foot: 'footstep-wet', rustle: 'cape-rustle', sting: 'stinger', shot: 'gunshot', glass: 'glass-crack', zap: 'zap', crtoff: 'crt-off', thud: 'heavy-thud' };
    const buffers = {};
    let bus = null;

    const rnd = (a, b) => a + Math.random() * (b - a);
    const pitch = () => 1 + rnd(-0.04, 0.04); // random pitch, plus or minus 4 percent, on every play
    const dB = (v) => 10 ** (v / 20);

    // A cold concrete room: noise that decays over `seconds`, bright at first, then rising a little and falling dark.
    const makeIR = (c, seconds) => {
        const rate = c.sampleRate;
        const len = Math.floor(rate * seconds);
        const buffer = c.createBuffer(2, len, rate);
        for (let channel = 0; channel < 2; channel += 1) {
            const data = buffer.getChannelData(channel);
            let y = 0;
            for (let i = 0; i < len; i += 1) {
                const x = i / rate;
                const cutoff = x < 0.05 ? 1800 + 3400 * (x / 0.05) : 380 + 4820 * Math.exp(-(x - 0.05) * 1.9);
                const a = 1 - Math.exp((-2 * Math.PI * cutoff) / rate);
                y += a * (Math.random() * 2 - 1 - y);
                data[i] = y * Math.exp((-x * 6.9) / seconds) * (x < 0.012 ? x / 0.012 : 1);
            }
        }
        return buffer;
    };

    const buildBus = (c) => {
        const master = c.createGain();
        master.gain.value = 0.9;
        const comp = c.createDynamicsCompressor();
        comp.threshold.value = -18;
        comp.ratio.value = 4;
        comp.attack.value = 0.005;
        comp.release.value = 0.18;
        master.connect(comp);
        comp.connect(c.destination);
        const duck = c.createGain(); // every voice except the shot and the glass runs through this
        duck.connect(master);
        const wetDuck = c.createGain();
        wetDuck.connect(master);
        const verb = c.createConvolver();
        verb.buffer = makeIR(c, 1.8);
        verb.connect(wetDuck);
        const verbGun = c.createConvolver(); // the shot gets the longer tail and is never ducked
        verbGun.buffer = makeIR(c, 2.8);
        verbGun.connect(master);
        bus = { master, duck, wetDuck, verb, verbGun };
    };

    const loadSamples = (c) => {
        Object.entries(SAMPLES).forEach(([name, url]) => {
            if (typeof url !== 'string' || !url.startsWith('/audio/honeypot/')) return;
            fetch(url, { credentials: 'same-origin' })
                .then((res) => (res.ok ? res.arrayBuffer() : Promise.reject(new Error('missing'))))
                .then((data) => new Promise((ok, fail) => {
                    const run = c.decodeAudioData(data, ok, fail);
                    if (run && run.catch) run.catch(fail);
                }))
                .then((buffer) => { buffers[name] = buffer; })
                .catch(() => { /* no file, keep the synthesis */ });
        });
    };

    const audio = () => {
        if (ctx || !(window.AudioContext || window.webkitAudioContext)) return ctx;
        try {
            ctx = new (window.AudioContext || window.webkitAudioContext)();
            buildBus(ctx);
            loadSamples(ctx);
            loadCue(ctx);
        } catch (error) {
            bus = null;
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
    const env = (c, out, at, peak, attack, release) => {
        const gain = c.createGain();
        gain.gain.setValueAtTime(0.0001, at);
        gain.gain.exponentialRampToValueAtTime(peak, at + attack);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + attack + release);
        gain.connect(out);
        return gain;
    };
    // One oscillator layer: a glide from f0 to f1 over `sweep`, wrapped in its own envelope.
    const osc = (c, out, type, f0, f1, at, sweep, peak, attack, release) => {
        const o = c.createOscillator();
        o.type = type;
        o.frequency.setValueAtTime(f0, at);
        if (f1 !== f0) o.frequency.exponentialRampToValueAtTime(f1, at + sweep);
        o.connect(env(c, out, at, peak, attack, release));
        o.start(at);
        o.stop(at + Math.max(sweep, attack + release) + 0.05);
    };
    // One noise layer through a filter.
    const hiss = (c, out, at, len, type, f, q, peak, attack, release) => {
        const n = noise(c, len);
        const flt = c.createBiquadFilter();
        flt.type = type;
        flt.frequency.value = f;
        flt.Q.value = q;
        n.connect(flt).connect(env(c, out, at, peak, attack, release));
        n.start(at);
    };

    // The input of one sound: gain (plus or minus 1.5 dB), distance low-pass, stereo pan, then the bus and the reverb send.
    const voice = (c, o) => {
        const input = c.createGain();
        input.gain.value = (o.gain ?? 1) * dB(rnd(-1.5, 1.5));
        const lp = c.createBiquadFilter();
        lp.type = 'lowpass';
        lp.frequency.value = 600 * 24 ** (1 - clamp(o.dist ?? 0.3)); // far = muffled (600 Hz), near = open (14 kHz)
        lp.Q.value = 0.5;
        input.connect(lp);
        let tail = lp;
        if (c.createStereoPanner) {
            const pan = c.createStereoPanner();
            pan.pan.value = clamp(o.pan ?? 0, -1, 1);
            lp.connect(pan);
            tail = pan;
        }
        tail.connect(o.bypass ? bus.master : bus.duck);
        const send = c.createGain();
        send.gain.value = o.send ?? 0.25;
        tail.connect(send);
        send.connect(o.bypass ? bus.verbGun : bus.verb);
        return input;
    };

    // Each sound is at least two layers. `r` is this play's pitch factor.
    const SFX = {
        buzz: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            const bed = env(c, out, t, 0.06, 0.04, 0.8);
            [100, 200, 300].forEach((f, i) => {
                const node = c.createOscillator();
                node.type = 'sawtooth';
                node.frequency.value = f * r;
                const g = c.createGain();
                g.gain.value = 0.5 / (i + 1);
                node.connect(g).connect(bed);
                node.start(t);
                node.stop(t + 1);
            });
            osc(c, out, 'sine', 50 * r, 50 * r, t, 0.1, 0.05, 0.05, 0.75); // mains hum
            [0.12, 0.37, 0.6].forEach((d) => hiss(c, out, t + d, 0.04, 'highpass', 4200, 0.7, 0.025, 0.002, 0.03)); // ballast crackle
        },
        drip: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            [0, 0.17].forEach((delay, i) => {
                osc(c, out, 'sine', 900 * r, 1500 * r, t + delay, 0.05, 0.09 / (i + 1), 0.003, 0.12);
                hiss(c, out, t + delay, 0.03, 'bandpass', 3200, 1.2, 0.04 / (i + 1), 0.001, 0.02); // the wet tick
            });
        },
        // The heavy knock behind the door and the thud under the face: sub sweep, body noise, dull click.
        boom: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            const g = o.arg ?? 1;
            osc(c, out, 'sine', 70 * r, 24 * r, t, 0.7, 0.85 * g, 0.01, 0.42);
            hiss(c, out, t, 0.25, 'lowpass', 260, 0.7, 0.4 * g, 0.004, 0.22);
            hiss(c, out, t, 0.06, 'bandpass', 900, 1, 0.12 * g, 0.002, 0.04);
        },
        // A hinge that wants oil: a slow saw glide through a narrow band, wood groan underneath, grit on top.
        creak: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            const node = c.createOscillator();
            node.type = 'sawtooth';
            node.frequency.setValueAtTime(140 * r, t);
            node.frequency.linearRampToValueAtTime(210 * r, t + 1.0);
            node.frequency.linearRampToValueAtTime(170 * r, t + 2.0);
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
            node.connect(bp).connect(g).connect(out);
            node.start(t);
            node.stop(t + 2.2);
            osc(c, out, 'triangle', 62 * r, 78 * r, t, 1.6, 0.05, 0.5, 1.4);
            [0.2, 0.55, 0.9, 1.35].forEach((d) => hiss(c, out, t + d, 0.05, 'bandpass', 1800 + d * 600, 3, 0.03, 0.003, 0.04));
        },
        // A heavy boot on concrete: low thud, sole click, wet slap. Duller and quieter while he is far away.
        foot: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            const g = o.arg ?? 0.5;
            osc(c, out, 'sine', 82 * r, 34 * r, t, 0.24, 0.15 + 0.55 * g, 0.006, 0.34);
            hiss(c, out, t, 0.1, 'lowpass', 220 + 600 * g, 0.7, 0.06 + 0.3 * g, 0.004, 0.09);
            hiss(c, out, t, 0.03, 'bandpass', 2600 + rnd(0, 1400), 1.4, 0.02 + 0.09 * g, 0.001, 0.025); // the sole click
            hiss(c, out, t + 0.03, 0.18, 'bandpass', 900, 0.8, 0.02 + 0.06 * g, 0.01, 0.14); // water under the heel
        },
        rustle: (c, t, o) => {
            const out = voice(c, o);
            hiss(c, out, t, 1, 'bandpass', 1500 * pitch(), 0.8, 0.06, 0.35, 0.6);
            hiss(c, out, t + 0.1, 0.8, 'highpass', 5200, 0.6, 0.014, 0.3, 0.45);
        },
        sting: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            const g = o.arg ?? 1;
            [55, 58.3, 82].forEach((freq) => {
                const node = c.createOscillator();
                node.type = 'sawtooth';
                node.frequency.value = freq * r;
                const f = c.createBiquadFilter();
                f.type = 'lowpass';
                f.frequency.setValueAtTime(900, t);
                f.frequency.exponentialRampToValueAtTime(120, t + 1.1);
                node.connect(f).connect(env(c, out, t, 0.11 * g, 0.015, 1.1));
                node.start(t);
                node.stop(t + 1.3);
            });
            osc(c, out, 'sine', 41 * r, 36 * r, t, 1.1, 0.16 * g, 0.03, 1.0);
            hiss(c, out, t, 1.2, 'bandpass', 700, 0.5, 0.03 * g, 0.5, 0.6);
        },
        // The slide of the pistol locking: two small metallic clicks.
        click: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            [0, 0.085].forEach((d, i) => {
                hiss(c, out, t + d, 0.03, 'bandpass', 3600 - i * 700, 2.2, 0.12, 0.001, 0.03);
                osc(c, out, 'sine', 2400 * r - i * 500, 2400 * r - i * 500, t + d, 0.05, 0.04, 0.001, 0.05);
            });
        },
        // The shot: a hard transient, a thump, a mid body. The long tail comes from the big room send.
        shot: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            hiss(c, out, t, 0.3, 'bandpass', 1900 * r, 0.5, 0.7, 0.001, 0.09);
            osc(c, out, 'sine', 150 * r, 38 * r, t, 0.22, 0.75, 0.002, 0.3);
            hiss(c, out, t + 0.02, 0.7, 'lowpass', 700, 0.7, 0.22, 0.01, 0.55);
            hiss(c, out, t, 0.02, 'highpass', 6000, 0.7, 0.3, 0.0005, 0.012);
        },
        // Glass follows the shot by about 60 ms: shards (noise ticks) and ringing pings.
        glass: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            [0, 0.06, 0.13, 0.21, 0.33, 0.47].forEach((delay, i) => {
                hiss(c, out, t + delay, 0.06, 'highpass', 3200 + i * 500, 0.7, 0.24 - i * 0.025, 0.001, 0.05);
                osc(c, out, 'sine', (3800 + i * 610) * r, (3800 + i * 610) * r, t + delay, 0.1, 0.05, 0.001, 0.12);
            });
        },
        zap: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            for (let i = 0; i < 7; i += 1) hiss(c, out, t + i * 0.028, 0.03, 'highpass', 2400, 0.7, 0.17, 0.001, 0.024);
            const node = c.createOscillator();
            node.type = 'square';
            node.frequency.setValueAtTime(90 * r, t);
            node.frequency.exponentialRampToValueAtTime(1800 * r, t + 0.22);
            const f = c.createBiquadFilter();
            f.type = 'lowpass';
            f.frequency.value = 2400;
            node.connect(f).connect(env(c, out, t, 0.06, 0.004, 0.22));
            node.start(t);
            node.stop(t + 0.3);
            osc(c, out, 'sawtooth', 50 * r, 50 * r, t, 0.1, 0.05, 0.004, 0.25); // mains buzz under the arc
        },
        // The tube losing power: a falling electronic whine, then one small pop when the dot dies.
        crtoff: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            osc(c, out, 'sine', 3200 * r, 55 * r, t, 0.65, 0.1, 0.01, 0.65);
            osc(c, out, 'triangle', 1600 * r, 40 * r, t, 0.6, 0.03, 0.01, 0.6);
            hiss(c, out, t + 0.52, 0.04, 'bandpass', 1400, 1.1, 0.2, 0.001, 0.03);
            osc(c, out, 'sine', 160 * r, 60 * r, t + 0.52, 0.06, 0.18, 0.001, 0.07);
        },
        thud: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            osc(c, out, 'sine', 62 * r, 26 * r, t, 0.5, 0.85, 0.006, 0.6);
            hiss(c, out, t, 0.2, 'lowpass', 200, 0.7, 0.3, 0.004, 0.18);
        },
    };

    // The shot ducks every other voice and the reverb return for 300 ms, then they come back.
    const duckOthers = (c, t) => {
        [bus.duck, bus.wetDuck].forEach((node) => {
            node.gain.cancelScheduledValues(t);
            node.gain.setValueAtTime(1, t);
            node.gain.linearRampToValueAtTime(0.16, t + 0.012);
            node.gain.linearRampToValueAtTime(1, t + 0.3);
        });
    };

    // The one entry point. `delay` schedules the sound on the audio clock, so it lands with the master timeline, not with a frame.
    const play = (name, o = {}, delay = 0) => {
        const c = ctx;
        if (!c || !bus || c.state !== 'running') return;
        try {
            const t = c.currentTime + 0.03 + Math.max(0, delay);
            if (name === 'shot') duckOthers(c, t);
            const buffer = buffers[FILE[name]];
            if (buffer) {
                const source = c.createBufferSource();
                source.buffer = buffer;
                source.playbackRate.value = pitch();
                // A recording has one loudness, so the synthesized voices' intensity argument becomes its gain.
                const strength = typeof o.arg === 'number' && (name === 'foot' || name === 'sting' || name === 'boom') ? 0.4 + 0.7 * o.arg : 1;
                source.connect(voice(c, { ...o, gain: (o.gain ?? 1) * strength }));
                source.start(t);
            } else {
                SFX[name](c, t, o);
            }
        } catch (error) {
            // A sound that fails must never touch the visuals.
        }
    };
    // Where each sound sits in the room: how far away it is (muffled), how much reverb it takes, whether the shot bus carries it.
    const SPACE = {
        buzz: { dist: 0.5, send: 0.2, pan: -0.4 },
        drip: { dist: 0.45, send: 0.35 },
        boom: { dist: 0.7, send: 0.3 },
        creak: { dist: 0.55, send: 0.3, pan: -0.05 },
        foot: { send: 0.3 },
        rustle: { dist: 0.4, send: 0.2 },
        sting: { dist: 0.15, send: 0.3 },
        click: { dist: 0.1, send: 0.22, pan: -0.1 },
        shot: { dist: 0, send: 0.35, bypass: true },
        glass: { dist: 0.05, send: 0.3, bypass: true },
        zap: { dist: 0.2, send: 0.2 },
        crtoff: { dist: 0.3, send: 0.15 },
        thud: { dist: 0.3, send: 0.3 },
    };

    /* ---- small maths: everything is a pure function of the clock ---- */

    const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
    const smooth = (x) => { const v = clamp(x); return v * v * (3 - 2 * v); };
    const inOut = (x) => { const v = clamp(x); return v < 0.5 ? 4 * v * v * v : 1 - ((-2 * v + 2) ** 3) / 2; };
    // The three curves every move is built from: outCubic, inOut (in-out cubic) and a small outBack. The smooth step
    // above stays only for light and opacity ramps, never for a position.
    const outCubic = (x) => 1 - (1 - clamp(x)) ** 3;
    const outBack = (x) => { const v = clamp(x) - 1; return 1 + 2.2 * v * v * v + 1.2 * v * v; };
    // A damped spring that starts at 1 and swings through zero: the recoil.
    const spring = (x) => (x < 0 ? 0 : Math.exp(-x / 0.09) * Math.cos((2 * Math.PI * x) / 0.22));
    const frac = (x) => x - Math.floor(x);
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
        reveal0: 24.0, // the crossfade to the aim plate starts, with a push-in and a rack focus
        reveal1: 24.4, // the face is fully there
        rise0: 25.7, // the pistol comes up from below the frame
        rise1: 26.2, // and is held until the shot
        key0: 24.2, // the key light ramps up on his face
        key1: 25.4,
        shot: 26.6, // silence before it: 26.2 to 26.6
        black: 29.3,
    };
    // The gait. A stride is a run of steps, and every step is one phase from 0 to 1 between two heel strikes. That single
    // phase drives the bob (two peaks per two steps), the shoulder roll, the weight shift and the cloak. The stride itself
    // starts and stops on an in-out cubic, so he never moves at a constant speed. He stops once, in silence, around 13 s.
    const STRIDES = [
        { a: 9.55, b: 12.0, w: 0.4, strikes: [10.3, 11.15, 12.0] },
        { a: 13.6, b: 15.7, w: 0.6, strikes: [14.3, 15.05, 15.7] },
    ];
    const HEAVY = [0.3, 0.42, 0.52, 0.62, 0.78, 0.95]; // how hard each heel lands (the footsteps use the same numbers)
    const STEPS = [];
    STRIDES.forEach((st) => st.strikes.forEach((hit, i) => STEPS.push({ a: i ? st.strikes[i - 1] : st.a, b: hit })));
    const STRIKES = STRIDES.flatMap((st) => st.strikes);
    const SETTLE = 0.25; // seconds of settle at a mark: down 3 px, then up
    const WS = 6; // weight shift, px at his nearest size
    const BOB = 7;
    const SETTLE_PX = 3;
    const REST = { p: 1, s: 0, sp: 0, bob: 0, roll: 0, shift: 0, settle: 0, heel: 0, over: 0, shakeX: 0, shakeY: 0 };
    const P1 = [16.1, 19.0];
    const P2 = [19.8, 24.0];

    // [time, sound, argument]. The sound is scheduled on the audio clock just before the time, so it lands with the timeline.
    const EVENTS = [
        [0.9, 'buzz'],
        [3.6, 'drip'],
        [T.knock, 'boom', 1],
        [6.95, 'creak'],
        [10.3, 'foot', 0.3],
        [10.9, 'rustle'],
        [11.15, 'foot', 0.42],
        [12.0, 'foot', 0.52],
        [14.3, 'foot', 0.62],
        [15.05, 'foot', 0.78],
        [15.7, 'foot', 0.95],
        [16.35, 'rustle'],
        [16.7, 'sting', 0.55],
        [19.35, 'drip'],
        [21.15, 'sting', 1],
        [24.05, 'boom', 0.6],
        [25.72, 'rustle'],
        [T.rise1, 'click'],
        [T.shot, 'shot'],
        [T.shot + 0.06, 'glass'],
        [27.15, 'zap'],
        [28.1, 'crtoff'],
        [29.55, 'thud'],
    ];

    const threatAt = (u) => {
        if (u < T.knock) return 0;
        if (u < T.alert) return lerp(0, 0.08, smooth((u - T.knock) / 1.5));
        if (u < 16) return lerp(0.08, 0.62, smooth((u - T.alert) / 7.4));
        if (u < 19.8) return lerp(0.62, 0.74, smooth((u - 16) / 3.8));
        if (u < 24) return lerp(0.74, 0.92, smooth((u - 19.8) / 4.2));
        return lerp(0.92, 1, smooth((u - 24) / 1.2));
    };

    const gaitAt = (u) => {
        if (reduced) return REST;
        let p = 0;
        let sp = 0;
        let settle = 0;
        let over = 0;
        STRIDES.forEach((st) => {
            const v = clamp((u - st.a) / (st.b - st.a));
            p += st.w * inOut(v);
            if (u >= st.a && u < st.b) {
                const slope = v < 0.5 ? 12 * v * v : 12 * (1 - v) * (1 - v); // 0 at both ends, 3 in the middle
                sp = Math.max(sp, (0.15 + 0.85 * (slope / 3)) * smooth((u - st.a) / 0.25));
            } else if (u >= st.b) {
                const x = u - st.b;
                sp = Math.max(sp, 0.15 * Math.exp(-x / 0.12));
                if (x < SETTLE) settle = Math.sin((Math.PI * x) / SETTLE);
                over += Math.exp(-x / 0.3) * Math.sin((2 * Math.PI * x) / 0.55); // the cloak overshoots a little when he stops
            }
        });
        // The step phase: i + 0..1, continuous across steps, held on a whole number between strides.
        let s = 0;
        for (let i = 0; i < STEPS.length; i += 1) {
            const st = STEPS[i];
            if (u >= st.b) {
                s = i + 1;
            } else {
                if (u >= st.a) s = i + (u - st.a) / (st.b - st.a);
                break;
            }
        }
        // Forward speed follows the step phase: a little quicker at each heel contact, a little slower through the passing
        // pose. A whole step adds nothing, so every stride still ends exactly on its mark.
        let first = 0;
        STRIDES.forEach((st) => {
            const local = clamp(s - first, 0, st.strikes.length);
            const env = Math.sin(Math.PI * clamp((u - st.a) / (st.b - st.a))); // zero at both ends, so he never creeps backwards
            p += ((st.w / st.strikes.length) * 0.3 * env * Math.sin(2 * Math.PI * local)) / (2 * Math.PI);
            first += st.strikes.length;
        });
        let heel = 0;
        let shakeX = 0;
        let shakeY = 0;
        STRIKES.forEach((hit, j) => {
            const x = u - hit;
            if (x < 0) return;
            if (x < 0.45) heel += HEAVY[j] * Math.exp(-x / 0.09);
            if (HEAVY[j] >= 0.5 && x < 0.09) {
                // Four to six frames of micro-shake on the heavy steps, one fixed offset per 60 Hz frame.
                const n = Math.floor(x * 60) + j * 7;
                const amp = HEAVY[j] * Math.exp(-x / 0.04);
                shakeX += (frac(Math.sin(n * 127.1) * 43758.5453) - 0.5) * 2 * amp;
                shakeY += (frac(Math.sin(n * 311.7 + 1.7) * 43758.5453) - 0.5) * 2 * amp;
            }
        });
        const sine = Math.sin(Math.PI * s);
        return { p: clamp(p), s, sp, bob: sp * Math.abs(sine), roll: sp * sine, shift: sp * Math.cos(Math.PI * s), settle, heel, over, shakeX, shakeY };
    };

    // Handheld drift: three sines per axis at frequencies that never line up, so the camera never repeats itself.
    const driftX = (t) => 2.1 * Math.sin(0.37 * t + 0.6) + 1.3 * Math.sin(0.93 * t + 2.1) + 0.55 * Math.sin(2.17 * t + 4.0);
    const spaceFor = (name, arg, at) => {
        const o = { ...(SPACE[name] || {}), arg };
        const p = gaitAt(at).p;
        if (name === 'foot') {
            o.pan = lerp(-0.55, 0.5, p); // the footsteps follow him across the room, left to right
            o.dist = 0.85 * (1 - p); // far and muffled at the door, open when he is close
        }
        if (name === 'rustle') o.pan = lerp(-0.4, 0.4, p);
        if (name === 'drip') o.pan = at < 10 ? -0.5 : 0.45;
        return o;
    };
    const driftY = (t) => 1.6 * Math.sin(0.29 * t + 1.0) + 0.9 * Math.sin(0.71 * t + 0.3) + 0.45 * Math.sin(1.93 * t + 2.6);

    /* ---- the art plates: a need-ordered manifest; nothing starts until what the current moment needs is decoded ---- */

    const ART = '/img/honeypot/';
    const WALK = [1, 2, 3, 4].map((n) => `${ART}walk-sheet/walk-${n}.webp`); // optional walk cycle, see the walk block below
    const ASSETS = [
        { key: 'closed', src: `${ART}room-closed.webp`, from: 0, until: 10.5 },
        { key: 'sand', src: `${ART}sandwich-hero.webp`, from: 2.2, until: 24.2 },
        { key: 'fog', src: `${ART}fog.webp`, from: 2.2, until: 24.2 },
        { key: 'pipes', src: `${ART}foreground-pipes.webp`, from: 2.2, until: 24.2 },
        { key: 'open', src: `${ART}room-open.webp`, from: 6.6, until: 24.2 },
        { key: 'spill', src: `${ART}light-spill.webp`, from: 6.6, until: 24.2 },
        { key: 'char', src: hz.dataset.figure, from: 8.0, until: 24.2 },
        ...WALK.map((src, i) => ({ key: `walk${i}`, src, from: 4.0, until: 24.2, soft: true })), // optional: the scene never waits for them
        { key: 'aim', src: `${ART}aim-pose.webp`, from: 23.6, until: 28.6 },
        { key: 'flash', src: `${ART}muzzle-flash.webp`, from: 26.0, until: 27.4 },
        { key: 'crack', src: `${ART}glass-crack.webp`, from: 26.0, until: 28.6 },
    ];
    const asset = (key) => ASSETS.find((a) => a.key === key);
    const needed = (a, u) => a.from <= u + 0.25 && a.until > u;
    // True when every plate the moment u needs is decoded (a plate that failed to load counts: the scene just stays dark there).
    const gate = (u) => ASSETS.every((a) => a.ready || a.soft || !needed(a, u));
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
        // The eyes behind the lenses (plate fractions), where the red light ignites.
        aim.style.setProperty('--e1x', `${(W / 2 + (0.417 - 0.5) * aw).toFixed(1)}px`);
        aim.style.setProperty('--e2x', `${(W / 2 + (0.658 - 0.5) * aw).toFixed(1)}px`);
        aim.style.setProperty('--ey', `${(at + 0.367 * ah).toFixed(1)}px`);
        aim.style.setProperty('--er', `${(0.07 * aw).toFixed(1)}px`);
        geo.drop = Math.max(60, H - (at + 0.4 * ah) + 0.05 * H); // how far the pistol waits below the frame

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

        // The crack plate is 3:2 with its impact point at (0.507, 0.346). It is sized so all four of its edges lie outside the
        // viewport (24 px margin) and never less than cover plus 10 percent; the image mask also fades to nothing inside its box.
        const m = 24;
        const need = Math.max((mx + m) / 0.507, (W - mx + m) / 0.493, ((my + m) * 1.5) / 0.346, ((H - my + m) * 1.5) / 0.654);
        const cw = Math.max(need, Math.max(W, 1.5 * H) * 1.1);
        crackImg.style.width = `${cw.toFixed(1)}px`;
        crackImg.style.left = `${(mx - 0.507 * cw).toFixed(1)}px`;
        crackImg.style.top = `${(my - (0.346 * cw) / 1.5).toFixed(1)}px`;
        crackWrap.style.setProperty('--cx', `${mx.toFixed(1)}px`);
        crackWrap.style.setProperty('--cy', `${my.toFixed(1)}px`);
        geo.reach = Math.max(Math.hypot(mx, my), Math.hypot(W - mx, my), Math.hypot(mx, H - my), Math.hypot(W - mx, H - my)) * 1.5;

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
    const fig = { ready: false, w: 0, h: 0, cw: 0, face: FACE_C, glints: GLINTS, lit: null, dark: null, ctx: null, strips: lite ? 9 : 16, drawAt: 0, every: lite ? 50 : 33 };

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

    /* ---- the real walk: four poses of one cycle, keyed out of their red background once and cached.
       Sheet order (public/img/honeypot/walk-sheet/): walk-1 left foot contact, walk-2 passing, walk-3 right foot contact,
       walk-4 passing. One step is two poses, so the step phase s from the gait picks the pose (poses sit on half steps)
       and a short phase window crossfades neighbours. If the order on disk differs, change WALK_ORDER only.
       Frames are normalized from their own alpha box: the same height for all, the sole of the lowest foot on the bottom
       row and the torso (not the planted foot, which moves under the body) on the centre line, so the body never jumps.
       Any failure (a missing file, a tainted canvas, a bad key) leaves wk.ok false and the standing cutout is used. ---- */

    const WALK_ORDER = lite ? [0, 1, 2, 1] : [0, 1, 2, 3]; // pose cycle as indexes into the sheet; lite keeps three frames
    const WALK_XF = 0.13; // half width of the crossfade between poses, in half steps (about 100 ms at his cadence)
    const wk = { ok: false, failed: reduced, job: null, frames: [], shown: -1 };

    // Key the red out of one sheet frame. The key colour is read from the four corners, so the exact red does not matter.
    // Flat key-coloured pixels anywhere go (the gaps between arm and body); softer pixels go only when they touch the
    // background, with their red mixed out (decontamination), so a red rim light inside the body is left alone.
    function* keyImage(img) {
        const w = img.naturalWidth;
        const h = img.naturalHeight;
        const cv = document.createElement('canvas');
        cv.width = w;
        cv.height = h;
        const cx = cv.getContext('2d', { willReadFrequently: true });
        cx.drawImage(img, 0, 0);
        const id = cx.getImageData(0, 0, w, h);
        const d = id.data;
        let kr = 0;
        let kg = 0;
        let kb = 0;
        let kn = 0;
        [[0, 0], [w - 6, 0], [0, h - 6], [w - 6, h - 6]].forEach(([x0, y0]) => {
            for (let y = y0; y < y0 + 6; y += 1) {
                for (let x = x0; x < x0 + 6; x += 1) {
                    const i = (y * w + x) * 4;
                    kr += d[i];
                    kg += d[i + 1];
                    kb += d[i + 2];
                    kn += 1;
                }
            }
        });
        kr /= kn;
        kg /= kn;
        kb /= kn;
        const dist2 = (i) => {
            const dr = d[i] - kr;
            const dg = d[i + 1] - kg;
            const db = d[i + 2] - kb;
            return dr * dr + dg * dg + db * db;
        };
        const FLAT = 30;
        const SOFT = 150;
        const n = w * h;
        const bgm = new Uint8Array(n); // 1 where the background is
        for (let p = 0; p < n; p += 1) {
            if (dist2(p * 4) < FLAT * FLAT) bgm[p] = 1;
            if ((p & 65535) === 0 && performance.now() > wkUntil) yield;
        }
        // flood in from the border through softly similar pixels (the anti-aliased rim of the figure is not reached)
        const stack = new Int32Array(n);
        let top = 0;
        const push = (p) => {
            if (!bgm[p] && dist2(p * 4) < 4096) {
                bgm[p] = 1;
                stack[top] = p;
                top += 1;
            }
        };
        for (let x = 0; x < w; x += 1) {
            push(x);
            push((h - 1) * w + x);
        }
        for (let y = 0; y < h; y += 1) {
            push(y * w);
            push(y * w + w - 1);
        }
        let spin = 0;
        while (top > 0) {
            spin += 1;
            if ((spin & 65535) === 0 && performance.now() > wkUntil) yield;
            top -= 1;
            const p = stack[top];
            const x = p % w;
            if (x > 0) push(p - 1);
            if (x < w - 1) push(p + 1);
            if (p >= w) push(p - w);
            if (p < n - w) push(p + w);
        }
        // a two pixel band around the background gets a soft alpha and its colour freed from the red
        const band = new Uint8Array(n);
        for (let pass = 0; pass < 2; pass += 1) {
            for (let y = 1; y < h - 1; y += 1) {
                if (performance.now() > wkUntil) yield;
                for (let x = 1; x < w - 1; x += 1) {
                    const p = y * w + x;
                    if (bgm[p] || band[p] > pass) continue;
                    if (bgm[p - 1] || bgm[p + 1] || bgm[p - w] || bgm[p + w] || (pass && (band[p - 1] === pass || band[p + 1] === pass || band[p - w] === pass || band[p + w] === pass))) band[p] = pass + 1;
                }
            }
        }
        for (let p = 0; p < n; p += 1) {
            if ((p & 65535) === 0 && performance.now() > wkUntil) yield;
            const i = p * 4;
            if (bgm[p]) {
                d[i] = 0;
                d[i + 1] = 0;
                d[i + 2] = 0;
                d[i + 3] = 0;
            } else if (band[p]) {
                const a = clamp(smooth((Math.sqrt(dist2(i)) - FLAT) / (SOFT - FLAT)), 0.04, 1);
                d[i] = clamp((d[i] - (1 - a) * kr) / a, 0, 255);
                d[i + 1] = clamp((d[i + 1] - (1 - a) * kg) / a, 0, 255);
                d[i + 2] = clamp((d[i + 2] - (1 - a) * kb) / a, 0, 255);
                d[i + 3] = Math.round(255 * a);
            }
        }
        cx.putImageData(id, 0, 0);
        // the alpha box (a row or a column needs a few solid pixels, so a stray speck does not stretch it)
        let y0 = -1;
        let y1 = -1;
        let x0 = w;
        let x1 = -1;
        let mx = 0;
        let mn = 0;
        for (let y = 0; y < h; y += 1) {
            if ((y & 127) === 0 && performance.now() > wkUntil) yield;
            let count = 0;
            for (let x = 0; x < w; x += 1) {
                if (d[(y * w + x) * 4 + 3] > 128) {
                    count += 1;
                    if (x < x0) x0 = x;
                    if (x > x1) x1 = x;
                }
            }
            if (count >= 3) {
                if (y0 < 0) y0 = y;
                y1 = y;
            }
        }
        if (y0 < 0 || y1 - y0 < 100) throw new Error('walk frame has no figure');
        // torso centre line: alpha weighted mean x over 25 to 55 percent of the height
        const r0 = Math.round(y0 + 0.25 * (y1 - y0));
        const r1 = Math.round(y0 + 0.55 * (y1 - y0));
        for (let y = r0; y <= r1; y += 1) {
            for (let x = 0; x < w; x += 1) {
                const a = d[(y * w + x) * 4 + 3] / 255;
                mx += a * x;
                mn += a;
            }
        }
        return { cv, w, h, y0, y1, x0, x1, ax: mn ? mx / mn : (x0 + x1) / 2 };
    }

    // The work runs as a job that gives the thread back after about 6 ms of each animation frame, so four frames are
    // keyed and graded without one visible hitch.
    let wkUntil = 0;
    function* walkJob() {
        try {
            const imgs = WALK.map((_, i) => asset(`walk${i}`));
            const used = WALK_ORDER.filter((v, i) => WALK_ORDER.indexOf(v) === i);
            const keyed = [];
            for (let i = 0; i < used.length; i += 1) {
                const a = imgs[used[i]];
                if (!a.img || !a.img.naturalWidth) throw new Error('walk frame missing');
                keyed[i] = yield* keyImage(a.img);
                yield;
            }
            const fh = fig.h;
            const scales = keyed.map((kf) => fh / (kf.y1 - kf.y0 + 1));
            const half = Math.max(...keyed.map((kf, i) => Math.max(kf.ax - kf.x0, kf.x1 - kf.ax) * scales[i]));
            const fw = Math.max(fig.w, Math.ceil(2 * half) + 8);
            const frames = [];
            for (let i = 0; i < used.length; i += 1) {
                const kf = keyed[i];
                const lit = document.createElement('canvas');
                lit.width = fw;
                lit.height = fh;
                const lc = lit.getContext('2d', { willReadFrequently: true });
                lc.drawImage(kf.cv, fw / 2 - kf.ax * scales[i], -kf.y0 * scales[i], kf.w * scales[i], kf.h * scales[i]);
                yield;
                const dark = document.createElement('canvas');
                dark.width = fw;
                dark.height = fh;
                const data = lc.getImageData(0, 0, fw, fh);
                const px = data.data;
                for (let j = 0; j < px.length; j += 4) {
                    if ((j & 262143) === 0 && performance.now() > wkUntil) yield;
                    const rim = smooth((px[j] - 70) / 110);
                    const gain = 0.085 + 0.9 * rim;
                    px[j] *= gain;
                    px[j + 1] *= gain;
                    px[j + 2] *= gain;
                }
                dark.getContext('2d').putImageData(data, 0, 0);
                wk.frames[used[i]] = { lit, dark };
                kf.cv.width = 1; // release the full size canvas
                kf.cv.height = 1;
                yield;
            }
            // adopt: every size that depended on the standing cutout follows the walk frames
            fig.w = fw;
            fig.cw = fw + PAD * 2;
            figCanvas.width = fig.cw;
            figCanvas.height = fh;
            [shadowEl, reflectEl].forEach((cv) => {
                cv.width = fig.cw;
                cv.height = fh;
            });
            fig.face = [fw / 2 + 4, FACE_C[1]];
            fig.glints = [[fw / 2 - 22, GLINTS[0][1]], [fw / 2 + 27, GLINTS[1][1]]];
            wk.shown = -1;
            wk.ok = true;
        } catch (error) {
            wk.failed = true; // keep the standing cutout, never crash
        }
    }
    const walkStep = () => {
        if (wk.ok || wk.failed || !fig.ready || !WALK.every((_, i) => asset(`walk${i}`).ready)) return;
        if (!wk.job) wk.job = walkJob();
        wkUntil = performance.now() + 6;
        try {
            if (wk.job.next().done) wk.job = null;
        } catch (error) {
            wk.failed = true;
        }
    };

    // The black copy for the cast shadow and the reflection follows the pose on screen (same frame, the CSS flips it).
    const castFrame = (frame) => {
        [shadowEl, reflectEl].forEach((cv) => {
            const c = cv.getContext('2d');
            c.globalCompositeOperation = 'source-over';
            c.clearRect(0, 0, cv.width, cv.height);
            c.drawImage(frame.lit, PAD, 0);
            c.globalCompositeOperation = 'source-in';
            c.fillStyle = '#000';
            c.fillRect(0, 0, cv.width, cv.height);
            c.globalCompositeOperation = 'source-over';
        });
    };

    // Where the cutout stands, from the gait: path position on an in-out cubic, plus the bob, the weight shift, the settle
    // at a mark and the small scale rise at each heel strike. Offsets are px at his nearest size and shrink with distance.
    const figGeom = (gait) => {
        const p = gait.p;
        const x0 = 0.49 * PW;
        const y0 = 0.708 * PH;
        const h0 = 0.3 * PH;
        const side = W >= 900 ? 0.09 * W : 0.05 * PW;
        const x1 = PW / 2 + side;
        const y1 = (PH - H) / 2 + 0.955 * H;
        const h1 = 0.9 * H;
        const hgt = lerp(h0, h1, p);
        const near = hgt / h1;
        return {
            x: lerp(x0, x1, p) + gait.shift * WS * near,
            y: lerp(y0, y1, p) - (gait.bob * BOB - gait.settle * SETTLE_PX) * near,
            sc: (hgt / (fig.h || 1180)) * (1 + 0.012 * gait.heel),
            p,
        };
    };
    const toScreen = (g, cx, cy) => ({
        x: (W - PW) / 2 + g.x + (cx + PAD - fig.cw / 2) * g.sc,
        y: (H - PH) / 2 + g.y - (fig.h - cy) * g.sc,
    });

    const turnAt = (u) => (reduced ? 0 : smooth((u - 12.9) / 0.5) * (1 - 0.35 * smooth((u - 13.8) / 0.4)) * (1 - smooth((u - 14.2) / 0.5)));

    // Lens glints: two small points, faintly alive; they catch the light as the door opens, and go red as the face comes up.
    const drawGlints = (c, u, D) => {
        const rd = smooth((u - T.reveal0) / 0.25);
        const ga = 0.1 + 0.32 * D + 0.5 * rd;
        const rgb = `${Math.round(235 + 20 * rd)},${Math.round(252 - 190 * rd)},${Math.round(255 - 195 * rd)}`;
        fig.glints.forEach(([gx, gy]) => {
            const r = 5 + 4 * rd;
            const g = c.createRadialGradient(PAD + gx, gy, 0, PAD + gx, gy, r);
            g.addColorStop(0, `rgba(${rgb},${ga.toFixed(3)})`);
            g.addColorStop(1, `rgba(${rgb},0)`);
            c.fillStyle = g;
            c.fillRect(PAD + gx - 14, gy - 14, 28, 28);
        });
    };

    // The walking figure. The head and torso are one piece and barely move; only the lower 58 percent is cut into strips
    // that sway out of phase (the cloak and its hem), their amplitude following the step speed. Segments tile on whole rows,
    // and a crossfade between two poses is a premultiplied blend (A at 1 - w, then B at w with 'lighter'), so the body never
    // goes thin in the middle of it.
    const drawWalk = (t, u, D, body, gait) => {
        const c = fig.ctx;
        const fw = fig.w;
        const fh = fig.h;
        const q = gait.s * 2;
        const idx = Math.floor(q + 0.5);
        const loc = q - idx;
        let nb = idx;
        let w = 0;
        if (loc > 0.5 - WALK_XF) {
            nb = idx + 1;
            w = smooth((loc - (0.5 - WALK_XF)) / (2 * WALK_XF));
        } else if (loc < -0.5 + WALK_XF) {
            nb = idx - 1;
            w = smooth((-0.5 + WALK_XF - loc) / (2 * WALK_XF));
        }
        const pose = (i) => wk.frames[WALK_ORDER[((i % 4) + 4) % 4]];
        const A = pose(idx);
        const B = w > 0.004 ? pose(nb) : null;
        const main = w > 0.5 ? B : A;
        const mainIdx = w > 0.5 ? nb : idx;
        if (mainIdx !== wk.shown) {
            wk.shown = mainIdx;
            castFrame(main);
        }

        c.clearRect(0, 0, fig.cw, fh);
        const seg = (y0, y1, dx, sw = fw, off = 0) => {
            const hgt = y1 - y0;
            if (hgt <= 0) return;
            const x = PAD + dx + off;
            if (B) {
                c.globalAlpha = 1 - w;
                c.drawImage(A.dark, 0, y0, fw, hgt, x, y0, sw, hgt);
                c.globalCompositeOperation = 'lighter';
                c.globalAlpha = w;
                c.drawImage(B.dark, 0, y0, fw, hgt, x, y0, sw, hgt);
                c.globalCompositeOperation = 'source-over';
                if (body > 0.02) {
                    c.globalAlpha = (1 - w) * body;
                    c.drawImage(A.lit, 0, y0, fw, hgt, x, y0, sw, hgt);
                    c.globalAlpha = w * body;
                    c.drawImage(B.lit, 0, y0, fw, hgt, x, y0, sw, hgt);
                }
            } else {
                c.globalAlpha = 1;
                c.drawImage(A.dark, 0, y0, fw, hgt, x, y0, sw, hgt);
                if (body > 0.02) {
                    c.globalAlpha = body;
                    c.drawImage(A.lit, 0, y0, fw, hgt, x, y0, sw, hgt);
                }
            }
        };

        // The cloak follows his weight shift 120 to 180 ms late (the hem is the slowest), so it trails him and then overshoots a stop.
        const calm = reduced ? 0.4 : 1;
        const ref = Math.max(0.1, (0.9 * H) / fh);
        const shiftFig = (x) => (WS / ref) * gaitAt(x).shift;
        const now0 = shiftFig(u);
        const turn = turnAt(u);
        const split = Math.round(0.42 * fh);
        const upper = reduced ? 0 : (shiftFig(u - 0.12) - now0) * 0.4 + Math.sin(t * 0.9) * 0.4;
        if (turn > 0.001) {
            const hd = Math.round(0.2 * fh);
            const sw = fw * (1 - 0.045 * turn);
            seg(0, hd, upper + turn * 3.5, sw, (fw - sw) / 2);
            seg(hd, split, upper);
        } else {
            seg(0, split, upper);
        }
        const nh = Math.max(3, Math.round(fig.strips * 0.58));
        for (let i = 0; i < nh; i += 1) {
            const y0 = Math.round(split + ((fh - split) * i) / nh);
            const y1 = Math.round(split + ((fh - split) * (i + 1)) / nh);
            const f = y0 / fh;
            const hem = smooth((f - 0.38) / 0.55);
            const lag = 0.12 + 0.06 * hem;
            const trail = reduced ? 0 : (shiftFig(u - lag) - now0) * (0.4 + 2.2 * hem);
            const over = gait.over * 5 * (0.15 + 1.8 * hem);
            const flutter = (0.5 + 2.2 * gait.sp) * 0.55 * (0.25 + 2.2 * hem) * Math.sin(t * 1.7 + i * 0.9) * calm;
            seg(y0, y1, trail + over + flutter);
        }
        c.globalAlpha = 1;
        drawGlints(c, u, D);
    };

    const drawFigure = (t, u, D, body, gait) => {
        if (!fig.ready) return;
        if (wk.ok) {
            drawWalk(t, u, D, body, gait);
            return;
        }
        const c = fig.ctx;
        const n = fig.strips;
        const sh = fig.h / n;
        const calm = reduced ? 0.4 : 1;
        // The cloak follows his weight shift 120 to 180 ms late (the hem is the slowest), so it trails him and then overshoots a stop.
        const ref = Math.max(0.1, (0.9 * H) / fig.h);
        const shiftFig = (x) => (WS / ref) * gaitAt(x).shift;
        const now0 = shiftFig(u);
        const turn = turnAt(u);
        c.clearRect(0, 0, fig.cw, fig.h);
        for (let i = 0; i < n; i += 1) {
            const y = i * sh;
            const f = y / fig.h;
            const hem = smooth((f - 0.38) / 0.55);
            const lag = 0.12 + 0.06 * hem;
            const trail = reduced ? 0 : (shiftFig(u - lag) - now0) * (0.4 + 2.2 * hem);
            const over = gait.over * 5 * (0.15 + 1.8 * hem);
            const flutter = (0.5 + 2.2 * gait.sp) * 0.55 * (0.25 + 2.2 * hem) * Math.sin(t * 1.7 + i * 0.62) * calm;
            let dx = trail + over + flutter + Math.sin(t * 0.9 + i * 0.3) * 0.4 * (1 - f);
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
        drawGlints(c, u, D);
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
        // ---- the lens: the glass follows the shot by 60 ms and the crack grows out of the impact point through a feathered
        // radial mask in 180 ms, drawn with screen blending so its dark parts never leave a box behind.
        const c = u - (T.shot + 0.06);
        if (c < 0) {
            if (cls.crackOn !== false) {
                cls.crackOn = false;
                crackWrap.style.opacity = '0';
            }
        } else {
            cls.crackOn = true;
            const alpha = x < 0.5 ? 0.3 : 0.3 + 0.62 * smooth((x - 0.5) / 0.9);
            crackWrap.style.opacity = alpha.toFixed(3);
            const grow = reduced ? 1 : outCubic(c / 0.18);
            // Once it has fully grown the growth mask covers nothing the viewport can see, so it is dropped (one less offscreen pass).
            const grown = grow >= 1;
            if (grown !== cls.grown) {
                cls.grown = grown;
                crackWrap.classList.toggle('is-grown', grown);
            }
            const cr = Math.round((grow * geo.reach) / 6) * 6;
            if (cr !== cls.cr) {
                cls.cr = cr;
                crackWrap.style.setProperty('--cr', `${cr}px`);
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
        // The camera takes the recoil a hair after the plate: up 6 px, then back on the same spring.
        const kick = !reduced && x > 0.02 && x < 0.7 ? -6 * spring(x - 0.02) : 0;
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

    /* ---- the Suno cue: decoded once, played once through the master bus, locked to the timeline.
       The file is 29.8 s and the shot is at 26.6 s, so it is placed, not stretched: its loudest bar (CUE_PEAK, measured)
       sits on the start of the face reveal (T.reveal0), which makes it start 1.0 s into the file at u = 0 and cut hard at
       the shot (a sample-accurate stop() scheduled when it starts). The gain follows the scene: silent until the door
       opens, a slow swell as he appears, strongest on the approach, a climax from the reveal, a 300 ms gap, the cut.
       A reload at second N starts it at the matching file position. If the decode or the context fails, a plain Audio
       element plays the same file with the same envelope. ---- */

    const CUE = '/audio/honeypot-cue.mp3';
    const CUE_PEAK = 25.0; // second of the file where the track peaks, landed on T.reveal0
    const CUE_GAIN = 0.55; // pre-bus gain at the climax: about -8 dBFS peak and -16 dBFS RMS after the bus
    const CUE_DUCK = dB(-4); // under dialogue and sound effects, only 4 dB
    const CUE_GAP = 0.3; // the one silence, right before the shot
    let cue = null; // the Audio element, only used when WebAudio cannot
    let cueBuf = null;
    let cueMode = 'loading'; // loading, buffer, element
    let cueGain = null;
    let cueSrc = null;
    let cueT0 = 0;
    let cueOff = 0;
    let cueAt = -1e9;
    let cueVol = 0;
    let cueBlocked = false;

    const loadCue = (c) => {
        fetch(CUE, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.arrayBuffer() : Promise.reject(new Error('missing'))))
            .then((data) => new Promise((ok, fail) => {
                const run = c.decodeAudioData(data, ok, fail);
                if (run && run.catch) run.catch(fail);
            }))
            .then((buffer) => {
                cueBuf = buffer;
                cueGain = c.createGain();
                cueGain.gain.value = 0;
                cueGain.connect(bus.master);
                const send = c.createGain(); // a small send into the cold-room reverb, so it sits in the same space
                send.gain.value = 0.12;
                cueGain.connect(send);
                send.connect(bus.verb);
                cueMode = 'buffer';
            })
            .catch(() => { cueMode = 'element'; });
    };

    // 0 to 1: the shape of the level across the scene.
    const cueEnv = (u) => {
        const swell = smooth((u - T.door0) / 2.2); // the door opens: it comes up from nothing
        const steps = 0.5 + 0.2 * smooth((u - 15.4) / 1.6); // the approach
        const climax = 0.3 * smooth((u - (T.reveal0 - 1.4)) / 1.4); // the reveal
        return swell * (steps + climax);
    };
    const cueStop = () => {
        if (!cueSrc) return;
        try { cueSrc.stop(); } catch (error) { /* already stopped */ }
        try { cueSrc.disconnect(); } catch (error) { /* already gone */ }
        cueSrc = null;
    };
    const cueStart = (c, u, fileAt) => {
        cueStop();
        try {
            const src = c.createBufferSource();
            src.buffer = cueBuf;
            src.connect(cueGain);
            const when = c.currentTime + 0.03;
            src.start(when, fileAt);
            src.stop(when + Math.max(0.05, (T.shot - u) * k)); // the hard cut, on the audio clock
            cueSrc = src;
            cueT0 = when;
            cueOff = fileAt;
        } catch (error) {
            cueSrc = null;
        }
    };
    const syncCue = (now, u, dt, talking) => {
        if (u < 0 || document.hidden || u >= T.shot + 0.05) {
            cueStop();
            if (cue && !cue.paused) cue.pause();
            cueVol = 0;
            return;
        }
        const gap = u >= T.shot - CUE_GAP;
        let want = gap ? 0 : cueEnv(u);
        if (!gap && (talking || now < duckUntil)) want *= CUE_DUCK;
        cueVol += (want - cueVol) * Math.min(1, dt * (gap ? 40 : 7));
        const fileAt = (u - T.reveal0) * k + CUE_PEAK;

        if (cueMode === 'buffer') {
            const c = ctx;
            if (!c || !bus || !cueGain || c.state !== 'running') return;
            cueGain.gain.setTargetAtTime(cueVol * CUE_GAIN, c.currentTime, 0.02);
            if (u < T.door0 - 0.3) return; // nothing audible yet: start just before the door
            if (!cueSrc) {
                if (now - cueAt > 400 && fileAt < cueBuf.duration) {
                    cueAt = now;
                    cueStart(c, u, fileAt);
                }
            } else if (c.currentTime > cueT0 && Math.abs(cueOff + (c.currentTime - cueT0) - fileAt) > 0.25 && now - cueAt > 500) {
                cueAt = now; // the timeline jumped (reload, a snap, a hidden tab): land on the same second again
                cueStart(c, u, fileAt);
            }
            return;
        }

        if (cueMode !== 'element') return;
        if (!cue) {
            cue = new Audio(CUE);
            cue.preload = 'auto';
            cue.volume = 0;
        }
        cue.volume = clamp(cueVol * CUE_GAIN);
        if (u < T.door0 - 0.3) return;
        if (cue.paused) {
            if (now - cueAt > 900) {
                cueAt = now;
                try { cue.currentTime = fileAt; } catch (error) { /* not seekable yet */ }
                const run = cue.play();
                if (run && run.catch) run.catch(() => { cueBlocked = true; });
            }
        } else if (Math.abs(cue.currentTime - fileAt) > 0.4) {
            try { cue.currentTime = fileAt; } catch (error) { /* not seekable yet */ }
        }
    };

    /* ---- sound needs a gesture in most browsers. Only if the audio clock cannot run, a dark CCTV card asks for a tap.
       Any tap or key opens sound and removes it. The timeline never waits for it: the server's clock keeps running. ---- */

    let tapEl = null;
    let tapState = 'check'; // check, shown, done
    const tapClose = () => {
        if (tapState !== 'shown') return;
        tapState = 'done';
        if (tapEl) {
            const el = tapEl;
            el.classList.add('is-gone');
            window.setTimeout(() => el.remove(), 450);
            tapEl = null;
        }
    };
    const tapOpen = () => {
        tapState = 'shown';
        tapEl = document.createElement('div');
        tapEl.className = 'cr-tap';
        tapEl.innerHTML = '<div class="cr-tap-box"><span class="cr-tap-rec"><i></i>REC</span><b>SINYAL TERTAHAN</b><em>KETUK UNTUK MELANJUTKAN</em><small>CAM 3 / AUDIO OFF</small></div>';
        hz.append(tapEl);
    };
    const tapCheck = (now) => {
        if (tapState !== 'check' || now - startedAt < 500) return;
        const running = ctx && ctx.state === 'running';
        if (running) {
            tapState = 'done'; // audio is allowed already: no card
        } else if (ctx || cueBlocked) {
            tapOpen();
        } else if (!(window.AudioContext || window.webkitAudioContext) && now - startedAt > 1500) {
            tapState = 'done';
        }
    };

    /* ---- the master frame: one rAF, one delta clock ---- */

    let blurNow = -1;
    let aimBlurNow = -1;
    let perf = 0.0167;
    let degraded = false;
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
        perf += (dt - perf) * 0.05;
        // A slow phone gets a cheaper figure (fewer strips, a slower redraw, no focus blur) instead of dropping frames.
        if (!degraded && now - startedAt > 4000 && perf > 0.024) {
            degraded = true;
            fig.strips = Math.min(fig.strips, 8);
            fig.every = 50;
            hz.classList.add('is-lite');
        }

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
        else if (!wk.ok && !wk.failed && u > 2.5) walkStep();

        // One-shot sounds: scheduled on the audio clock a hair before the timeline reaches them, once each. Anything the
        // clock passed long ago (a reload at second N) stays silent, so a resume never replays old sounds.
        if (!waiting) {
            EVENTS.forEach(([at, name, arg], i) => {
                if (fired.has(i)) return;
                const lead = (at - u) * k;
                if (lead > 0.12) return;
                fired.add(i);
                if (lead < -0.8) return;
                play(name, spaceFor(name, arg, at), lead);
                duckUntil = now + (name === 'shot' ? 1400 : 800);
            });
        }

        // ---- light and world state
        const bootOp = smooth((u - 1.7) / 1.4);
        const cut = reduced ? smooth((u - T.reveal0) / 0.4) : inOut((u - T.reveal0) / (T.reveal1 - T.reveal0)); // the 400 ms crossfade
        const roomOn = u < T.reveal1 + 0.1;
        const D = smooth((u - 7.2) / 2.8);
        const door = smooth((u - T.door0) / (T.door1 - T.door0));
        const walk = gaitAt(u);
        const p = walk.p;
        // One slow dolly, built from two overlapping eases so it never stops: a gentle push over the room, then the long push
        // toward his face that is still moving when the crossfade starts, plus a little extra through the fade.
        const cam1 = inOut((u - 7.5) / 12.5);
        const cam2 = clamp(inOut((u - 18) / 8) / 0.9375);
        const S = Math.exp(Math.log(1.12) * cam1 + Math.log(2.7 / 1.12) * cam2 + Math.log(1.1) * smooth((u - 23.6) / 0.85));

        if (roomOn) {
            room.style.opacity = (bootOp * (1 - cut)).toFixed(3);
            openImg.style.opacity = door.toFixed(3);
            spill.style.opacity = (D * (1 - 0.3 * p)).toFixed(3);
            bloom.style.opacity = (0.5 * D * (1 - 0.3 * cam1)).toFixed(3);
            fogs.style.opacity = (0.06 + 0.26 * D).toFixed(3);
            dust.style.opacity = D.toFixed(3);
            doorline.style.opacity = (0.5 * Math.max(pulse(u, 5.5, 0.12, 0.32), pulse(u, 6.0, 0.12, 0.3), pulse(u, 6.5, 0.12, 0.34)) * (1 - door)).toFixed(3);

            // ---- the owner
            const fOpacity = reduced ? smooth((u - 9) / 2.5) : smooth((u - T.appear) / 0.7);
            const g = figGeom(walk);
            const cw = fig.cw || 724;
            const ch = fig.h || 1180;
            figEl.style.opacity = fOpacity.toFixed(3);
            // Shoulder roll: a turn of up to 1.2 degrees about his hips, alternating with each step.
            const hips = -0.48 * ch;
            figCanvas.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch).toFixed(1)}px, 0) scale(${g.sc.toFixed(4)}) translate(0px, ${hips.toFixed(1)}px) rotate(${(1.2 * walk.roll).toFixed(3)}deg) translate(0px, ${(-hips).toFixed(1)}px)`;
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

            if (now - fig.drawAt > fig.every) {
                fig.drawAt = now;
                drawFigure(ut, u, D, 0.05 + 0.1 * cam2, walk);
            }

            // ---- camera: one move, handheld drift on top, parallax by layer
            const drift = reduced ? 0 : 1;
            const dx = (driftX(ut) + walk.shakeX * 2) * drift;
            const dy = (driftY(ut) + walk.shakeY * 2) * drift;
            const face = toScreen(g, fig.face[0], fig.face[1]);
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
            if (!lite && !degraded && !reduced) {
                const blur = Math.round(3 * smooth((u - 16.5) / 7) * 2) / 2;
                if (blur !== blurNow) {
                    blurNow = blur;
                    layers.far.style.filter = blur ? `blur(${blur}px)` : '';
                    sandWrap.style.filter = blur ? `blur(${(blur * 0.8).toFixed(1)}px)` : '';
                }
            }
        }

        // ---- the aim plate: a 400 ms crossfade with a push-in and a rack focus, the key light and the red eyes, the pistol
        // coming up from below the frame (500 ms, small overshoot, then a 400 ms hold), the shot and its recoil
        if (u >= T.reveal0 - 0.05) {
            const F = flashAt(u);
            const x = u - T.shot;
            const rx = u - T.reveal0;
            // push-in 1 to 1.12 as the face comes up, then a slow creep toward the shot
            const sa = reduced ? 1 : 1 + 0.12 * outCubic(rx / 0.45) + 0.03 * inOut((u - 25.0) / 1.6);
            // recoil: the plate jumps up 10 px and settles back on a damped spring, with a hair of scale and tilt
            const dyP = !reduced && x > 0 && x < 0.7 ? -10 * spring(x) : 0;
            const hit = !reduced && x > 0 && x < 0.6 ? Math.exp(-x / 0.07) : 0;
            aim.style.opacity = cut.toFixed(3);
            aim.style.transform = `translate3d(0, ${dyP.toFixed(1)}px, 0) scale(${(sa * (1 + 0.02 * hit)).toFixed(4)}) rotate(${(-1 * hit).toFixed(2)}deg)`;
            // rack focus: the plate starts soft and pulls sharp as the crossfade lands
            const blur = reduced || lite || degraded ? 0 : Math.round(7 * (1 - outCubic(rx / 0.9)) * 2) / 2;
            if (blur !== aimBlurNow) {
                aimBlurNow = blur;
                aim.style.filter = blur ? `blur(${blur}px)` : '';
            }
            // the pistol rises from below the frame, held from the end of the rise until the shot
            const rise = reduced ? 1 : outBack((u - T.rise0) / (T.rise1 - T.rise0));
            gun.style.transform = `translate3d(0, ${((1 - rise) * geo.drop).toFixed(1)}px, 0)`;
            const patch = !reduced && u < T.rise1 + 0.2; // once the pistol is home, the plate itself shows it: the patch and the copy are hidden
            cover.style.opacity = reduced ? '0' : (1 - smooth((u - T.rise1 - 0.05) / 0.15)).toFixed(3);
            if (patch !== cls.patch) {
                cls.patch = patch;
                cover.style.visibility = patch ? '' : 'hidden';
                gun.style.visibility = patch ? '' : 'hidden';
            }
            // the red light in the eyes ignites 150 ms before the face is fully there
            const eyes = smooth((u - (T.reveal1 - 0.15)) / 0.2) * (reduced ? 1 : 0.88 + 0.12 * Math.sin(u * 7.3));
            aimEyes.style.opacity = eyes.toFixed(3);
            const ks = smooth((u - T.key0) / (T.key1 - T.key0));
            const s = lerp(0.22, 1.9, ks) + 0.7 * smooth((u - T.key1) / 1.2);
            const fx = geo.mx + (geo.afx - geo.mx) * sa;
            const fy = geo.my + (geo.afy - geo.my) * sa + dyP;
            aimKey.style.transform = `translate3d(${(fx - W / 2).toFixed(1)}px, ${(fy - H / 2).toFixed(1)}px, 0) scale(${s.toFixed(3)})`;
            aimWarm.style.opacity = (F * 0.55).toFixed(3);
            flashImg.style.opacity = (F * 0.85).toFixed(3);
            // One local flash on the muzzle (not the screen). The gun points at the lens, so it is a ring: the plate's fan and its mirror.
            const fs = (0.7 + 0.5 * F).toFixed(3);
            flashImg.style.transform = F > 0 ? `translate3d(0, ${dyP.toFixed(1)}px, 0) scale(${fs})` : '';
            flashTwin.style.opacity = (F * 0.85).toFixed(3);
            flashTwin.style.transform = F > 0 ? `translate3d(0, ${dyP.toFixed(1)}px, 0) scale(${fs}, -${fs})` : '';
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
        tapCheck(now);
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

            // Sound needs a gesture in most browsers: try now, and again on every press, key or touch until it runs.
            audio();
            wake();
            const gesture = () => {
                wake();
                cueAt = -1e9;
                tapClose();
            };
            ['pointerdown', 'pointerup', 'touchstart', 'touchend', 'click', 'keydown'].forEach((type) => window.addEventListener(type, gesture, { capture: true, passive: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (ctx && ctx.state === 'running') ctx.suspend().catch(() => {});
                    if (cue) cue.pause();
                } else {
                    wake();
                    cueAt = -1e9;
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
            cueStop();
            if (cue) cue.pause();
            if (tapEl) tapEl.remove();
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
