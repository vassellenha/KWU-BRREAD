// The /admin honeypot, in two phases: a short cinematic horror intro (phase A), then a hard cut to the
// "Kewer Kewer" video (phase B). Forced fullscreen, no way out. The server keeps the real clock per
// address and hands over the elapsed seconds, so a reload resumes at the right point and never replays the intro. Everything is bounded: one canvas, a fixed pool of windows, stamps and pop-ups,
// transform and opacity only, one requestAnimationFrame loop that sleeps while the tab is hidden.
// Nothing flashes bright: the canvas carries dim grain and thin tinted bars, never a full-screen flash.

const KEY = 'brread-honeypot';
const LINE = 'mana mungkin seorang Vassell Enha membiarkan amatiran seperti anda bisa akses ke panel admin wkwkwk';
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

const state = { start: 0, until: 0, horrorMs: 14000, ip: root?.dataset.ip || '', phase: 'horror', tries: [], bsod: false, popAt: 0, raf: 0, ended: false };

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

    window.addEventListener('beforeunload', (event) => {
        if (state.ended) return;
        event.preventDefault();
        event.returnValue = '';
    });

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

/* ------------------------------------------------------------ phase A: the horror intro */

const horror = (() => {
    const hz = document.getElementById('hz');
    const eyesHost = document.getElementById('hz-eyes');
    const handsHost = document.getElementById('hz-hands');
    const pulse = document.getElementById('hz-pulse');
    const lineEl = document.getElementById('hz-text');
    const chars = [];
    const eyes = [];
    const hands = [];
    let ctx = null;
    let music = null;
    let timer = 0;
    let beatTimer = 0;
    let blinkTimer = 0;
    let fade = 0;
    let stopCanvas = () => {};
    let k = 1;
    let handIndex = 0;
    let beatsFired = 0;
    let musicOn = false;

    const live = () => ctx && ctx.state === 'running';

    function makeContext() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) ctx = new Ctx();
        } catch {
            ctx = null;
        }
    }

    /** Sound waits for the first press, key or touch, as browsers insist; the picture does not wait. */
    function wake() {
        if (ctx && ctx.state === 'suspended') ctx.resume().catch(() => {});
        if (musicOn && music && music.paused && state.phase === 'horror') music.play().catch(() => {});
    }

    function tone(type, from, to, at, gain, attack, length) {
        if (!live()) return;
        const osc = ctx.createOscillator();
        const amp = ctx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(from, at);
        osc.frequency.exponentialRampToValueAtTime(Math.max(1, to), at + length);
        amp.gain.setValueAtTime(0.0001, at);
        amp.gain.exponentialRampToValueAtTime(Math.max(0.0002, gain), at + attack);
        amp.gain.exponentialRampToValueAtTime(0.0001, at + length);
        osc.connect(amp).connect(ctx.destination);
        osc.start(at);
        osc.stop(at + length + 0.05);
    }

    function hiss(at, length, gain, freq) {
        if (!live()) return;
        const size = Math.floor(ctx.sampleRate * length);
        const buffer = ctx.createBuffer(1, size, ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < size; i += 1) data[i] = Math.random() * 2 - 1;
        const source = ctx.createBufferSource();
        const filter = ctx.createBiquadFilter();
        const amp = ctx.createGain();
        source.buffer = buffer;
        filter.type = 'bandpass';
        filter.frequency.value = freq;
        amp.gain.setValueAtTime(0.0001, at);
        amp.gain.exponentialRampToValueAtTime(gain, at + 0.08);
        amp.gain.exponentialRampToValueAtTime(0.0001, at + length);
        source.connect(filter).connect(amp).connect(ctx.destination);
        source.start(at);
    }

    const thump = (strength = 1) => {
        if (!live()) return;
        const at = ctx.currentTime + 0.01;
        tone('sine', 72, 42, at, 0.55 * strength, 0.012, 0.22);
        tone('triangle', 140, 80, at, 0.12 * strength, 0.01, 0.12);
        tone('sine', 60, 38, at + 0.19, 0.4 * strength, 0.012, 0.2);
    };

    const thud = () => {
        if (!live()) return;
        const at = ctx.currentTime + 0.005;
        tone('sine', 110, 38, at, 0.8, 0.005, 0.35);
        hiss(at, 0.2, 0.25, 500);
    };

    const sting = () => {
        if (!live()) return;
        const at = ctx.currentTime + 0.01;
        hiss(at, 0.9, 0.2, 2400);
        [1760, 1867, 1990].forEach((f) => tone('sawtooth', f, f * 0.94, at, 0.03, 0.03, 1.1));
        tone('sawtooth', 55, 41, at, 0.2, 0.2, 1.4);
    };

    function build() {
        // The line, one span per letter, so it can emerge from the dark without the words jumping about.
        LINE.split(' ').forEach((word, index, all) => {
            const holder = el('span', null);
            holder.style.opacity = '1';
            holder.style.display = 'inline-block';
            [...word].forEach((letter) => {
                const span = el('span', null, letter);
                chars.push(span);
                holder.append(span);
            });
            lineEl.append(holder);
            if (index < all.length - 1) lineEl.append(document.createTextNode(' '));
        });
        lineEl.setAttribute('aria-label', LINE);

        const total = lite ? 4 : 7;
        for (let i = 0; i < total; i += 1) {
            const eye = el('div', 'hz-eye');
            eye.append(el('i'), el('i'));
            eye.querySelectorAll('i').forEach((i2) => i2.append(el('b')));
            const left = Math.random() < 0.5;
            eye.style.left = `${left ? rand(4, 26) : rand(62, 86)}%`;
            eye.style.top = `${rand(8, 82)}%`;
            eye.style.fontSize = `${rand(0.7, 1.5).toFixed(2)}em`;
            eye.style.transitionDelay = `${(i * 0.22).toFixed(2)}s`;
            eyesHost.append(eye);
            eyes.push(eye);
        }

        for (let i = 0; i < 3; i += 1) {
            const NS = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(NS, 'svg');
            svg.setAttribute('viewBox', '0 0 100 130');
            svg.setAttribute('class', 'hz-hand');
            const part = (tag, attrs) => {
                const node = document.createElementNS(NS, tag);
                Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
                svg.append(node);
            };
            part('ellipse', { cx: 50, cy: 88, rx: 27, ry: 31 });
            [[20, 52, -20, 21], [37, 38, -6, 25], [55, 36, 4, 26], [72, 44, 14, 23]].forEach(([cx, cy, rot, ry]) => part('ellipse', { cx, cy, rx: 7, ry, transform: `rotate(${rot} ${cx} ${cy})` }));
            part('ellipse', { cx: 16, cy: 92, rx: 7, ry: 20, transform: 'rotate(-55 16 92)' });
            handsHost.append(svg);
            hands.push(svg);
        }
    }

    function slam() {
        const hand = hands[handIndex % hands.length];
        handIndex += 1;
        const w = window.innerWidth;
        const h = window.innerHeight;
        hand.style.setProperty('--x', `${rand(w * 0.05, w * 0.7)}px`);
        hand.style.setProperty('--y', `${rand(h * 0.08, h * 0.5)}px`);
        hand.style.setProperty('--r', `${rand(-25, 25).toFixed(0)}deg`);
        hand.classList.remove('is-slam');
        void hand.getBoundingClientRect();
        hand.classList.add('is-slam');
    }

    function quake() {
        if (reduced || lite) return;
        hz.classList.remove('is-quake');
        void hz.offsetWidth;
        hz.classList.add('is-quake');
    }

    function startMusic() {
        musicOn = true;
        try {
            music = new Audio(root.dataset.music);
            music.volume = 0;
            music.loop = true;
            music.play().catch(() => {});
            // A slow rise, so the heartbeat comes first and the score swells under it.
            fade = window.setInterval(() => {
                if (!music) return;
                music.volume = Math.min(0.8, music.volume + 0.04);
                if (music.volume >= 0.8) window.clearInterval(fade);
            }, 220);
        } catch {
            music = null;
        }
    }

    /** Heartbeat: slow at first, faster and louder towards the reveal. */
    function beatLoop() {
        if (state.phase !== 'horror' || state.ended) return;
        const progress = Math.min(1, Math.max(0, (Date.now() - state.start - 1500 * k) / (12000 * k)));
        const gap = 1000 - progress * 560;
        if (!document.hidden) {
            thump(0.7 + progress * 0.5);
            if (!reduced && pulse.animate) {
                pulse.animate([{ opacity: 0 }, { opacity: 0.35 + progress * 0.35, offset: 0.2 }, { opacity: 0 }], { duration: 520, easing: 'ease-out' });
            }
        }
        beatTimer = window.setTimeout(beatLoop, gap);
    }

    function blinkLoop() {
        if (state.phase !== 'horror' || state.ended || reduced) return;
        const eye = eyes[Math.floor(Math.random() * eyes.length)];
        if (eye?.classList.contains('is-open')) {
            eye.classList.add('is-blinking');
            later(() => eye.classList.remove('is-blinking'), 130);
        }
        blinkTimer = window.setTimeout(blinkLoop, rand(900, 1800));
    }

    // The beat sheet. [ms at 14 s, name]. Anything already past on arrival is applied silently.
    const BEATS = [
        [0, (on) => {
            hz.classList.add('b-static');
            if (on) { hiss(ctx?.currentTime ?? 0, 1.6, 0.12, 3000); tone('sine', 55, 52, ctx?.currentTime ?? 0, 0.1, 0.5, 3.2); }
        }],
        [1500, (on) => {
            hz.classList.add('b-beat');
            if (on) { startMusic(); beatLoop(); } else if (state.phase === 'horror') { startMusic(); beatLoop(); }
        }],
        [3000, () => hz.classList.add('b-blood')],
        [4000, (on) => {
            eyes.forEach((eye) => eye.classList.add('is-open'));
            blinkLoop();
            if (on) tone('sine', 220, 180, ctx?.currentTime ?? 0, 0.05, 0.4, 1.4);
        }],
        [5000, (on) => { hz.classList.add('b-figure', 's1'); if (on) sting(); }],
        [6300, (on) => { hz.classList.add('s2'); if (on) { sting(); } slam(); if (on) { thud(); quake(); } }],
        [7600, (on) => { hz.classList.add('s3'); if (on) { sting(); } slam(); if (on) { thud(); quake(); } }],
        [8400, () => hz.classList.add('b-text')],
        // 13.6 s: the hard cut to black. Phase B starts at 14 s on the dot.
        [13600, () => {
            clearTimeout(beatTimer);
            clearTimeout(blinkTimer);
            window.clearInterval(fade);
            music?.pause();
            hz.classList.add('is-cut');
            hz.style.visibility = 'hidden';
        }],
    ];

    function frame() {
        if (state.phase !== 'horror' || state.ended) return;
        const elapsed = Date.now() - state.start;

        while (beatsFired < BEATS.length && elapsed >= BEATS[beatsFired][0] * k) {
            const [at, run] = BEATS[beatsFired];
            beatsFired += 1;
            run(elapsed - at * k < 700);
        }

        // Letter by letter from 8.4 s to 11 s; the hold on the whole line then runs to the cut.
        const typed = Math.min(chars.length, Math.max(0, Math.floor(((elapsed - 8400 * k) / (2600 * k)) * chars.length)));
        for (let i = 0; i < typed; i += 1) if (!chars[i].classList.contains('is-on')) chars[i].classList.add('is-on');
    }

    return {
        start(offset) {
            k = state.horrorMs / 14000;
            hz.hidden = false;
            hz.classList.toggle('is-lite', lite);
            hz.classList.toggle('is-calm', reduced);
            build();
            makeContext();
            stopCanvas = startCanvas(document.getElementById('hz-canvas'), { bars: false });

            const late = offset > 400;
            if (late) hz.classList.add('is-late');
            frame();
            if (late) later(() => hz.classList.remove('is-late'), 80);

            timer = window.setInterval(frame, 80);
            ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, wake, { capture: true }));
            wake();
        },
        stop() {
            window.clearInterval(timer);
            window.clearInterval(fade);
            clearTimeout(beatTimer);
            clearTimeout(blinkTimer);
            music?.pause();
            stopCanvas();
        },
        end() {
            this.stop();
            hz.hidden = true;
            hz.remove();
            try {
                ctx?.close?.().catch(() => {});
            } catch {
                // Already closed.
            }
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
    const horrorSeconds = Number(root.dataset.horror || 14);
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
