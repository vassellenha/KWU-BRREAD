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

// QA only (the server sets data-seek only outside production, with ?replay=1&seek=N): the cutscene is frozen at that second.
const QA = root && root.dataset.seek !== undefined ? { seek: Number(root.dataset.seek) || 0 } : null;
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
    if (QA) return; // a frozen QA frame never moves on to Kewer or ends the hold
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

function glitchLoop() {
    if (reduced || state.ended) return;

    // One burst of 240 ms every few seconds: far below three a second.
    if (!document.hidden && state.phase === 'kewer') {
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
        bar.append(el('i'), el('i'), el('i'), el('span', null, ['TERMINAL', 'root@kewer', 'nmap.exe', 'sshd', 'kewer.sh'][i % 5]));
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

// A window line wears the dark lockdown look: a pink "$" on commands, red on refusals, soft grey on the rest.
function termLine(line) {
    const cmd = line.startsWith('$ ');
    const row = el('p', cmd ? 'is-cmd' : /denied|cannot|not found|refused|failed/i.test(line) ? 'is-err' : 'is-out', cmd ? line.slice(2) : line);
    return row;
}

let lineIndex = 0;
function feedWindows() {
    if (state.ended) return;

    if (!document.hidden) {
        windows.forEach((entry, index) => {
            const next = index === 0 && lineIndex % 7 === 3 && state.ip ? `> trace ${state.ip} ... logged` : TERM_LINES[(lineIndex + index * 3) % TERM_LINES.length];
            entry.lines.push(next);
            if (entry.lines.length > 4) entry.lines.shift();
            entry.body.replaceChildren(...entry.lines.map(termLine));
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
    const openImg = $('cr-open'); // room-open without its door: the plate under the closed room
    const closedImg = $('cr-closed');
    const holeEl = $('cr-hole');
    const doorCv = $('cr-door');
    const spill = $('cr-spill');
    const doorline = $('cr-doorline');
    const bloom = $('cr-bloom');
    const fogs = $('cr-fogs');
    const dust = $('cr-dust');
    const eggWrap = $('cr-egg');
    const eggLit = $('cr-egg-lit');
    const eggGlint = $('cr-egg-glint');
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
    const aimImg = $('cr-aim-img');
    const gun = $('cr-aim-gun');
    const aimBg = $('cr-aim-bg');
    const vig = $('cr-vig');
    const aimFull = $('cr-aim-full');
    const flashImg = $('cr-flash-img');
    const flashTwin = $('cr-flash-twin');
    const crackWrap = $('cr-crackwrap');
    const crackImg = $('cr-crack-img');
    const dark = hz.querySelector('.cr-dark');
    let lineEl = null;
    let dcEl = null;
    let nosig = null;
    let dcBox = null;
    let dcTitle = null;
    let vhs = null;
    let vhsCtx = null;
    let vhsImg = null;
    let vhsW = 0;
    let vhsH = 0;
    let vhsFrame = -1;
    let vhsOn = false;
    let dcFrame = -1;
    let dcLetters = [];
    let dcRules = [];
    let dcSub = null;
    let dcKey = '';
    let greyOn = false;
    let osdTop = null;
    let osdBot = null;
    let osdTxt = '';
    let zone = { l: 0, t: 0, r: 0, b: 0 };
    let guardDirty = true;
    let guardAt = -1e9;
    let hudParts = [];
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
    const FILE = { buzz: 'fluorescent-buzz', drip: 'drip', boom: 'heavy-thud', creak: 'door-creak', hinge: 'door-creak', foot: 'footstep-wet', rustle: 'cape-rustle', sting: 'stinger', shot: 'gunshot', glass: 'glass-crack', zap: 'zap', crtoff: 'crt-off', thud: 'heavy-thud' };
    const buffers = {};
    let bus = null;

    const rnd = (a, b) => a + Math.random() * (b - a);
    const pitch = () => 1 + rnd(-0.04, 0.04); // random pitch, plus or minus 4 percent, on every play
    const rateOf = (o) => (o && o.rate) || 1; // a sound can sit higher or lower than its recording or synthesis
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
            const r = pitch() * rateOf(o);
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
        // The same hinge a little higher and shorter, so the second squeak is not a repeat of the first.
        hinge: (c, t, o) => SFX.creak(c, t, { ...o, rate: 1.22 }),
        // The latch: a small steel click as the handle goes down, then the bolt drops into its keeper.
        latch: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            hiss(c, out, t, 0.03, 'bandpass', 2600 * r, 3, 0.25, 0.001, 0.02);
            osc(c, out, 'square', 1500 * r, 700 * r, t, 0.03, 0.06, 0.001, 0.03);
            hiss(c, out, t + 0.07, 0.04, 'bandpass', 1500 * r, 2, 0.3, 0.001, 0.03);
            osc(c, out, 'sine', 180 * r, 90 * r, t + 0.07, 0.08, 0.16, 0.002, 0.08);
        },
        // A bite into a frozen sandwich: four short, dry crackles and a dull knock under them.
        crunch: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            [0, 0.045, 0.095, 0.16].forEach((d, i) => hiss(c, out, t + d, 0.05, 'bandpass', 2200 * r + i * 520, 1.4, 0.3 - 0.05 * i, 0.001, 0.04));
            hiss(c, out, t, 0.12, 'lowpass', 520, 0.7, 0.16, 0.002, 0.1);
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
        // Dead tape: a bed of hiss that swells from the cut to the power-off, mains hum under it and one slow sagging tone (wow).
        tape: (c, t, o) => {
            const out = voice(c, o);
            hiss(c, out, t, 2.1, 'bandpass', 4200, 0.5, 0.11, 0.3, 1.5);
            hiss(c, out, t, 2.1, 'highpass', 7500, 0.7, 0.05, 0.5, 1.3);
            osc(c, out, 'sine', 60, 60, t, 0.1, 0.06, 0.4, 1.5);
            osc(c, out, 'sine', 410, 300, t + 0.15, 1.6, 0.02, 0.5, 1.2);
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
        // Ringing ears after the shot: two close sines around 3.5 kHz (a slow beat between them), 1 s, falling away.
        tinnitus: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            osc(c, out, 'sine', 3520 * r, 3380 * r, t, 1.2, 0.035, 0.03, 1.1);
            osc(c, out, 'sine', 3527 * r, 3387 * r, t, 1.2, 0.02, 0.05, 1.05);
        },
        thud: (c, t, o) => {
            const out = voice(c, o);
            const r = pitch();
            osc(c, out, 'sine', 62 * r, 26 * r, t, 0.5, 0.85, 0.006, 0.6);
            hiss(c, out, t, 0.2, 'lowpass', 200, 0.7, 0.3, 0.004, 0.18);
        },
    };

    // The shot ducks every other voice and the room reverb: the reverb comes back after 300 ms, the dry voices stay low for
    // a second under the ringing in the ears (the shot's own long tail runs on its own reverb and is never ducked).
    const duckOthers = (c, t) => {
        [bus.duck, bus.wetDuck].forEach((node, i) => {
            node.gain.cancelScheduledValues(t);
            node.gain.setValueAtTime(1, t);
            node.gain.linearRampToValueAtTime(0.16, t + 0.012);
            if (i === 0) {
                node.gain.linearRampToValueAtTime(0.3, t + 0.3);
                node.gain.setValueAtTime(0.3, t + 0.9);
            }
            node.gain.linearRampToValueAtTime(1, t + (i === 0 ? 1.15 : 0.3));
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
                source.playbackRate.value = pitch() * rateOf(o);
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
        hinge: { dist: 0.5, send: 0.3, pan: -0.05, rate: 1.22 },
        latch: { dist: 0.5, send: 0.25, pan: -0.05 },
        crunch: { dist: 0.1, send: 0.15 },
        foot: { send: 0.3 },
        rustle: { dist: 0.4, send: 0.2 },
        sting: { dist: 0.15, send: 0.3 },
        click: { dist: 0.1, send: 0.22, pan: -0.1 },
        shot: { dist: 0, send: 0.35, bypass: true },
        glass: { dist: 0.05, send: 0.3, bypass: true },
        zap: { dist: 0.2, send: 0.2 },
        tape: { dist: 0.1, send: 0.04 },
        crtoff: { dist: 0.3, send: 0.15 },
        thud: { dist: 0.3, send: 0.3 },
        tinnitus: { dist: 0, send: 0.05, bypass: true },
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
    // The step response of a spring (damping .72): 0 at the start, a hair over 1 once, then still. The hand lifting the pistol.
    const stepResp = (t) => {
        if (t <= 0) return 0;
        const z = 0.72;
        const w = 12;
        const wd = w * Math.sqrt(1 - z * z);
        return 1 - Math.exp(-z * w * t) * (Math.cos(wd * t) + (z / Math.sqrt(1 - z * z)) * Math.sin(wd * t));
    };
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
        door0: 7.0, // the latch lets go and the door starts to swing (see DOOR)
        door1: 10.0, // the leaf has hit its stop and rests
        appear: 9.0, // the owner stands in the doorway, once the leaf is well past half open
        alert: 8.6,
        reveal0: 22.4, // the match cut to the aim plate starts, with a push-in and a rack focus
        reveal1: 22.8, // the face is fully there
        rise0: 23.6, // the pistol comes up from below the frame
        rise1: 24.35, // a slow, heavy lift (750 ms, a small overshoot), held on the lens: the second line is spoken over it, and the shot cuts it off
        key0: 22.6, // the key light ramps up on his face
        key1: 23.8,
        shot: 26.6,
        glitch0: 26.8, // the picture breaks: tear, red/cyan split, CAM 3 NO SIGNAL (to dc0)
        dc0: 27.6, // DISCONNECTED snaps in, with its zap, and holds still
        crt0: 28.9, // the tube loses power: scaleY to a line, scaleX to a dot, DISCONNECTED shrinks with the screen
        crt1: 29.4, // the dot is gone
        black: 29.4, // black plus one low thud, then Kewer at the end of the 30 seconds
    };
    // The barrel safe zone, as fractions of the aim plate: the slide and the muzzle (x .20 to .47, y .43 to .67) plus 20 percent.
    // Nothing the viewer has to read may enter it while the pistol is on screen (ZONE_ON), and the plate is scaled up to 1.15
    // about the muzzle by then, so the zone is grown by ZONE_SA and padded.
    const ZONE = [0.146, 0.382, 0.524, 0.718];
    const ZONE_SA = 1.16;
    const ZONE_PAD = 12;
    const ZONE_ON = T.rise0 - 0.2;
    const DC_ORDER = [5, 9, 1, 11, 3, 7, 0, 10, 4, 8, 2, 6]; // the order the letters of DISCONNECTED light up in
    const CHAR_RATE = 0.028; // the text box types one letter every 28 ms
    // The gait. A stride is a run of steps, and every step is one phase from 0 to 1 between two heel strikes. That single
    // phase drives the bob (two peaks per two steps), the shoulder roll, the weight shift and the cloak. The stride itself
    // starts and stops on an in-out cubic, so he never moves at a constant speed. Heavy and slow: about 1.1 s a step. He stops
    // once in total stillness (1.5 s), takes two more steps, and then the light dies for 120 ms: when it comes back he is a
    // whole step closer (JUMP), the one jump of the walk.
    const STRIDES = [
        { a: 9.55, b: 12.85, w: 0.4, strikes: [10.65, 11.75, 12.85] },
        { a: 14.35, b: 16.55, w: 0.45, strikes: [15.45, 16.55] },
    ];
    const JUMP = { at: 16.95, dark: 0.12, w: 0.15 }; // lights out at 16.95 for 120 ms (3 frames at 25 fps); he moves in the dark
    const HEAVY = [0.45, 0.55, 0.62, 0.8, 1.0]; // how hard each heel lands (the footsteps use the same numbers)
    const STEPS = [];
    STRIDES.forEach((st) => st.strikes.forEach((hit, i) => STEPS.push({ a: i ? st.strikes[i - 1] : st.a, b: hit })));
    const STRIKES = STRIDES.flatMap((st) => st.strikes);
    // The door, as a timeline of positions in the door atlas (frame numbers, fractional between two frames): the handle goes
    // down and the latch clicks (6.6 to 7.0), the leaf sticks for a moment, lets go, swings on an in-out cubic to the stopper
    // at 9.6, and takes two frames to rebound and rest. The room light and the fog follow the angle of the leaf.
    const DOOR = { latch0: 6.6, latch1: 7.0, stuck1: 7.35, swing1: 9.6, knockTicks: 4 };
    const SETTLE = 0.25; // seconds of settle at a mark: down 3 px, then up
    const WS = 6; // weight shift, px at his nearest size
    const BOB = 7;
    const SETTLE_PX = 3;
    const REST = { p: 1, s: 0, sp: 0, bob: 0, roll: 0, shift: 0, settle: 0, heel: 0, over: 0, shakeX: 0, shakeY: 0 };
    const P1 = [16.1, 19.6];
    const P2 = [T.rise1 + 0.1, T.shot]; // spoken with the pistol on the lens; the shot cuts the box off mid air

    // [time, sound, argument]. The sound is scheduled on the audio clock just before the time, so it lands with the timeline.
    const EVENTS = [
        [0.9, 'buzz'],
        [3.6, 'drip'],
        [T.knock, 'boom', 1],
        [6.85, 'latch'],
        [7.3, 'creak'],
        [8.55, 'hinge'],
        [9.6, 'boom', 0.9],
        [10.65, 'foot', 0.45],
        [11.2, 'rustle'],
        [11.75, 'foot', 0.55],
        [12.85, 'foot', 0.62],
        [15.45, 'foot', 0.8],
        [16.55, 'foot', 1],
        [16.57, 'thud', 0.7], // the last step lands with one heavy thud, out of 400 ms of silence
        [JUMP.at, 'buzz'], // the light dies with a crackle of its ballast
        [JUMP.at + JUMP.dark, 'sting', 0.55], // and comes back on him, a step closer
        [19.35, 'drip'],
        [21.15, 'sting', 1],
        [T.reveal0 + 0.05, 'boom', 0.6],
        [T.rise0 + 0.02, 'rustle'],
        [T.rise1, 'click'],
        [T.shot, 'shot'],
        [T.shot + 0.06, 'glass'],
        [T.shot + 0.08, 'tinnitus'],
        [T.glitch0 + 0.3, 'tape'],
        [T.dc0, 'zap'],
        [T.crt0, 'crtoff'],
        [T.crt1 + 0.05, 'thud'],
    ];

    const threatAt = (u) => {
        if (u < T.knock) return 0;
        if (u < T.alert) return lerp(0, 0.08, smooth((u - T.knock) / 1.5));
        if (u < 16) return lerp(0.08, 0.62, smooth((u - T.alert) / 7.4));
        if (u < 19.0) return lerp(0.62, 0.74, smooth((u - 16) / 3.0));
        if (u < T.reveal0) return lerp(0.74, 0.92, smooth((u - 19.0) / (T.reveal0 - 19.0)));
        return lerp(0.92, 1, smooth((u - T.reveal0) / 1.2));
    };

    const gaitAt = (u) => {
        if (reduced) return REST;
        let p = 0;
        let sp = 0;
        let settle = 0;
        let over = 0;
        if (u >= JUMP.at + JUMP.dark / 2) p += JUMP.w; // in the middle of the dark
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
                // a heavy, even tempo: mostly linear, with a little surge into each heel strike
                if (u >= st.a) { const l = (u - st.a) / (st.b - st.a); s = i + 0.7 * l + 0.3 * inOut(l); }
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
        // With the planted foot table the path position follows the steps: step k ends exactly on its heel strike, and the floor
        // each step covers is whatever keeps the planted boot still. The jump then takes him the rest of the way in the dark.
        if (phase.table) {
            const half = (wk.m.frames || WALK_CYCLE) / 2;
            p = tableFind(phase.table, s * half);
            if (u >= JUMP.at + JUMP.dark / 2) p = 1;
        }
        let heel = 0;
        let shakeX = 0;
        let shakeY = 0;
        STRIKES.forEach((hit, j) => {
            const x = u - hit;
            if (x < 0) return;
            if (x < 0.45) heel += HEAVY[j] * Math.exp(-x / 0.09);
            if (x < 0.09) {
                // Four to six frames of micro-shake on every heel strike (1.5 to 2.5 px on screen), one fixed offset per 60 Hz frame.
                const n = Math.floor(x * 60) + j * 7;
                const amp = (0.75 + 0.5 * HEAVY[j]) * Math.exp(-x / 0.04);
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
    const ASSETS = [
        { key: 'door', src: `${ART}door-atlas.webp`, from: 0, until: 10.5, bitmap: true }, // the leaf is drawn from this, so it is needed with the room
        { key: 'doorjson', src: `${ART}door-atlas.json`, from: 0, until: 10.5, json: true },
        { key: 'closed', src: `${ART}room-closed.webp`, from: 0, until: 10.5 },
        { key: 'egg', src: `${ART}egg-sandwich.webp`, from: 2.2, until: 22.9 },
        { key: 'eggLit', src: `${ART}egg-sandwich-lit.webp`, from: 2.2, until: 22.9 },
        { key: 'fog', src: `${ART}fog.webp`, from: 2.2, until: 22.9 },
        { key: 'pipes', src: `${ART}foreground-pipes.webp`, from: 2.2, until: 22.9 },
        { key: 'opn', src: `${ART}room-open-nodoor.webp`, from: 6.6, until: 22.9 },
        { key: 'spill', src: `${ART}light-spill.webp`, from: 6.6, until: 22.9 },
        { key: 'char', src: hz.dataset.figure, from: 8.0, until: 22.9 },
        // optional: the scene never waits for these; without them the standing cutout and a plain door fade are used
        { key: 'dust', src: `${ART}dust-atlas.webp`, from: 4.0, until: 11, soft: true, bitmap: true },
        { key: 'wlk', src: `${ART}walk-atlas-dark.webp`, from: 4.0, until: 22.9, soft: true, bitmap: true }, // backlit: a silhouette
        { key: 'wlkLit', src: `${ART}walk-atlas.webp`, from: 19.5, until: 22.9, soft: true, bitmap: true }, // his face, just before the reveal
        { key: 'wlkjson', src: `${ART}walk-atlas.json`, from: 4.0, until: 22.9, soft: true, json: true },
        { key: 'aim', src: `${ART}aim-pose.webp`, from: 21.6, until: 28.6 },
        { key: 'aimclean', src: `${ART}aim-clean.webp`, from: 21.6, until: 28.6 },
        { key: 'aimgun', src: `${ART}aim-gun.webp`, from: 21.6, until: 28.6 },
        { key: 'flash', src: `${ART}muzzle-flash.webp`, from: 26.0, until: 27.4 },
        { key: 'crack', src: `${ART}glass-crack.webp`, from: 26.0, until: 28.6 },
    ];
    // The server caches images for 30 days and JSON not at all, so a rebuilt atlas could meet its new JSON in a browser
    // that still holds the old picture. Every plate carries the build version, so a new build is always a new URL.
    const ART_V = '2611201';
    ASSETS.forEach((a) => { a.src += `?v=${ART_V}`; });
    const asset = (key) => ASSETS.find((a) => a.key === key);
    const needed = (a, u) => a.from <= u + 0.25 && a.until > u;
    // True when every plate the moment u needs is decoded (a plate that failed to load counts: the scene just stays dark there).
    const gate = (u) => ASSETS.every((a) => a.ready || a.soft || !needed(a, u));
    const loadAsset = (a) => new Promise((resolve) => {
        if (a.json) {
            fetch(a.src, { credentials: 'same-origin' })
                .then((res) => (res.ok ? res.json() : Promise.reject(new Error('missing'))))
                .then((data) => { a.data = data; })
                .catch(() => {})
                .then(() => { a.ready = true; resolve(); });
            return;
        }
        const img = new Image();
        img.decoding = 'async';
        img.src = a.src;
        const done = () => {
            a.img = img;
            if (img.naturalWidth) hz.querySelectorAll(`[data-asset="${a.key}"]`).forEach((n) => { n.src = a.src; });
            // A sprite atlas is decoded once into a bitmap, so drawing a frame never decodes or converts anything.
            if (a.bitmap && img.naturalWidth && window.createImageBitmap) {
                window.createImageBitmap(img).then((bmp) => { a.bmp = bmp; }).catch(() => {}).then(() => { a.ready = true; resolve(); });
                return;
            }
            a.ready = true;
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
        // The plate is shifted sideways so his face, not the middle of the photo, sits on the centre of the screen.
        const cx = W / 2 - (AIM_FACE[0] - 0.5) * aw;
        aim.style.setProperty('--ax', `${(cx - W / 2).toFixed(1)}px`);
        aim.style.setProperty('--aw', `${aw.toFixed(1)}px`);
        aim.style.setProperty('--ah', `${ah.toFixed(1)}px`);
        aim.style.setProperty('--at', `${at.toFixed(1)}px`);
        const mx = cx + (AIM_MUZZLE[0] - 0.5) * aw;
        const my = at + AIM_MUZZLE[1] * ah;
        const afx = cx + (AIM_FACE[0] - 0.5) * aw;
        const afy = at + AIM_FACE[1] * ah;
        geo = { mx, my, afx, afy, aw, ah };
        // the barrel safe zone, in screen pixels, grown for the push-in (scaled about the muzzle) and padded for the recoil
        const zx = (f) => mx + (cx + (f - 0.5) * aw - mx) * ZONE_SA;
        zone = { l: zx(ZONE[0]) - ZONE_PAD, r: zx(ZONE[2]) + ZONE_PAD, t: my + (at + ZONE[1] * ah - my) * ZONE_SA - ZONE_PAD, b: my + (at + ZONE[3] * ah - my) * ZONE_SA + ZONE_PAD };
        hz.dataset.zone = [zone.l, zone.t, zone.r, zone.b].map((v) => v.toFixed(0)).join(',');
        // the two glitch bands stay either side of it
        hz.style.setProperty('--tear-a', `${Math.max(0, zone.t - 0.036 * H - 6).toFixed(0)}px`);
        hz.style.setProperty('--tear-b', `${Math.min(H * 0.96, zone.b + 6).toFixed(0)}px`);
        sizeVhs();
        guardDirty = true;
        aim.style.transformOrigin = `${mx.toFixed(1)}px ${my.toFixed(1)}px`;
        aim.style.setProperty('--fx', `${afx.toFixed(0)}px`);
        aim.style.setProperty('--fy', `${afy.toFixed(0)}px`);
        // The eyes behind the lenses (plate fractions), where the red light ignites.
        aim.style.setProperty('--e1x', `${(cx + (0.417 - 0.5) * aw).toFixed(1)}px`);
        aim.style.setProperty('--e2x', `${(cx + (0.658 - 0.5) * aw).toFixed(1)}px`);
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

        walkMarks();
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
            const rim = smooth((px[i] - 150) / 90);
            const gain = 0.03 + 0.5 * rim;
            px[i] *= gain;
            px[i + 1] *= gain;
            px[i + 2] *= gain;
        }
        dc.putImageData(data, 0, 0);
        silhouette(shadowEl, img);
        silhouette(reflectEl, img);
        fig.ready = true;
    };

    /* ---- the real walk: a 30 frame cycle in one atlas (public/img/honeypot/walk-atlas.webp, built by
       scripts/build_honeypot_walk.py). It is drawn on a 15 fps tick ("on twos"): the frame is a function of the distance
       he has covered on screen, so a foot never slides, and it is only redrawn when the frame index changes. The sprite
       canvas holds the atlas cell at its own size and CSS scales it, so there is no per frame pixel work at all.
       Any failure (a missing file, a bad atlas) leaves wk.ok false and the standing cutout is used. ---- */

    const WALK_H = 0.62; // his height while walking, of the screen height; the aim plate takes over for the face
    const WALK_CYCLE = 30;
    const wk = { ok: false, failed: false, m: null, src: null, lit: null, litCv: null, litCtx: null, shown: '', rawA: 1, rawB: 2 };
    const stats = { fig: 0, door: 0, dust: 0, t: 0 };

    // Where the path starts and ends, in plate pixels. Used by the geometry and by the step counter, so they always agree.
    const pathNow = () => {
        const x0 = 0.49 * PW;
        const y0 = 0.708 * PH;
        const h0 = 0.3 * PH;
        const x1 = PW / 2; // he ends his walk in the middle of the frame
        const y1 = (PH - H) / 2 + 0.89 * H; // his feet stand a little above the bottom edge: the subtitle owns the last 10 percent
        const h1 = (wk.ok ? WALK_H : 0.9) * H;
        return { x0, y0, h0, x1, y1, h1 };
    };
    // The walk is driven by the planted foot. The atlas says where each boot touches down in every frame (walk-atlas.json,
    // feet), so for every path position p the frame is found that keeps the planted boot on the same spot of the floor while
    // his body moves on (and grows toward the lens). When that boot has rolled through its whole stance, the other one lands
    // and takes over. The table below is that solution, sampled once per layout; a frame is then a lookup, so a foot can
    // never slide, at any screen size. Before the atlas is known, an even count of steps over the path stands in.
    const PHASE_N = 480;
    const phase = { table: null, pa: 0.4, pb: 0.85 };
    // The boot's sole in the cell at q frames into its stance (0 = heel strike, 15 = toe off): its height, or (x) its column.
    const footAt = (foot, q, x = false) => {
        const f = wk.m.feet;
        const n = wk.m.frames || WALK_CYCLE;
        const i = Math.floor(q);
        const t = q - i;
        const at = (k) => f[((foot ? 15 : 0) + k) % n][foot * 2 + (x ? 1 : 0)];
        return lerp(at(i), at(i + 1), t);
    };
    const solvePhase = (kf) => {
        const { y0, h0, y1, h1 } = pathNow();
        const A = wk.m.anchorY;
        const half = (wk.m.frames || WALK_CYCLE) / 2;
        const table = new Float32Array(PHASE_N + 1);
        // and sideways: he grows about the planted boot, not about the middle of the sprite, so it does not slide out either
        const side = new Float32Array(PHASE_N + 1);
        const AX = wk.m.anchorX;
        let foot = 0;
        let base = 0;
        let plant = y0 - (A - footAt(0, 0)) * (h0 / A);
        let plantX = (footAt(0, 0, true) - AX) * (h0 / A);
        let q = 0;
        for (let i = 0; i <= PHASE_N; i += 1) {
            const pp = i / PHASE_N;
            const ya = lerp(y0, y1, pp);
            const hh = lerp(h0, h1, pp);
            // how far (cell px) the planted sole must have moved up the cell to stay where it touched down
            const need = kf * (footAt(foot, 0) - (A - ((ya - plant) * A) / hh));
            const span = footAt(foot, 0) - footAt(foot, half);
            if (need >= span) {
                // toe off: the other boot lands here and becomes the planted one
                const sx = i ? side[i - 1] : 0;
                base += half;
                foot = 1 - foot;
                plant = ya - (A - footAt(foot, 0)) * (hh / A);
                plantX = sx + (footAt(foot, 0, true) - AX) * (hh / A);
                q = 0;
            } else {
                while (q < half && footAt(foot, 0) - footAt(foot, q + 0.05) <= need) q += 0.05;
            }
            table[i] = base + q;
            side[i] = plantX - (footAt(foot, q, true) - AX) * (hh / A);
        }
        table.side = side;
        return table;
    };
    const tableAt = (table, pp) => {
        const x = clamp(pp) * PHASE_N;
        const i = Math.min(PHASE_N - 1, Math.floor(x));
        return lerp(table[i], table[i + 1], x - i);
    };
    const tableFind = (table, target) => {
        for (let i = 0; i < PHASE_N; i += 1) {
            if (table[i + 1] >= target) return (i + clamp((target - table[i]) / Math.max(1e-6, table[i + 1] - table[i]))) / PHASE_N;
        }
        return 1;
    };
    // Three steps, a stop, two steps, then the step in the dark. The planted foot fixes how far a step goes, so the floor the
    // five steps cover depends on the screen; the jump takes whatever is left, ideally one step. A slight scale of the foot
    // travel (kf, 0.8 to 1.3, so a slip of at most a few px per step) keeps that jump between half a step and two steps.
    const buildPhase = () => {
        if (!wk.ok || !wk.m.feet || !PW) return;
        const frames = wk.m.frames || WALK_CYCLE;
        const want = 3 * frames; // 1.5 cycles + 1 cycle walked, half a cycle in the dark
        let lo = 0.8;
        let hi = 1.3;
        let table = solvePhase(1);
        if (table[PHASE_N] < want - 0.5 * frames / 2 || table[PHASE_N] > want + frames / 2) {
            for (let i = 0; i < 16; i += 1) {
                const mid = (lo + hi) / 2;
                table = solvePhase(mid);
                if (table[PHASE_N] < want) lo = mid;
                else hi = mid;
            }
        }
        const pa = tableFind(table, 1.5 * frames);
        const pb = Math.min(0.94, tableFind(table, 2.5 * frames));
        phase.table = table;
        phase.side = table.side;
        phase.kf = lo === 0.8 && hi === 1.3 ? 1 : (lo + hi) / 2;
        phase.pa = pa;
        phase.pb = pb;
        STRIDES[0].w = pa;
        STRIDES[1].w = Math.max(0.02, pb - pa);
        JUMP.w = Math.max(0, 1 - pa - STRIDES[1].w);
    };
    // The position in the cycle, in cycles. Every stop lands on a foot contact (1.5 cycles, 2.5 cycles, then 3 after the jump).
    const walkCycles = (pp) => {
        const frames = wk.m && wk.m.frames ? wk.m.frames : WALK_CYCLE;
        if (!phase.table) return 3 * clamp(pp);
        const pb = phase.pb;
        if (pp <= pb) return Math.min(2.5, tableAt(phase.table, pp) / frames);
        return 2.5 + (0.5 * (pp - pb)) / Math.max(1e-3, 1 - pb);
    };
    // The position in the cycle as a frame number with its fraction: the sprite crossfades frame i into frame i + 1 by it.
    const walkPos = (pp) => {
        const f = walkCycles(pp) * WALK_CYCLE + 1e-4;
        return ((f % WALK_CYCLE) + WALK_CYCLE) % WALK_CYCLE;
    };
    // The heel strikes in the middle of each stride follow the walk instead of a fixed time: find where the planted foot
    // changes, and move its footstep (and its micro shake) there, so the sound lands on the boot that lands.
    const FOOT_AT = EVENTS.map((e, i) => (e[1] === 'foot' ? i : -1)).filter((i) => i >= 0);
    const walkMarks = () => {
        if (reduced || !PW) return;
        buildPhase();
        if (phase.table) return; // the steps now land exactly on their beats (STRIKES), the floor follows the planted boot
        const when = (st, target) => {
            let lo = st.a;
            let hi = st.b;
            for (let i = 0; i < 22; i += 1) {
                const mid = (lo + hi) / 2;
                if (walkCycles(gaitAt(mid).p) < target) lo = mid;
                else hi = mid;
            }
            return (lo + hi) / 2;
        };
        [[0, STRIDES[0], 0.5], [1, STRIDES[0], 1.0], [3, STRIDES[1], 2.0]].forEach(([n, st, target]) => {
            const at = when(st, target);
            if (FOOT_AT[n] !== undefined && !fired.has(FOOT_AT[n])) EVENTS[FOOT_AT[n]][0] = at;
            STRIKES[n] = at;
        });
    };

    // Adopt the atlas: every size that depended on the standing cutout follows the cell (same nominal height, so the
    // geometry, the camera and the shadow keep their numbers). The pixel store is the cell itself, CSS does the scaling.
    const adoptAtlas = () => {
        const a = asset('wlk');
        if (wk.ok || wk.failed || !fig.ready || !a.ready) return;
        const src = a.bmp || (a.img && a.img.naturalWidth ? a.img : null);
        if (!src) {
            wk.failed = true;
            return;
        }
        const m = { cellW: 296, cellH: 408, anchorX: 148, anchorY: 390, cols: 6, frames: 30, ...((asset('wlkjson') || {}).data || {}) };
        // The cells must tile the picture exactly. If they do not (an old picture with a new JSON, or the reverse), the grid
        // is taken from the picture itself and the anchor moves with it, so a frame never shows a piece of its neighbour.
        const rows = Math.ceil(m.frames / m.cols);
        const sw = src.width || src.naturalWidth;
        const shh = src.height || src.naturalHeight;
        if (sw !== m.cellW * m.cols || shh !== m.cellH * rows) {
            const cw = Math.floor(sw / m.cols);
            const chh = Math.floor(shh / rows);
            m.anchorX = Math.round((m.anchorX / m.cellW) * cw);
            m.anchorY = Math.min(chh, Math.round((m.anchorY / m.cellH) * chh));
            m.cellW = cw;
            m.cellH = chh;
        }
        const scale = fig.h / m.anchorY;
        fig.cw = Math.round(m.cellW * scale);
        [figCanvas, shadowEl, reflectEl].forEach((cv) => {
            cv.width = m.cellW;
            cv.height = m.anchorY;
            cv.style.width = `${fig.cw}px`;
            cv.style.height = `${fig.h}px`;
        });
        fig.ctx = figCanvas.getContext('2d');
        fig.face = [fig.cw / 2 - PAD, 0.095 * fig.h]; // the eyes: the camera pushes in on this point
        fig.glints = [];
        wk.m = m;
        wk.src = src;
        wk.shown = '';
        wk.ok = true;
        walkMarks();
    };
    // The lit atlas (same grid) is only needed for the last 600 ms of the walk; until it is decoded he simply stays backlit.
    const adoptLit = () => {
        const a = asset('wlkLit');
        if (wk.lit || !wk.ok || !a.ready) return;
        const src = a.bmp || (a.img && a.img.naturalWidth ? a.img : null);
        if (!src || (src.width || src.naturalWidth) !== wk.m.cellW * wk.m.cols) return;
        wk.litCv = document.createElement('canvas');
        wk.litCv.width = wk.m.cellW;
        wk.litCv.height = wk.m.anchorY;
        wk.litCtx = wk.litCv.getContext('2d');
        wk.lit = src;
        wk.shown = '';
    };

    // Draws one moment of the cycle into a context: frame i and frame i + 1 crossfaded by the fraction (premultiplied add, so
    // the blend of two poses never goes thin), from the backlit atlas, with the lit atlas over it by `lit` (0 to 1).
    const blendCells = (c, src, pos) => {
        const m = wk.m;
        const i = Math.floor(pos) % WALK_CYCLE;
        const j = (i + 1) % WALK_CYCLE;
        const f = pos - Math.floor(pos);
        const cell = (n) => [(n % m.cols) * m.cellW, Math.floor(n / m.cols) * m.cellH];
        const [ax, ay] = cell(i);
        c.globalAlpha = 1 - f;
        c.drawImage(src, ax, ay, m.cellW, m.anchorY, 0, 0, m.cellW, m.anchorY);
        if (f > 0.004) {
            const [bx, by] = cell(j);
            c.globalCompositeOperation = 'lighter';
            c.globalAlpha = f;
            c.drawImage(src, bx, by, m.cellW, m.anchorY, 0, 0, m.cellW, m.anchorY);
        }
        c.globalCompositeOperation = 'source-over';
        c.globalAlpha = 1;
    };
    const drawAtlas = (pos, lit) => {
        const m = wk.m;
        const c = fig.ctx;
        c.clearRect(0, 0, m.cellW, m.anchorY);
        blendCells(c, wk.src, pos);
        if (lit > 0.004 && wk.lit) {
            // the lit pass is drawn on its own layer first, so the dark one shows through it in proportion
            const lc = wk.litCtx;
            lc.clearRect(0, 0, m.cellW, m.anchorY);
            blendCells(lc, wk.lit, pos);
            c.globalAlpha = lit;
            c.drawImage(wk.litCv, 0, 0);
            c.globalAlpha = 1;
        }
        // the black copy for the cast shadow and the reflection is the same blend, filled black
        [shadowEl, reflectEl].forEach((cv) => {
            const sc = cv.getContext('2d');
            sc.clearRect(0, 0, cv.width, cv.height);
            sc.drawImage(figCanvas, 0, 0);
            sc.globalCompositeOperation = 'source-in';
            sc.fillStyle = '#000';
            sc.fillRect(0, 0, cv.width, cv.height);
            sc.globalCompositeOperation = 'source-over';
        });
        stats.fig += 1;
    };

    // Where the cutout stands, from the gait: path position on an in-out cubic, plus the bob, the weight shift, the settle
    // at a mark and the small scale rise at each heel strike. Offsets are px at his nearest size and shrink with distance.
    const figGeom = (gait) => {
        const p = gait.p;
        const { x0, y0, h0, x1, y1, h1 } = pathNow();
        const hgt = lerp(h0, h1, p);
        const near = wk.ok ? 0 : hgt / h1; // the atlas has its own bob and sway, and anything added here would move the planted boot
        return {
            x: lerp(x0, x1, p) + gait.shift * WS * near + (phase.side && p < 0.999 ? tableAt(phase.side, Math.min(p, phase.pb)) * (p > phase.pb ? (1 - p) / Math.max(1e-3, 1 - phase.pb) : 1) : 0),
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

    const drawFigure = (t, u, D, body, gait) => {
        if (!fig.ready) return;
        if (wk.ok) {
            // The lit atlas comes up over the backlit one only in the last 600 ms before the reveal (the key light of the aim plate).
            const litA = wk.lit ? smooth((u - (T.reveal0 - 0.6)) / 0.6) : 0;
            const pos = walkPos(gait.p);
            const key = `${Math.round(pos * 32)}|${Math.round(litA * 24)}`;
            if (key !== wk.shown) {
                wk.shown = key;
                drawAtlas(pos, litA);
            }
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

    /* ---- the dialogue: an "audio intercept" subtitle blended into the camera picture, revealed word by word on the clock ---- */

    // A horror game text box: a name plate, the line typed letter by letter on the clock, a blinking arrow once it is done.
    const PARTS = [
        { at: P1[0], to: P1[1], words: [['Mana'], ['Mungkin'], ['Seorang'], ['"Vassell', true], ['Enha"', true]] },
        { at: P2[0], to: P2[1], words: 'Membiarkan Amatiran Seperti Anda Memasuki Panel Admin.'.split(' ').map((w) => [w]) },
    ];
    let shownPart = -1;
    let chars = []; // the letter spans of the part on screen
    let outAt = -1;
    let typedNow = -1;
    const buildPart = (index, instant) => {
        dlgText.textContent = '';
        chars = [];
        outAt = -1;
        typedNow = -1;
        dlg.classList.remove('out', 'done');
        dlg.classList.toggle('instant', instant);
        if (index < 0) return;
        PARTS[index].words.forEach(([word, hl], i) => {
            if (i > 0) dlgText.append(document.createTextNode(' '));
            const w = el('span', hl ? 'cr-w hl' : 'cr-w');
            Array.from(word).forEach((ch) => {
                const node = el('span', 'cr-c', ch);
                w.append(node);
                chars.push(node);
            });
            dlgText.append(w);
        });
    };
    const dialogue = (u, late) => {
        let part = -1;
        PARTS.forEach((p, i) => { if (u >= p.at && u < p.to) part = i; });
        // The shot takes the box with it, with no fade: from the shot on there is no text box at all.
        const hush = u >= T.shot;
        if (hush !== cls.dlgGone) {
            cls.dlgGone = hush;
            dlg.style.visibility = hush ? 'hidden' : '';
        }
        if (part !== shownPart) {
            const leaving = shownPart >= 0 && part < 0 && !late && !hush;
            shownPart = part;
            if (leaving) {
                outAt = u;
                dlg.classList.add('out');
            } else {
                buildPart(part, late || hush);
                guardDirty = true; // place the new box in its slot on this same frame, never one frame late
            }
        }
        if (outAt >= 0 && (part >= 0 || u > outAt + 0.5)) buildPart(part, late);
        flag('on', part >= 0, dlg);
        if (part < 0) return false;
        const p = PARTS[part];
        const typed = Math.min(chars.length, Math.floor((u - p.at) / CHAR_RATE) + 1);
        if (typed !== typedNow) {
            typedNow = typed;
            chars.forEach((c, i) => c.classList.toggle('on', i < typed));
            dlg.classList.toggle('done', typed >= chars.length);
        }
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
        lineEl = el('div', 'cr-crtline cr-keep');
        // DISCONNECTED lives inside the screen, so it shrinks with the tube; its slot is above the barrel safe zone (fitDc).
        // A thriller title card: two thin red rules that draw out from the centre, the word in a tall engraved serif whose letters
        // light up out of order.
        dcTitle = el('b', 'cr-dc-t');
        dcLetters = Array.from('DISCONNECTED').map((ch) => {
            const node = el('span', 'cr-dc-l', ch);
            dcTitle.append(node);
            return node;
        });
        const head = el('div', 'cr-dc-head');
        head.append(dcTitle);
        dcRules = [el('i', 'cr-dc-rule'), el('i', 'cr-dc-rule')];
        dcSub = el('small', 'cr-dc-s', 'CAM 3 / COLD ROOM 03 / SIGNAL LOST');
        dcBox = el('div', 'cr-dc-box');
        dcBox.append(dcRules[0], head, dcRules[1], dcSub);
        dcEl = el('div', 'cr-dc');
        dcEl.setAttribute('aria-hidden', 'true');
        dcEl.append(dcBox);
        nosig = el('div', 'cr-nosig', 'CAM 3  NO SIGNAL');
        // Analog horror: a low resolution canvas for the snow, the tracking band and the dropouts, and a tape OSD that outlives the HUD.
        vhs = el('canvas', 'cr-vhs');
        vhs.setAttribute('aria-hidden', 'true');
        osdTop = el('div', 'cr-osd cr-osd-t');
        osdBot = el('div', 'cr-osd cr-osd-b', 'SP  CH 03');
        screen.append(vhs, osdTop, osdBot, nosig, dcEl);
        sizeVhs();
        hz.append(lineEl);
        hudParts = Array.from(hz.querySelectorAll('.cr-hud-top, .cr-side, .cr-alert, .cr-bl, .cr-map, .cr-br, .cr-osd'));
    };

    // The muzzle flash: one event, 2 to 3 frames, low peak, local to the face and the haze. Reduced motion gets a soft ramp.
    const flashAt = (u) => {
        const x = u - T.shot;
        if (x < 0) return 0;
        if (reduced) return x < 0.2 ? 0.4 * smooth(x / 0.2) : 0.4 * Math.max(0, 1 - (x - 0.2) / 0.5);
        const v = x < 0.016 ? x / 0.016 : Math.exp(-(x - 0.016) / 0.05);
        return v < 0.02 ? 0 : v;
    };
    // The glitch beat (glitch0 to dc0, 800 ms): two tear bursts and a flickering CAM 3 NO SIGNAL, so at most 3 bright flashes a second.
    const tearOn = (u) => !reduced && ((u >= T.glitch0 + 0.12 && u < T.glitch0 + 0.2) || (u >= T.glitch0 + 0.5 && u < T.glitch0 + 0.62));
    const sigOn = (u) => (reduced ? u >= T.glitch0 + 0.1 && u < T.dc0 : (u >= T.glitch0 + 0.05 && u < T.glitch0 + 0.3) || (u >= T.glitch0 + 0.4 && u < T.glitch0 + 0.7) || (u >= T.glitch0 + 0.74 && u < T.dc0));
    // ---- Analog horror, 26.8 to 29.4. Everything is a pure function of u (the noise is a hash of position and frame, never
    // Math.random), redrawn at 15 fps on a canvas a quarter of the screen size (a sixth on small or slow devices).
    const h2 = (a, b, c) => {
        let x = Math.imul(a | 0, 374761393) + Math.imul(b | 0, 668265263) + Math.imul(c | 0, 2147483647);
        x = Math.imul(x ^ (x >>> 13), 1274126177);
        return ((x ^ (x >>> 16)) >>> 0) / 4294967296;
    };
    const sizeVhs = () => {
        if (!vhs) return;
        const q = lite ? 6 : 4;
        vhsW = Math.max(40, Math.ceil(W / q));
        vhsH = Math.max(40, Math.ceil(H / q));
        vhs.width = vhsW;
        vhs.height = vhsH;
        vhsCtx = vhs.getContext('2d');
        vhsImg = vhsCtx ? vhsCtx.createImageData(vhsW, vhsH) : null;
        vhsFrame = -1;
    };
    // How much snow: a soft rise out of the tear, a dead signal that breathes, a swell as the tube loses power.
    const vhsI = (u) => {
        if (u < T.glitch0 || u >= T.crt0 + 0.35) return 0;
        const rise = smooth((u - T.glitch0) / 0.3);
        const base = u < T.dc0 ? 0.16 + 0.14 * ((u - T.glitch0) / 0.8) : 0.36 + 0.08 * Math.sin(u * 3.1);
        const burst = tearOn(u) ? 0.3 : 0;
        const swell = 0.25 * smooth((u - (T.crt0 - 0.3)) / 0.3);
        return clamp(rise * (base + burst) + swell) * (1 - smooth((u - T.crt0 - 0.05) / 0.3));
    };
    // The face loses the picture and finds it again: a hard cut every 300 ms (about 1.7 a second), never fully black so the pistol stays readable.
    const lockAt = (u) => {
        if (reduced || u < T.dc0 || u >= T.crt0) return 1;
        const w = Math.floor((u - T.dc0) / 0.3);
        return [0.4, 0.95, 0.35, 1, 0.6][w] ?? 1;
    };
    const vhsDraw = (u, I) => {
        const f = Math.floor(u * 15);
        if (f === vhsFrame || !vhsImg) return;
        vhsFrame = f;
        const w = vhsW;
        const h = vhsH;
        const d = vhsImg.data;
        const zl = (zone.l / W) * w;
        const zr = (zone.r / W) * w;
        const zt = (zone.t / H) * h;
        const zb = (zone.b / H) * h;
        const bandC = h * (1.15 - ((u * 0.27) % 1.3)); // the tracking band climbs the tube
        const bandH = h * 0.11;
        const guard = u >= ZONE_ON;
        let i = 0;
        for (let y = 0; y < h; y += 1) {
            const bk = Math.max(0, 1 - Math.abs(y - bandC) / bandH);
            const smear = 5 + Math.floor(h2(y, f, 3) * 22);
            const head = y > h * 0.955 ? 1 : 0;
            for (let x = 0; x < w; x += 1) {
                let a;
                if (bk > 0) a = I * (0.3 + 0.7 * bk) * (0.35 + 0.65 * h2((x / smear) | 0, y, f + 91));
                else {
                    const n = h2(x, y, f);
                    a = I * 0.55 * n * n;
                }
                if (head) a = Math.max(a, I * 0.9 * h2((x / 9) | 0, y, f + 17));
                if (guard && x > zl && x < zr && y > zt && y < zb) a *= 0.22;
                const k = h2(x, y, 7) < 0.5 ? 0 : 1;
                d[i] = 232 + k * 14;
                d[i + 1] = 240;
                d[i + 2] = 255 - k * 20;
                d[i + 3] = Math.min(255, a * 255);
                i += 4;
            }
        }
        // dropouts: short bright streaks, a few per frame, only when the signal is already bad
        if (I > 0.25) {
            for (let k = 0; k < 5; k += 1) {
                if (h2(k, f, 12) > 0.6) continue;
                const y = Math.floor(h2(k, f, 9) * h);
                const x0 = Math.floor(h2(k, f, 10) * w * 0.8);
                const len = Math.floor(8 + h2(k, f, 11) * w * 0.35);
                for (let x = x0; x < Math.min(w, x0 + len); x += 1) {
                    if (guard && x > zl && x < zr && y > zt && y < zb) continue;
                    const j = (y * w + x) * 4;
                    d[j] = 255; d[j + 1] = 255; d[j + 2] = 255; d[j + 3] = Math.min(230, 150 + I * 160);
                }
            }
        }
        vhsCtx.putImageData(vhsImg, 0, 0);
    };
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
            const tt = u - (T.glitch0 + 0.05);
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
        flag('hud-off', u >= T.glitch0 && (u >= T.glitch0 + 0.3 || (Math.floor((u - T.glitch0) / 0.06) * 7) % 5 < 3));
        flag('sig', sigOn(u), nosig);
        flag('is-tear', tearOn(u));
        const vI = reduced ? 0 : vhsI(u);
        if (vhs) {
            if (vI > 0.001) {
                vhsDraw(u, vI);
                if (!vhsOn) { vhsOn = true; vhs.style.opacity = '1'; }
            } else if (vhsOn) {
                vhsOn = false;
                vhs.style.opacity = '0';
            }
        }
        // the tape OSD outlives the HUD: PLAY while the picture tears, TRACKING (blinking) once the signal is gone
        if (osdTop) {
            const osdLive = u >= T.glitch0 + 0.3 && u < T.crt0 + 0.1;
            const txt = u < T.dc0 ? 'PLAY' : 'TRACKING';
            if (txt !== osdTxt) {
                osdTxt = txt;
                osdTop.textContent = txt === 'PLAY' ? '\u25B6 PLAY' : 'TRACKING';
            }
            const blink = txt === 'TRACKING' && !reduced ? Math.floor(u * 2.5) % 2 === 0 : true;
            osdTop.classList.toggle('on', osdLive && blink);
            osdBot.classList.toggle('on', osdLive);
        }

        // ---- picture slip: jitter, then one vertical roll, a little less than 1.4 s of unrest
        let sx = 0;
        let ry = 0;
        if (!reduced) {
            if (u >= T.glitch0 + 0.05 && u < T.glitch0 + 0.45) sx = (((Math.floor(u * 28) * 37) % 23) - 11) * 1.6;
            if (u >= T.glitch0 + 0.45 && u < T.glitch0 + 0.7) ry = H * 0.2 * Math.sin((Math.PI * (u - T.glitch0 - 0.45)) / 0.25);
        }
        // The camera takes the recoil a hair after the plate: up 6 px, then back on the same spring.
        const kick = !reduced && x > 0.02 && x < 0.7 ? -6 * spring(x - 0.02) : 0;
        const shift = `translate3d(${(sx + kick * 0.4).toFixed(1)}px, ${(ry + kick).toFixed(1)}px, 0)`;

        // ---- DISCONNECTED, a thriller title card. The picture behind it freezes into a cold grey (only the red of his eyes
        // survives), two red rules draw out from the centre, the letters light up out of order with one flicker each, then hold
        // and now and then one dies for a frame. Reduced motion: the card fades in over 200 ms and holds, no flicker.
        const dx = u - T.dc0;
        const grey = u >= T.dc0;
        if (grey !== greyOn) {
            greyOn = grey;
            [aimImg, aimFull, gun].forEach((n) => { n.style.filter = grey ? 'grayscale(0.9) brightness(0.5) contrast(1.35)' : ''; });
        }
        let dcOp = 0;
        let key = 'off';
        if (dx >= 0) {
            dcOp = reduced ? smooth(dx / 0.2) : 1;
            const f = Math.floor(u * 15);
            const lit = dcLetters.map((n, i) => {
                if (reduced) return 1;
                const at = 0.03 + DC_ORDER[i] * 0.034;
                if (dx < at) return 0;
                if (dx < at + 0.06) return 1;
                if (dx < at + 0.1) return 0.15;
                if (dx > 0.6 && h2(f, i, 31) < 0.018) return 0.25; // a letter dies for a frame
                return 1;
            });
            const draw = reduced ? 1 : outCubic(dx / 0.45);
            key = `${lit.join(',')}|${Math.round(draw * 20)}|${reduced ? 1 : Math.round(smooth((dx - 0.4) / 0.25) * 10)}`;
            if (key !== dcKey) {
                dcKey = key;
                dcLetters.forEach((n, i) => { n.style.opacity = String(lit[i]); });
                dcRules.forEach((r) => { r.style.transform = `scaleX(${(Math.round(draw * 20) / 20).toFixed(2)})`; });
                dcSub.style.opacity = reduced ? '1' : (Math.round(smooth((dx - 0.4) / 0.25) * 10) / 10).toFixed(1);
            }
        }
        dcEl.style.opacity = dcOp.toFixed(2);
        // a breath of drift after the letters are in: 1 px at most, so the card feels watched, not shaken
        dcBox.style.transform = dcOp && !reduced && dx > 0.5 ? `translate3d(${Math.round(Math.sin(u * 3.1))}px, ${Math.round(Math.sin(u * 2.3 + 1))}px, 0)` : '';
        dcEl.style.setProperty('--dcs', `${reduced ? 1 : 1.5 + Math.abs(Math.sin(u * 4.1))}px`);

        // ---- the CRT power-off: scaleY to a line, scaleX to a dot, a soft glow, then nothing. DISCONNECTED is a child of the
        // screen and goes with it. Reduced motion: a 300 ms fade to black instead.
        const off = u - T.crt0;
        if (off > 0 && !reduced) {
            const s1 = inOut(off / 0.28);
            const s2 = inOut((off - 0.24) / 0.26);
            screen.style.transform = `${shift} scale(${(1 - 0.99 * s2).toFixed(4)}, ${(1 - 0.994 * s1).toFixed(4)})`;
            const lineOp = 0.85 * smooth((off - 0.12) / 0.1) * (1 - smooth((off - 0.36) / 0.14));
            lineEl.style.opacity = lineOp.toFixed(3);
            lineEl.style.transform = `translateY(-50%) scale(${(1 - 0.985 * s2).toFixed(4)}, ${(1 + 2.4 * s2).toFixed(3)})`;
            screen.style.opacity = '1';
        } else {
            screen.style.transform = shift;
            lineEl.style.opacity = '0';
            screen.style.opacity = reduced && off > 0 ? (1 - smooth(off / 0.3)).toFixed(3) : '1';
        }
        flag('b-dead', u >= T.crt0);
    };

    /* ---- the Suno cue: decoded once, played once through the master bus, locked to the timeline.
       The file is 29.8 s and the shot is at 26.6 s, so it is placed, not stretched: its loudest bar (CUE_PEAK, measured)
       sits on the start of the face reveal (T.reveal0), which makes it start 1.0 s into the file at u = 0 and cut hard at
       the shot (a sample-accurate stop() scheduled when it starts). The gain follows the scene: silent until the door
       opens, a slow swell as he appears, strongest on the approach, a climax from the reveal, a 300 ms gap, the cut.
       A reload at second N starts it at the matching file position. If the decode or the context fails, a plain Audio
       element plays the same file with the same envelope. ---- */

    const CUE = '/audio/honeypot-cue.mp3';
    const CUE_PEAK = 25.0; // second of the file where the track peaks, landed on T.reveal0 (so the file starts 2.6 s in)
    const CUE_GAIN = 0.55; // pre-bus gain at the climax: about -8 dBFS peak and -16 dBFS RMS after the bus
    const CUE_DUCK = dB(-4); // under dialogue and sound effects, only 4 dB
    const CUE_GAP = 0.4; // the one silence, right before the shot: the held breath
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
        // 400 ms of silence before his last step lands (the thud then comes out of nothing)
        const last = STRIDES[1].b;
        if (u >= last - 0.4 && u < last + 0.25) want *= 0.12;
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
    // The door and the dust are drawn from one atlas each onto one canvas, on the same 15 fps tick as the walk.
    const dr = { ok: false, failed: false, m: null, src: null, ctx: null, key: '' };
    const adoptDoor = () => {
        if (dr.ok || dr.failed) return;
        const a = asset('door');
        const j = asset('doorjson');
        if (!a.ready || !j.ready) return;
        const src = a.bmp || (a.img && a.img.naturalWidth ? a.img : null);
        if (!src || !j.data || !j.data.frames) {
            dr.failed = true; // no door atlas: the door simply fades with the room, as a plain crossfade
            return;
        }
        dr.m = j.data;
        dr.src = src;
        doorCv.width = j.data.frameW;
        doorCv.height = j.data.frameH;
        dr.ctx = doorCv.getContext('2d');
        dr.ok = true;
    };
    // Position in the atlas (a frame number, fractional between two frames) at time u.
    const doorPos = (u) => {
        if (u < DOOR.latch0) return 0;
        if (u < DOOR.latch1) return 3 * smooth((u - DOOR.latch0) / (DOOR.latch1 - DOOR.latch0));
        if (u < DOOR.stuck1) return 3 + 2 * ((u - DOOR.latch1) / (DOOR.stuck1 - DOOR.latch1));
        if (u < DOOR.swing1) return 5 + 10 * inOut((u - DOOR.stuck1) / (DOOR.swing1 - DOOR.stuck1));
        const x = clamp((u - DOOR.swing1) / (T.door1 - DOOR.swing1)); // the hit, one frame of rebound, the rest
        return x < 0.375 ? 15 + x / 0.375 : 16 + (x - 0.375) / 0.625;
    };
    const doorAngle = (pos) => {
        const table = dr.m ? dr.m.angleAt : null;
        if (!table) return (pos / 17) * 121;
        const i = Math.min(table.length - 2, Math.floor(pos));
        return lerp(table[i], table[i + 1], clamp(pos - i));
    };
    const drawDoor = (a, b, f, shake, puffs) => {
        const m = dr.m;
        const c = dr.ctx;
        const at = (n) => [(n % m.cols) * m.frameW, Math.floor(n / m.cols) * m.frameH];
        c.globalCompositeOperation = 'source-over';
        c.clearRect(0, 0, m.frameW, m.frameH);
        const [ax, ay] = at(a);
        c.globalAlpha = f > 0.001 ? 1 - f : 1;
        c.drawImage(dr.src, ax, ay, m.frameW, m.frameH, shake, 0, m.frameW, m.frameH);
        if (f > 0.001) {
            const [bx, by] = at(b);
            c.globalCompositeOperation = 'lighter'; // premultiplied add: a crossfade between two poses never goes thin
            c.globalAlpha = f;
            c.drawImage(dr.src, bx, by, m.frameW, m.frameH, shake, 0, m.frameW, m.frameH);
            c.globalCompositeOperation = 'source-over';
        }
        const d = asset('dust');
        const dsrc = d && (d.bmp || (d.img && d.img.naturalWidth ? d.img : null));
        puffs.forEach(([n, cx, cy, size, alpha]) => {
            if (!dsrc || n < 0 || n >= 8) return;
            const cell = (m.dust && m.dust.cellW) || 128;
            const cols = (m.dust && m.dust.cols) || 4;
            c.globalAlpha = alpha * (1 - 0.15 * n / 7);
            c.drawImage(dsrc, (n % cols) * cell, Math.floor(n / cols) * cell, cell, cell, (cx - m.offsetX) * m.scale - size * 0.5, (cy - m.offsetY) * m.scale - size * 0.62, size, size);
        });
        c.globalAlpha = 1;
        stats.door += 1;
    };

    let lastTick = -1;
    const frame = () => {
        raf = window.requestAnimationFrame(frame);
        if (document.hidden) return; // nothing runs while the tab is hidden; the clock is the server's
        const now = performance.now();
        if (now - stats.t >= 1000) {
            window.__hpStats = { fig: stats.fig, door: stats.door, fps: lite || degraded || reduced ? 10 : 15 };
            if (import.meta.env && import.meta.env.DEV && stats.t) console.log('[honeypot] sprite redraws per second', window.__hpStats);
            stats.fig = 0;
            stats.door = 0;
            stats.t = now;
        }
        const t = QA ? QA.seek * k : (Date.now() - state.start) / 1000;
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
        if (QA) {
            if (gate(target)) vis = target; // QA: exactly the asked second, once its plates are decoded
        } else if (Number.isNaN(vis)) {
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
        else if (!wk.ok && !wk.failed && u > 2.5) adoptAtlas();
        else if (wk.ok && !wk.lit) adoptLit();
        adoptDoor();
        // The sprite tick: 15 per second, 10 on a weak device or with reduced motion. Nothing sprite related draws between ticks.
        const tick = Math.floor(now / (1000 / (lite || degraded || reduced ? 10 : 15)));
        const ticked = tick !== lastTick;
        lastTick = tick;

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
        // The door: its position in the atlas is a pure function of u, so a seek lands on the right pose at once.
        // The angle of the leaf drives the light that comes through (the room crossfade, the spill, the fog).
        const wFade = reduced ? smooth((u - T.door0) / 0.4) : 0; // reduced motion: the leaf is simply there, after a 400 ms fade
        const pos = reduced ? 0 : doorPos(u);
        const ang = reduced ? 121 * wFade : doorAngle(pos);
        const knockN = Math.floor((ut - T.knock * k) * 15);
        const knocking = !reduced && knockN >= 0 && knockN < DOOR.knockTicks;
        const door = smooth((ang - 4) / 60);
        const D = smooth((ang - 2) / 110);
        const walk = gaitAt(u);
        const p = walk.p;
        // One slow dolly, built from two overlapping eases so it never stops: a gentle push over the room, then the long push
        // toward his face that is still moving when the crossfade starts, plus a little extra through the fade.
        const cam1 = inOut((u - 7.5) / 12.5);
        const cam2 = clamp(inOut((u - 16.4) / 8) / 0.9375);
        const S = Math.exp(Math.log(1.12) * cam1 + Math.log(2.7 / 1.12) * cam2 + Math.log(1.1) * smooth((u - 22.0) / 0.85));

        if (roomOn) {
            // The cage bulb: it dips dark for a moment on every second step, and dies for 120 ms for the jump (dark, never a bright flash).
            let dip = 0;
            if (!reduced) [1, 3].forEach((n) => { dip = Math.max(dip, 0.5 * pulse(u, STRIKES[n] + 0.04, 0.03, 0.16)); });
            const out = !reduced && u >= JUMP.at && u < JUMP.at + JUMP.dark ? 0.97 : 0;
            room.style.opacity = (bootOp * (1 - cut) * (1 - Math.max(dip, out))).toFixed(3);
            // the closed plate has a hole where its leaf is: the leaf comes from the atlas for as long as it moves
            const holeOn = dr.ok && (reduced ? u >= T.door0 : pos > 0 || knocking);
            if (holeOn !== cls.hole) {
                cls.hole = holeOn;
                closedImg.classList.toggle('has-hole', holeOn);
                doorCv.style.visibility = holeOn ? 'visible' : 'hidden';
                dr.key = '';
            }
            closedImg.style.opacity = (1 - door).toFixed(3);
            holeEl.style.opacity = holeOn ? (1 - door).toFixed(3) : '0';
            if (holeOn && (ticked || dr.key === '')) {
                const hi = Math.min(16, Math.floor(pos));
                const a = reduced ? 0 : hi;
                const b = reduced ? 17 : hi + 1;
                const f = reduced ? Math.round(wFade * 8) / 8 : Math.round((pos - hi) * 6) / 6;
                const shake = knocking ? (knockN % 2 ? 2 : -2) : 0;
                const puffs = lite || reduced || degraded ? [] : [[knockN, 785, 728, 70, 0.5], [Math.floor((ut - DOOR.swing1 * k) * 15), 950, 728, 130, 0.85]];
                const key = `${a}|${f}|${shake}|${puffs.map((q) => (q[0] >= 0 && q[0] < 8 ? q[0] : -1)).join(',')}`;
                if (key !== dr.key) {
                    dr.key = key;
                    drawDoor(a, b, f, shake, puffs);
                }
            }
            spill.style.opacity = (D * (1 - 0.3 * p)).toFixed(3);
            bloom.style.opacity = (0.5 * D * (1 - 0.3 * cam1)).toFixed(3);
            fogs.style.opacity = (0.06 + 0.26 * D).toFixed(3);
            dust.style.opacity = D.toFixed(3);
            doorline.style.opacity = (0.5 * Math.max(pulse(u, 5.5, 0.12, 0.32), pulse(u, 6.0, 0.12, 0.3), pulse(u, 6.5, 0.12, 0.34)) * (1 - door)).toFixed(3);

            // ---- the owner
            const fOpacity = reduced ? smooth((u - 9) / 2.5) : smooth((u - T.appear) / 0.7);
            const g = figGeom(walk);
            qaG = g;
            const cw = fig.cw || 724;
            const ch = fig.h || 1180;
            figEl.style.opacity = fOpacity.toFixed(3);
            // Inside the door frame he is clipped to the opening; the box widens as he crosses the threshold, then is released.
            const through = wk.ok ? smooth((p - 0.2) / 0.3) : 1;
            if (through < 1 || cls.clipped) {
                cls.clipped = through < 1;
                const clip = through >= 1 ? '' : `inset(${(lerp(0.31, 0, through) * 100).toFixed(2)}% ${(lerp(0.43, 0, through) * 100).toFixed(2)}% 0% ${(lerp(0.41, 0, through) * 100).toFixed(2)}%)`;
                figEl.style.clipPath = clip;
            }
            // Shoulder roll: a lean of about 1 degree toward the boot that lands, alternating with each step. With the atlas it turns
            // about the planted boots (so they stay put); the standing cutout turns about its hips as before.
            const hips = wk.ok ? 0 : -0.48 * ch;
            figCanvas.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch).toFixed(1)}px, 0) scale(${g.sc.toFixed(4)}) translate(0px, ${hips.toFixed(1)}px) rotate(${((wk.ok ? 1 : 1.2) * walk.roll).toFixed(3)}deg) translate(0px, ${(-hips).toFixed(1)}px)`;
            // The cast shadow and the reflection come from the same position value as the body.
            const len = lerp(2.4, 1.0, p);
            const skew = ((g.x - 0.49 * PW) / PH) * -26;
            shadowEl.style.opacity = (0.85 * D * fOpacity).toFixed(3);
            shadowEl.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch).toFixed(1)}px, 0) scale(${g.sc.toFixed(4)}, ${(-g.sc * len).toFixed(4)}) skewX(${skew.toFixed(2)}deg)`;
            reflectEl.style.opacity = (0.18 * D * fOpacity).toFixed(3);
            reflectEl.style.transform = `translate3d(${(g.x - cw / 2).toFixed(1)}px, ${(g.y - ch + walk.bob * 2).toFixed(1)}px, 0) scale(${(g.sc * 0.97).toFixed(4)}, ${(-g.sc * 0.82).toFixed(4)})`;
            // the sandwich on the shelf: cold rim first, the lit copy comes up with the light, one slow glint once the door is open
            eggLit.style.opacity = D.toFixed(3);
            const gl = clamp((u - (T.door1 + 0.5)) / 1.6);
            const glOn = gl > 0 && gl < 1 && !reduced;
            if (glOn || cls.glint) {
                cls.glint = glOn;
                eggGlint.style.opacity = glOn ? (Math.sin(Math.PI * gl) ** 2 * 0.9).toFixed(3) : '0';
                eggGlint.firstElementChild.style.transform = `translate3d(${lerp(-70, 70, gl).toFixed(1)}%, 0, 0)`;
            }

            // The walk is drawn on every display frame (it is two small drawImage calls), at most 30 a second on a weak device.
            if (wk.ok ? !(lite || degraded) || now - fig.drawAt >= 32 : now - fig.drawAt > fig.every) {
                fig.drawAt = now;
                drawFigure(ut, u, D, smooth((u - (T.reveal0 - 0.6)) / 0.6), walk); // backlit until 600 ms before the reveal
            }

            // ---- camera: one move, handheld drift on top, parallax by layer
            const drift = reduced ? 0 : 1;
            // the stopper: 3 px of shake that dies away in 400 ms (none with reduced motion)
            const hit = reduced ? 0 : (ut - DOOR.swing1 * k) / 0.4;
            const sk = hit > 0 && hit < 1 ? Math.exp(-hit * 5) : 0;
            const dx = (driftX(ut) + walk.shakeX * 2 + 3 * sk * Math.sin(hit * 46)) * drift;
            const dy = (driftY(ut) + walk.shakeY * 2 + 2 * sk * Math.cos(hit * 37)) * drift;
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
                }
            }
        }

        // ---- the aim plate: a 400 ms crossfade with a push-in and a rack focus, the key light and the red eyes, the pistol
        // coming up from below the frame (500 ms, small overshoot, then a 400 ms hold), the shot and its recoil
        if (u >= T.reveal0 - 0.05) {
            const F = flashAt(u);
            const x = u - T.shot;
            const rx = u - T.reveal0;
            // The match cut: the plate starts as small as his head is on screen (the world has been pushed in on it for six
            // seconds), stays locked on the face (the camera has just panned it onto F), and grows to full size in 550 ms with the rack focus, so the cut reads as one
            // crash zoom into the face. After that comes the push-in 1 to 1.12 and a slow creep toward the shot.
            const figH = (wk.ok ? WALK_H : 0.9) * H;
            const s0 = clamp((0.123 * figH * S) / (0.59 * geo.ah), 0.2, 1);
            const grow = outCubic(rx / 0.55);
            const early = reduced ? 1 : Math.min(1, Math.exp(Math.log(s0) * (1 - grow)));
            const sa = reduced ? 1 : early * (1 + 0.12 * grow + 0.03 * inOut((u - 23.4) / 3.2));
            const match = reduced ? 0 : 1 - Math.min(1, sa);
            const mtx = match * (geo.afx - W / 2);
            const mty = match * (geo.afy - H / 2);
            // recoil: the plate jumps up 6 px and settles back on a damped spring, with a hair of scale and tilt
            const dyP = !reduced && x > 0 && x < 0.7 ? -6 * spring(x) : 0;
            const hit = !reduced && x > 0 && x < 0.6 ? Math.exp(-x / 0.07) : 0;
            // handheld: a slow drift and a breath on the whole plate, so the face is never a still picture
            const hh = reduced ? 0 : smooth(rx / 0.6);
            const hx = hh * (1.4 * Math.sin(u * 1.9 + 1) + 0.8 * Math.sin(u * 4.7));
            const hy = hh * (1.2 * Math.sin(u * 1.3) + 0.7 * Math.sin(u * 3.9 + 2) + 0.9 * Math.sin(u * 2.2));
            const hr = hh * 0.09 * Math.sin(u * 1.1);
            const hs = 1 + hh * 0.0025 * Math.sin(u * 2.2);
            aim.style.opacity = (cut * lockAt(u)).toFixed(3);
            aimBg.style.opacity = cut.toFixed(3);
            aim.style.transform = `translate3d(${(hx + mtx).toFixed(2)}px, ${(dyP + hy + mty).toFixed(2)}px, 0) scale(${(sa * hs * (1 + 0.02 * hit)).toFixed(4)}) rotate(${(-1 * hit + hr).toFixed(3)}deg)`;
            // rack focus: the plate starts soft and pulls sharp as the crossfade lands
            const blur = reduced || lite || degraded ? 0 : Math.round(7 * (1 - outCubic(rx / 0.9)) * 2) / 2;
            if (blur !== aimBlurNow) {
                aimBlurNow = blur;
                aim.style.filter = blur ? `blur(${blur}px)` : '';
            }
            // The pistol is its own layer: it comes up from below and to the right on an arc (a turn about the wrist and a growth
            // in size as it nears the lens), slow and heavy: 750 ms on an in-out lift with one small overshoot (outBack). While
            // it is fast it is blurred along its own path only (vertical SVG blur, three steps), never doubled by a dark copy.
            // Once it is still (150 ms after rise1) the whole plate takes over, which is the same picture.
            const rt = u - T.rise0;
            const RISE = T.rise1 - T.rise0;
            const lift = (x) => (x <= 0 ? 0 : outBack(inOut(x / RISE)));
            const home = reduced || u >= T.rise1 + 0.15;
            const showGun = !home && u >= T.rise0 - 0.05;
            const place = (el, p, shake) => {
                const q = 1 - p;
                el.style.transform = `translate3d(${(q * 0.13 * geo.aw + shake[0]).toFixed(1)}px, ${(q * geo.drop + shake[1]).toFixed(1)}px, 0) rotate(${(q * 8).toFixed(2)}deg) scale(${(1 - 0.12 * q).toFixed(4)})`;
            };
            if (showGun) {
                const pos = lift(rt);
                const tremor = smooth((rt - RISE + 0.1) / 0.2);
                const sh = [tremor * (0.7 * Math.sin(u * 47) + 0.4 * Math.sin(u * 83)), tremor * 0.6 * Math.sin(u * 53)];
                place(gun, pos, sh);
                // px it travels in one 60 Hz frame: under 6 sharp, then 3, 7 or 12 px of blur along the rise
                const step = Math.abs(pos - lift(rt - 1 / 60)) * geo.drop;
                const mb = lite || degraded ? 0 : step < 6 ? 0 : step < 14 ? 1 : step < 24 ? 2 : 3;
                if (mb !== cls.mb) {
                    cls.mb = mb;
                    gun.style.filter = mb ? `url(#cr-mb${mb})` : '';
                }
            }
            if (showGun !== cls.gunOn) {
                cls.gunOn = showGun;
                gun.style.visibility = showGun ? 'visible' : 'hidden';
            }
            if (home !== cls.home) {
                cls.home = home;
                aimFull.style.visibility = home ? 'visible' : 'hidden';
            }
            // the red light in the eyes ignites 150 ms before the face is fully there
            // and swell to full 150 ms before the shot, while the key light closes in on the face
            const pre = smooth((u - (T.shot - 0.15)) / 0.15) * (u < T.shot + 0.3 ? 1 : 0);
            const eyes = smooth((u - (T.reveal1 - 0.15)) / 0.2) * (reduced ? 1 : lerp(0.66 + 0.1 * Math.sin(u * 7.3), 1, pre));
            aimEyes.style.opacity = eyes.toFixed(3);
            const ks = smooth((u - T.key0) / (T.key1 - T.key0));
            const s = (lerp(0.22, 1.9, ks) + 0.7 * smooth((u - T.key1) / 1.2)) * (1 - 0.2 * pre);
            const fx = geo.mx + (geo.afx - geo.mx) * sa;
            const fy = geo.my + (geo.afy - geo.my) * sa + dyP;
            aimKey.style.transform = `translate3d(${(fx - W / 2).toFixed(1)}px, ${(fy - H / 2).toFixed(1)}px, 0) scale(${s.toFixed(3)})`;
            aimWarm.style.opacity = (F * 0.55).toFixed(3);
            flashImg.style.opacity = (F * 0.85).toFixed(3);
            if ((F > 0) !== cls.flashOn) {
                cls.flashOn = F > 0;
                flashImg.style.visibility = F > 0 ? 'visible' : 'hidden';
                flashTwin.style.visibility = F > 0 ? 'visible' : 'hidden';
            }
            // One local flash on the muzzle (not the screen). The gun points at the lens, so it is a ring: the plate's fan and its mirror.
            const fs = (0.7 + 0.5 * F).toFixed(3);
            flashImg.style.transform = F > 0 ? `translate3d(0, ${dyP.toFixed(1)}px, 0) scale(${fs})` : '';
            flashTwin.style.opacity = (F * 0.85).toFixed(3);
            flashTwin.style.transform = F > 0 ? `translate3d(0, ${dyP.toFixed(1)}px, 0) scale(${fs}, -${fs})` : '';
        }

        // ---- grain rises with the push-in
        const grainOp = u < 2 ? 0.9 - 0.2 * smooth(u / 2) : Math.min(0.9, 0.5 + 0.12 * p + 0.32 * cam2);
        // the vignette closes in as he comes closer, and once more in the last 150 ms before the shot (in steps, so it repaints rarely)
        const preShot = smooth((u - (T.shot - 0.15)) / 0.15) * (u < T.shot + 0.3 ? 1 : 0);
        const vg = Math.round((u < T.reveal0 ? 0.55 * smooth(p) : 0.35 + 0.65 * preShot) * 10) / 10;
        if (vg !== cls.vg) {
            cls.vg = vg;
            vig.style.setProperty('--vg', String(vg));
        }
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
        flag('a3', u >= 19.0);
        threat.style.transform = `scaleX(${threatAt(u).toFixed(3)})`;
        const base = u < T.knock ? 0 : 0.1;
        const want = clamp(base + 0.6 * pulse(u, T.knock, 0.1, 1.1) + 0.55 * walk.bob + 0.5 * Math.min(1, walk.heel) + 0.45 * smooth((u - 19.0) / 2) * (u < T.reveal0 ? 1 : 0.5) + (u >= T.shot ? 1 : 0) * pulse(u, T.shot, 0.05, 0.8));
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
        guardUi(u, now);
        flag('b-black', u >= T.black);
        syncCue(now, waiting ? -1 : u, dt, talking);
        tapCheck(now);
    };

    /* ---- the barrel guard: while the pistol is on screen nothing may sit in the safe zone. Every few frames (and on every resize or
       orientation change) the HUD pieces and the dialogue card are measured against it. A HUD piece that would touch it is hidden;
       the dialogue card moves to the next slot that does not (bottom, top, very bottom). DISCONNECTED is fitted into the space above
       it (its font shrinks until it clears), so none of them can ever cover the muzzle, the flash or the crack. ---- */

    const SLOTS = ['', 'slot-top', 'slot-low'];
    const inZone = (r) => r.width > 0 && r.height > 0 && r.right > zone.l && r.left < zone.r && r.bottom > zone.t && r.top < zone.b;
    const fitDc = () => {
        if (!dcBox) return;
        const maxW = W * 0.94;
        let f = clamp(W * 0.105, 22, 160);
        for (let i = 0; i < 4; i += 1) {
            dcEl.style.setProperty('--dcf', `${f.toFixed(1)}px`);
            // the box is capped at 94vw, so measure the word itself, not the box
            const w = dcTitle.scrollWidth + 0.4 * f;
            const room = zone.t - 8 - dcEl.offsetTop;
            const h = dcBox.offsetHeight;
            if (w <= maxW && h <= room) break;
            f = Math.max(14, f * Math.min(maxW / Math.max(w, 1), room / Math.max(h, 1)) * 0.97);
        }
    };
    const guardUi = (u, now) => {
        if (!guardDirty && now - guardAt < 120) return;
        guardDirty = false;
        guardAt = now;
        fitDc();
        if (u < ZONE_ON) return;
        hudParts.forEach((node) => node.classList.toggle('cr-avoid', inZone(node.getBoundingClientRect())));
        if (dlg.style.visibility === 'hidden' || !dlg.classList.contains('on')) return;
        let chosen = SLOTS.length - 1;
        for (let i = 0; i < SLOTS.length; i += 1) {
            SLOTS.forEach((c) => c && dlg.classList.toggle(c, c === SLOTS[i]));
            if (!inZone(dlg.getBoundingClientRect())) { chosen = i; break; }
        }
        SLOTS.forEach((c) => c && dlg.classList.toggle(c, c === SLOTS[chosen]));
        hz.dataset.slot = SLOTS[chosen] || 'bottom';
    };

    // QA only: freeze at another second, and read how bright the owner's own pixels are in a screen rectangle (the figure
    // canvas is read back, so the result is what the sprite draws there, before grain and scanlines).
    let qaG = null;
    if (QA) {
        window.__hp = {
            seek(sec) { QA.seek = Number(sec) || 0; },
            walk() { return { pa: phase.pa, pb: phase.pb, kf: phase.kf, frames: phase.table ? phase.table[PHASE_N] : null, strikes: STRIKES.slice() }; },
            face() {
                if (!qaG || !fig.ready) return null;
                // read back through every transform (camera layers, push-in, shoulder roll) from the canvas box itself
                const r = figCanvas.getBoundingClientRect();
                const cx = r.left + ((fig.face[0] + PAD) / fig.cw) * r.width;
                const cy = r.top + (fig.face[1] / fig.h) * r.height;
                const half = (62 / fig.h) * r.height;
                return { x: cx - 0.9 * half, y: cy - 0.7 * half, w: 1.8 * half, h: 2 * half };
            },
            // Both feet: the lowest opaque pixel of each half of the sprite, on screen and in plate pixels of the floor layer
            // (the far layer carries the floor and the grate), so a planted foot can be checked against the floor itself.
            feet() {
                const r = figCanvas.getBoundingClientRect();
                const fr = layers.far.getBoundingClientRect();
                const mr = layers.mid.getBoundingClientRect();
                const cv = figCanvas;
                const d = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
                const half = (x0, x1) => {
                    for (let y = cv.height - 1; y >= 0; y -= 1) {
                        let sum = 0;
                        let n = 0;
                        for (let x = x0; x < x1; x += 1) if (d[(y * cv.width + x) * 4 + 3] > 128) { sum += x; n += 1; }
                        if (n > 2) {
                            const sx = r.left + ((sum / n) / cv.width) * r.width;
                            const sy = r.top + ((y + 1) / cv.height) * r.height;
                            return { sx, sy, px: ((sx - fr.left) / fr.width) * PW, py: ((sy - fr.top) / fr.height) * PH, mx: ((sx - mr.left) / mr.width) * PW, my: ((sy - mr.top) / mr.height) * PH };
                        }
                    }
                    return null;
                };
                const mid = Math.round(cv.width / 2);
                return { left: half(0, mid), right: half(mid, cv.width), shown: wk.shown };
            },
            sample(x, y, w, h) {
                const r = figCanvas.getBoundingClientRect();
                const op = Number(getComputedStyle(figEl).opacity) || 0;
                if (!r.width || !r.height || !op) return { lum: 0, cover: 0 };
                const sx = figCanvas.width / r.width;
                const sy = figCanvas.height / r.height;
                const px = Math.max(0, Math.floor((x - r.left) * sx));
                const py = Math.max(0, Math.floor((y - r.top) * sy));
                const pw = Math.min(figCanvas.width - px, Math.ceil(w * sx));
                const ph = Math.min(figCanvas.height - py, Math.ceil(h * sy));
                if (pw <= 0 || ph <= 0) return { lum: 0, cover: 0 };
                const d = figCanvas.getContext('2d').getImageData(px, py, pw, ph).data;
                let sum = 0;
                let wsum = 0;
                for (let i = 0; i < d.length; i += 4) {
                    const a = d[i + 3] / 255;
                    if (a < 0.5) continue;
                    sum += (0.2126 * d[i] + 0.7152 * d[i + 1] + 0.0722 * d[i + 2]) / a;
                    wsum += 1;
                }
                return { lum: wsum ? (sum / wsum) * op : 0, cover: wsum / (d.length / 4) };
            },
        };
    }

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
                Promise.all([document.fonts.load('800 24px "Bagel Fat One"'), document.fonts.load('700 12px "Space Mono"'), document.fonts.load('600 24px "Crimson Pro"'), document.fonts.load('italic 600 24px "Crimson Pro"'), document.fonts.load('600 24px "Cinzel"'), document.fonts.load('700 12px "IBM Plex Mono"')])
                    .then(() => document.fonts.ready)
                    .then(() => {
                        const loaded = (name) => Array.from(document.fonts).some((face) => face.family.replace(/"/g, '') === name && face.status === 'loaded');
                        hz.dataset.fonts = ['Space Mono', 'Crimson Pro', 'Cinzel', 'IBM Plex Mono'].every(loaded) ? 'brand' : 'fallback';
                    })
                    .catch(() => { hz.dataset.fonts = 'fallback'; });
            } else {
                hz.dataset.fonts = 'fallback';
            }

            layout();
            window.addEventListener('resize', layout);
            // Press or tap the sandwich on the shelf: a small crunch. No hint, no text.
            eggWrap.addEventListener('click', () => {
                play('crunch', SPACE.crunch);
                if (eggWrap.animate && !reduced) eggWrap.animate([{ transform: 'scale(1)' }, { transform: 'scale(0.9) rotate(-2deg)' }, { transform: 'scale(1)' }], { duration: 150, easing: 'ease-out' });
            });
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

    if (QA) horror.start(QA.seek * 1000);
    else if (elapsed < horrorSeconds) horror.start(elapsed * 1000);
    else startKewer();

    tick();
    window.setInterval(tick, 250);
    forceFullscreen();
}

boot();
