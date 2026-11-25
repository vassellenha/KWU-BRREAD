// Global sound effects for the customer pages: pure Web Audio, no files. Soft, round, dessert-like.
// window.brreadSfx.play(name, opts?), setMuted(bool), toggle(), isMuted(), setVolume(0..1).
// Click and hover sounds come from data-sfx="name" or the selector registry below (innermost match wins);
// sounds that depend on state (unlock, add to cart, drawer, undo...) are played from the Alpine handlers.

const STORE_KEY = 'brreadSfxMuted';
const MAX_VOICES = 6;
const TICK_GAP_MS = 60;
const TICKS = new Set(['tick', 'softtick']);
// Kept even for reduced motion (at full level): they confirm something the customer did.
const ESSENTIAL = new Set(['register', 'marimba', 'chime', 'unlock']);

let muted = false;
try {
    muted = localStorage.getItem(STORE_KEY) === '1';
} catch {
    // Storage can be blocked; the sound simply starts on.
}

let volume = 0.3;
let ctx = null;
let bus = null; // every voice feeds this: master gain, then a soft compressor
let master = null;
let noiseBuffer = null;
let activeVoices = 0;
let lastTick = 0;
let lastHover = 0;

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
const coarsePointer = window.matchMedia?.('(pointer: coarse)');
const semitone = (n) => 2 ** (n / 12);

function ensureContext() {
    if (!ctx) {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return null;

        ctx = new AudioCtx();
        master = ctx.createGain();
        master.gain.value = volume;

        const compressor = ctx.createDynamicsCompressor();
        compressor.threshold.value = -18;
        compressor.ratio.value = 3;
        compressor.knee.value = 12;

        bus = master;
        master.connect(compressor).connect(ctx.destination);

        // One second of noise, made once and shared by every noise voice.
        noiseBuffer = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
        const data = noiseBuffer.getChannelData(0);
        for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
    }

    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    return ctx;
}

// The context is only created on the first gesture, as browsers require.
['pointerdown', 'keydown', 'touchstart'].forEach((type) => {
    window.addEventListener(type, ensureContext, { passive: true, capture: true });
});

// ---- voices -------------------------------------------------------------------------------------------------

// A rounded tone: sine or triangle through a lowpass, short attack, smooth release.
function tone(t0, rate, level, { type = 'sine', f0, f1 = f0, at = 0, dur, gain = 0.5, lp = 2500, atk = 0.005 }) {
    const start = t0 + at;
    const end = start + dur;
    const osc = ctx.createOscillator();
    const filter = ctx.createBiquadFilter();
    const env = ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(f0 * rate, start);
    if (f1 !== f0) osc.frequency.exponentialRampToValueAtTime(f1 * rate, start + dur * 0.85);

    filter.type = 'lowpass';
    filter.frequency.value = lp;

    const peak = Math.max(0.0002, gain * level);
    env.gain.setValueAtTime(0.0001, start);
    env.gain.linearRampToValueAtTime(peak, start + Math.min(atk, dur * 0.5));
    env.gain.exponentialRampToValueAtTime(0.0001, end);

    osc.connect(filter).connect(env).connect(bus);
    osc.start(start);
    osc.stop(end + 0.02);
}

// Filtered noise: a sweep from f0 to f1, optionally softened by an extra lowpass.
function noise(t0, level, { kind = 'lowpass', f0, f1 = f0, at = 0, dur, gain = 0.3, q = 0.9, lp = 0, atk = 0.006 }) {
    const start = t0 + at;
    const end = start + dur;
    const src = ctx.createBufferSource();
    const filter = ctx.createBiquadFilter();
    const env = ctx.createGain();

    src.buffer = noiseBuffer;
    filter.type = kind;
    filter.Q.value = q;
    filter.frequency.setValueAtTime(f0, start);
    if (f1 !== f0) filter.frequency.exponentialRampToValueAtTime(f1, end);

    const peak = Math.max(0.0002, gain * level);
    env.gain.setValueAtTime(0.0001, start);
    env.gain.linearRampToValueAtTime(peak, start + Math.min(atk, dur * 0.5));
    env.gain.exponentialRampToValueAtTime(0.0001, end);

    src.connect(filter);
    if (lp) {
        const soft = ctx.createBiquadFilter();
        soft.type = 'lowpass';
        soft.frequency.value = lp;
        filter.connect(soft).connect(env);
    } else {
        filter.connect(env);
    }
    env.connect(bus);
    src.start(start, Math.random() * 0.5);
    src.stop(end + 0.02);
}

// name -> [total duration in seconds, builder(t0, rate, level, opts)]
const RECIPES = {
    tock: [0.09, (t, r, l) => {
        tone(t, r, l, { f0: 520, f1: 380, dur: 0.07, gain: 0.5, lp: 1600 });
        noise(t, l, { kind: 'bandpass', f0: 900, dur: 0.008, gain: 0.08, q: 1 });
    }],
    popsoft: [0.13, (t, r, l) => RECIPES.pop[1](t, r, l * 0.5)],
    pop: [0.13, (t, r, l) => tone(t, r, l, { f0: 420, f1: 720, dur: 0.11, gain: 0.6, lp: 2600 })],
    tick: [0.04, (t, r, l) => tone(t, r, l, { type: 'triangle', f0: 1100, dur: 0.028, gain: 0.12, lp: 2200, atk: 0.003 })],
    plop: [0.15, (t, r, l) => tone(t, r, l, { f0: 300, f1: 180, dur: 0.13, gain: 0.55, lp: 1500 })],
    ding: [0.45, (t, r, l) => {
        tone(t, r, l, { f0: 988, dur: 0.32, gain: 0.12, lp: 3000, atk: 0.006 });
        tone(t, r, l, { f0: 1319, dur: 0.32, gain: 0.09, lp: 3200, atk: 0.006 });
    }],
    puf: [0.16, (t, r, l) => {
        noise(t, l, { f0: 500, dur: 0.14, gain: 0.35 });
        tone(t, r, l, { f0: 140, f1: 90, dur: 0.12, gain: 0.5, lp: 800 });
    }],
    scoop: [0.18, (t, r, l) => {
        tone(t, r, l, { f0: 240, f1: 130, dur: 0.16, gain: 0.5, lp: 1200 });
        noise(t, l, { f0: 700, dur: 0.06, gain: 0.2 });
    }],
    sprinkle: [0.22, (t, r, l) => {
        const count = 4 + Math.floor(Math.random() * 3);
        for (let i = 0; i < count; i++) {
            tone(t, r, l, { f0: 1800 + Math.random() * 1400, dur: 0.025, at: Math.random() * 0.155, gain: 0.1, lp: 3500, atk: 0.003 });
        }
    }],
    sparkle: [0.5, (t, r, l) => {
        [660, 880, 1175, 1568, 2093].forEach((f, i) => tone(t, r, l, { f0: f, dur: 0.09, at: i * 0.045, gain: 0.17, lp: 3500, atk: 0.004 }));
        tone(t, r, l, { f0: 2093, dur: 0.2, at: 0.2, gain: 0.07, lp: 3500, atk: 0.01 });
    }],
    unlock: [0.3, (t, r, l) => {
        tone(t, r, l, { f0: 880, dur: 0.11, gain: 0.35, lp: 3200 });
        tone(t, r, l, { f0: 1318, dur: 0.16, at: 0.09, gain: 0.32, lp: 3500 });
    }],
    softtick: [0.05, (t, r, l) => tone(t, r, l, { type: 'triangle', f0: 900, dur: 0.035, gain: 0.12, lp: 2000, atk: 0.003 })],
    blipdown: [0.13, (t, r, l) => tone(t, r, l, { f0: 700, f1: 420, dur: 0.11, gain: 0.35, lp: 2200 })],
    blipup: [0.13, (t, r, l) => tone(t, r, l, { f0: 420, f1: 700, dur: 0.11, gain: 0.35, lp: 2200 })],
    swish: [0.34, (t, r, l) => noise(t, l, { kind: 'bandpass', f0: 400, f1: 1800, dur: 0.32, gain: 0.22, q: 1.2, lp: 2600, atk: 0.04 })],
    whoosh: [0.28, (t, r, l, o) => {
        const closing = o.dir === 'down';
        noise(t, l, { kind: 'bandpass', f0: closing ? 1200 : 300, f1: closing ? 300 : 1200, dur: 0.26, gain: 0.2, q: 1, lp: 2200, atk: 0.05 });
    }],
    tockup: [0.09, (t, r, l) => RECIPES.tock[1](t, r * semitone(4), l)],
    tockdown: [0.09, (t, r, l) => RECIPES.tock[1](t, r * semitone(-4), l)],
    register: [0.45, (t, r, l) => {
        tone(t, r, l, { f0: 1568, dur: 0.38, gain: 0.2, lp: 3500, atk: 0.004 });
        tone(t, r, l, { f0: 2093, dur: 0.38, at: 0.05, gain: 0.15, lp: 3500, atk: 0.004 });
    }],
    poof: [0.22, (t, r, l) => {
        noise(t, l, { f0: 900, f1: 250, dur: 0.18, gain: 0.35 });
        tone(t, r, l, { f0: 200, f1: 110, dur: 0.15, gain: 0.35, lp: 900 });
    }],
    chime: [0.55, (t, r, l) => {
        tone(t, r, l, { f0: 784, dur: 0.45, gain: 0.25, lp: 3200, atk: 0.006 });
        tone(t, r, l, { f0: 1175, dur: 0.45, at: 0.1, gain: 0.2, lp: 3400, atk: 0.006 });
    }],
    marimba: [0.65, (t, r, l) => {
        [523, 659, 784, 1047].forEach((f, i) => {
            tone(t, r, l, { f0: f, dur: 0.22, at: i * 0.11, gain: 0.34, lp: 3200, atk: 0.004 });
            tone(t, r, l, { f0: f * 4, dur: 0.07, at: i * 0.11, gain: 0.05, lp: 3500, atk: 0.002 });
        });
    }],
};

// plop then ding, the quick add on the menu
const SEQUENCES = { plopding: [['plop', 0], ['ding', 0.1]] };

function play(name, opts = {}) {
    if (muted || document.hidden || !ctx || !bus) return;

    if (SEQUENCES[name]) {
        SEQUENCES[name].forEach(([part, delay]) => setTimeout(() => play(part, opts), delay * 1000));
        return;
    }

    const recipe = RECIPES[name];
    if (!recipe) return;

    if (TICKS.has(name)) {
        const now = performance.now();
        if (now - lastTick < TICK_GAP_MS) return;
        lastTick = now;
    }
    if (activeVoices >= MAX_VOICES) return;

    if (ctx.state === 'suspended') ctx.resume().catch(() => {});

    // Reduced motion keeps the confirmations and softens everything else.
    const level = (opts.gain ?? 1) * (reducedMotion?.matches && !ESSENTIAL.has(name) ? 0.6 : 1);
    const rate = 1 + (Math.random() * 2 - 1) * 0.03;
    const [duration, build] = recipe;

    activeVoices++;
    setTimeout(() => (activeVoices = Math.max(0, activeVoices - 1)), (duration + 0.05) * 1000);

    try {
        build(ctx.currentTime + 0.005, rate, level, opts);
    } catch {
        // A sound must never break the page.
    }

    window.brreadSfx?.onPlay?.(name, opts);
}

// ---- mute button ---------------------------------------------------------------------------------------------

function syncButtons() {
    document.querySelectorAll('[data-sfx-toggle]').forEach((button) => button.setAttribute('aria-pressed', String(muted)));
    document.querySelectorAll('[data-sfx-label]').forEach((el) => (el.textContent = muted ? 'Sound off' : 'Sound on'));
}

function setMuted(value) {
    muted = Boolean(value);
    try {
        localStorage.setItem(STORE_KEY, muted ? '1' : '0');
    } catch {
        // Not persisted; still applies to this page.
    }
    syncButtons();
}

function toggle() {
    setMuted(!muted);
    if (!muted) {
        ensureContext();
        play('pop', { gain: 0.6 });
    }
    return muted;
}

function setVolume(value) {
    volume = Math.min(1, Math.max(0, Number(value) || 0));
    if (master) master.gain.value = volume;
}

// ---- delegation ----------------------------------------------------------------------------------------------

// [selector, sound, options]. The innermost element that matches (or carries data-sfx) wins.
const CLICK_REGISTRY = [
    ['.site-nav-link, .site-nav-sheet-link, .site-nav-logo, footer a[href]', 'tock'],
    ['.menu-tile', 'plop'],
    ['a[href^="http"], a[href^="tel:"], a[href^="mailto:"], a[href*="wa.me"]', 'pop', { gain: 0.5 }],
    ['.btn-chunky', 'pop'],
    ['summary, [role="tab"], [aria-expanded]', 'tick'],
];
const HOVER_REGISTRY = [
    ['.product-card, .menu-tile, .about-card-line', 'tick'],
    ['[role="tab"], summary', 'tick'],
];

function soundFor(target, registry, attribute) {
    for (let el = target; el && el !== document.documentElement; el = el.parentElement) {
        if (el.matches?.(':disabled, [aria-disabled="true"]')) return null;

        const named = el.dataset?.[attribute];
        if (named !== undefined) return named === 'none' || named === '' ? null : { name: named, opts: {} };

        for (const [selector, name, opts = {}] of registry) {
            if (el.matches?.(selector)) return { name, opts };
        }
    }
    return null;
}

document.addEventListener('click', (event) => {
    const toggleButton = event.target.closest?.('[data-sfx-toggle]');
    if (toggleButton) {
        toggle();
        return;
    }

    const hit = soundFor(event.target, CLICK_REGISTRY, 'sfx');
    if (hit) play(hit.name, hit.opts);
});

// Hover and keyboard focus ticks: never on touch screens or with reduced motion, and spaced out.
let hoverTarget = null;
document.addEventListener('mouseover', (event) => {
    if (coarsePointer?.matches || reducedMotion?.matches) return;

    const hit = soundFor(event.target, HOVER_REGISTRY, 'sfxHover');
    if (!hit) {
        hoverTarget = null;
        return;
    }

    const holder = event.target.closest?.('.product-card, .menu-tile, .about-card-line, [role="tab"], summary, [data-sfx-hover]');
    if (holder === hoverTarget) return;
    hoverTarget = holder;

    const now = performance.now();
    if (now - lastHover < TICK_GAP_MS) return;
    lastHover = now;
    play(hit.name, { gain: 0.8 });
});

// Form fields on the customer pages get the soft pop when focused.
document.addEventListener('focusin', (event) => {
    if (event.target.matches?.('input:not([type="checkbox"]):not([type="radio"]), textarea, select')) {
        play('pop', { gain: 0.4 });
    }
});

window.addEventListener('storage', (event) => {
    if (event.key === STORE_KEY) {
        muted = event.newValue === '1';
        syncButtons();
    }
});

document.addEventListener('DOMContentLoaded', syncButtons);
document.addEventListener('alpine:initialized', syncButtons);

window.brreadSfx = { play, setMuted, toggle, isMuted: () => muted, setVolume };
