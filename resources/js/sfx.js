// Global sound effects for the customer pages: pure Web Audio, no files. Tactile and dessert-shop: bread thumps,
// wafer snaps, spoon clinks and clean bells, each built from a few short layers.
// window.brreadSfx.play(name, opts?), setMuted(bool), toggle(), isMuted(), setVolume(0..1).
// Click and hover sounds come from data-sfx="name" or the selector registry below (innermost match wins);
// sounds that depend on state (unlock, add to cart, drawer, undo...) are played from the Alpine handlers.

const STORE_KEY = 'brreadSfxMuted';
const MAX_VOICES = 8;
const TICK_GAP_MS = 60;
const HOVER_GAP_MS = 90;
const TICKS = new Set(['tick', 'softtick', 'hover']);
// Kept even for reduced motion (at full level): they confirm something the customer did.
const ESSENTIAL = new Set(['register', 'marimba', 'chime', 'unlock']);
const MASTER = 0.6;

let muted = false;
try {
    // The speaker button is gone: the sound effects are always on, so an old saved mute is dropped.
    localStorage.removeItem(STORE_KEY);
} catch {
    // Storage can be blocked; the sound simply starts on.
}

let volume = MASTER;
let ctx = null;
let bus = null; // every voice feeds this: master gain, then the filters, compressor and limiter
let graph = null;
let noiseBuffer = null;
let smallSpeaker = false;
let activeVoices = 0;
let lastTick = 0;
let lastHover = 0;

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
const coarsePointer = window.matchMedia?.('(pointer: coarse)');
const semitone = (n) => 2 ** (n / 12);
const dbToGain = (db) => 10 ** (db / 20);

// master -> highpass 80 Hz -> presence +2 dB at 3 kHz -> compressor -> limiter -> (small speaker filters) -> out.
function buildGraph(context) {
    const master = context.createGain();
    master.gain.value = volume;

    const rumble = context.createBiquadFilter();
    rumble.type = 'highpass';
    rumble.frequency.value = 80;
    rumble.Q.value = 0.7;

    const presence = context.createBiquadFilter();
    presence.type = 'peaking';
    presence.frequency.value = 3000;
    presence.Q.value = 0.9;
    presence.gain.value = 2;

    const compressor = context.createDynamicsCompressor();
    compressor.threshold.value = -14;
    compressor.ratio.value = 4;
    compressor.knee.value = 6;
    compressor.attack.value = 0.002;
    compressor.release.value = 0.08;

    const limiter = context.createDynamicsCompressor();
    limiter.threshold.value = -3;
    limiter.ratio.value = 20;
    limiter.knee.value = 0;
    limiter.attack.value = 0.001;
    limiter.release.value = 0.05;

    // Only used by the audition page to imitate a small laptop speaker.
    const speakerLow = context.createBiquadFilter();
    speakerLow.type = 'lowpass';
    speakerLow.frequency.value = 22000;
    const speakerHigh = context.createBiquadFilter();
    speakerHigh.type = 'highpass';
    speakerHigh.frequency.value = 10;

    master.connect(rumble).connect(presence).connect(compressor).connect(limiter).connect(speakerLow).connect(speakerHigh).connect(context.destination);

    // One second of noise, made once and shared by every noise voice.
    const noise = context.createBuffer(1, context.sampleRate, context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;

    return { master, noise, speakerLow, speakerHigh };
}

function applySmallSpeaker() {
    if (!graph) return;
    graph.speakerLow.frequency.value = smallSpeaker ? 8000 : 22000;
    graph.speakerHigh.frequency.value = smallSpeaker ? 150 : 10;
}

function ensureContext() {
    if (!ctx) {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return null;

        try {
            ctx = new AudioCtx({ latencyHint: 'interactive' });
        } catch {
            ctx = new AudioCtx();
        }
        graph = buildGraph(ctx);
        bus = graph.master;
        noiseBuffer = graph.noise;
        applySmallSpeaker();
        loadFiles();
    }

    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    return ctx;
}

// The context is only created on the first gesture, as browsers require.
['pointerdown', 'keydown', 'touchstart'].forEach((type) => {
    window.addEventListener(type, ensureContext, { passive: true, capture: true });
});

// ---- layers -------------------------------------------------------------------------------------------------

// A tone: sine or triangle, near-instant attack, optional fast pitch drop (sweep seconds), smooth release.
function tone(t0, rate, level, { type = 'sine', f0, f1 = f0, at = 0, dur, gain = 0.5, lp = 3500, atk = 0.001, sweep = 0 }) {
    const start = t0 + at;
    const end = start + dur;
    const osc = ctx.createOscillator();
    const filter = ctx.createBiquadFilter();
    const env = ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(f0 * rate, start);
    if (f1 !== f0) osc.frequency.exponentialRampToValueAtTime(f1 * rate, start + (sweep || dur * 0.85));

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
function noise(t0, level, { kind = 'lowpass', f0, f1 = f0, at = 0, dur, gain = 0.3, q = 0.9, lp = 0, atk = 0.001 }) {
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

// ---- voices -------------------------------------------------------------------------------------------------
// Each takes (t, rate, level, opts): rate is the small random pitch variation, level the loudness, opts.v the
// round-robin variant (-1, 0, 1) so two clicks in a row never sound identical.

// A dry mechanical click: a 4 ms noise transient, a short body with a fast pitch drop, and a small ring for material.
function click(t, r, l, o = {}) {
    const v = o.v ?? 0;
    const g = o.g ?? 1;
    const bright = o.bright ?? 1;
    const body = o.pitch ?? 1;
    noise(t, l, { kind: 'bandpass', f0: 3200 * (1 + v * 0.08) * bright * r, dur: 0.005, gain: 2.2 * g, q: 0.8, atk: 0.0004 });
    tone(t, r * body, l, { f0: o.rise ? 110 : 260, f1: o.rise ? 260 : 110, sweep: 0.03, dur: 0.045, gain: 0.3 * g, lp: 3000, atk: 0.0008 });
    noise(t, l, { kind: 'bandpass', f0: (1800 + v * 180) * bright * r, dur: 0.07, gain: 2.6 * g, q: 14, atk: 0.0008 });
}

// Soft bread: a low body and a very short muffled noise.
function thump(t, r, l, o = {}) {
    const g = o.g ?? 1;
    tone(t, r, l, { f0: 150, f1: 80, sweep: 0.05, dur: 0.1, gain: 0.8 * g, lp: 700, atk: 0.001 });
    noise(t, l, { kind: 'lowpass', f0: 900, dur: 0.025, gain: 0.4 * g, atk: 0.0008 });
}

// Metal spoon: three inharmonic partials with a fast decay and a bright transient.
function clink(t, r, l, o = {}) {
    const g = o.g ?? 1;
    const at = o.at ?? 0;
    const base = (o.base ?? 1) * r;
    noise(t, l, { kind: 'highpass', f0: 5000, at, dur: 0.003, gain: 0.5 * g, atk: 0.0003 });
    [[2100, 0.3, 0.14], [3170, 0.22, 0.1], [4450, 0.14, 0.06]].forEach(([f, gain, dur]) => {
        tone(t, base * (1 + (Math.random() - 0.5) * 0.02), l, { f0: f, at, dur, gain: gain * g, lp: 9000, atk: 0.0005 });
    });
}

// Wafer: a bright noise crack with two or three tiny crackle bursts and a small body.
function snap(t, r, l, o = {}) {
    const g = o.g ?? 1;
    noise(t, l, { kind: 'bandpass', f0: 5000 * r, dur: 0.014, gain: 1.1 * g, q: 0.9, atk: 0.0005 });
    const bursts = 2 + Math.floor(Math.random() * 2);
    for (let i = 0; i < bursts; i++) {
        noise(t, l, { kind: 'bandpass', f0: 3000 + Math.random() * 4000, at: 0.006 + Math.random() * 0.025, dur: 0.005, gain: 0.6 * g, q: 1.5, atk: 0.0004 });
    }
    tone(t, r, l, { f0: 240, f1: 150, sweep: 0.02, dur: 0.035, gain: 0.35 * g, lp: 2500, atk: 0.001 });
}

// A clean bell: sine plus inharmonic partials at 2.76x and 5.4x, longer ring for the fundamental.
function bell(t, r, l, o = {}) {
    const f = o.note ?? 880;
    const g = o.g ?? 1;
    const at = o.at ?? 0;
    const dur = o.dur ?? 0.5;
    const ratios = o.ratios ?? [1, 2.76, 5.4];
    [0.4, 0.17, 0.07].forEach((gain, i) => {
        if (ratios[i] === undefined) return;
        tone(t, r, l, { f0: f * ratios[i], at, dur: dur * [1, 0.6, 0.3][i], gain: gain * g, lp: 9000, atk: 0.001 });
    });
    noise(t, l, { kind: 'bandpass', f0: 6000, at, dur: 0.003, gain: 0.25 * g, q: 0.8, atk: 0.0003 });
}

// Recorded sounds (public/audio/sfx): when a name has files here they play instead of the synthesized recipe, and a
// missing or broken file quietly falls back to the recipe. name -> [files (rotated in turn), level, pitch in semitones].
const SFX_DIR = '/audio/sfx/';
const FILES = {
    tick: [['tick-1', 'tick-2', 'tick-3'], 0.29],
    softtick: [['tick-1', 'tick-2', 'tick-3'], 0.195, 2],
    tockup: [['clink-1'], 0.3, 4],
    tockdown: [['clink-2'], 0.271, -4],
    snap: [['snap-1', 'snap-2', 'snap-3', 'snap-4'], 0.277],
    pop: [['pop'], 0.335],
    popsoft: [['pop'], 0.239],
    sprinkle: [['sprinkle-1', 'sprinkle-2'], 0.315],
    scoop: [['scoop'], 0.247],
    poof: [['poof'], 0.36],
    swish: [['swish'], 0.171],
    chime: [['chime'], 0.328],
    ding: [['ding'], 0.292],
    sparkle: [['sparkle'], 0.299],
    unlock: [['unlock'], 0.282],
    register: [['register'], 0.33],
};
const buffers = {}; // file -> AudioBuffer (decoded once, after the first gesture)
const lastFile = {};

let filesReady = null;

function loadFiles(context = ctx) {
    if (filesReady || !context) return filesReady;
    const wanted = new Set(Object.values(FILES).flatMap(([list]) => list));
    filesReady = Promise.all([...wanted].map((file) => fetch(`${SFX_DIR}${file}.mp3`)
        .then((response) => (response.ok ? response.arrayBuffer() : Promise.reject()))
        .then((data) => context.decodeAudioData(data))
        .then((buffer) => {
            buffers[file] = buffer;
        })
        .catch(() => {
            // Not there or not decodable: the synthesized recipe stays in use.
        })));
    return filesReady;
}

// Starts a recorded sound if every file of the name is loaded. Returns its length in seconds, or 0 for no file.
function playFile(name, t, level, opts) {
    const entry = FILES[name];
    if (!entry || !entry[0].every((file) => buffers[file])) return 0;

    const list = entry[0];
    const index = ((lastFile[name] ?? -1) + 1) % list.length;
    lastFile[name] = index;
    const buffer = buffers[list[index]];

    const src = ctx.createBufferSource();
    const env = ctx.createGain();
    src.buffer = buffer;
    src.playbackRate.value = semitone(entry[2] ?? 0) * (1 + (Math.random() * 2 - 1) * 0.03);
    env.gain.value = Math.max(0.0002, entry[1] * level * (opts.gain ?? 1) * dbToGain((Math.random() * 2 - 1) * 1));
    src.connect(env).connect(bus);
    src.start(t);
    return buffer.duration;
}

// name -> [total duration in seconds, builder(t0, rate, level, opts), short description for the audition page]
const RECIPES = {
    // clicks
    click: [0.09, (t, r, l, o) => click(t, r, l, o), 'noise 4 kHz 4 ms + body 260 to 110 Hz + ring 1.8 kHz'],
    tock: [0.09, (t, r, l, o) => click(t, r, l, { ...o, bright: 0.9, g: 0.9 }), 'click, dry (nav, footer)'],
    tick: [0.09, (t, r, l, o) => click(t, r, l, { ...o, bright: 1.6, g: 0.8 }), 'click, higher and short (toggles, tabs)'],
    softtick: [0.09, (t, r, l, o) => click(t, r, l, { ...o, bright: 1.5, g: 0.55 }), 'click, higher and quiet'],
    tockup: [0.09, (t, r, l, o) => click(t, r, l, { ...o, pitch: semitone(4), bright: semitone(4) }), 'click, 4 semitones up'],
    tockdown: [0.09, (t, r, l, o) => click(t, r, l, { ...o, pitch: semitone(-4), bright: semitone(-4) }), 'click, 4 semitones down'],
    blipup: [0.09, (t, r, l, o) => click(t, r, l, { ...o, rise: true, pitch: semitone(3), bright: 1.2 }), 'click with a rising body'],
    blipdown: [0.09, (t, r, l, o) => click(t, r, l, { ...o, pitch: semitone(-3), bright: 0.85 }), 'click with a falling body'],
    pop: [0.22, (t, r, l, o) => {
        click(t, r, l, { ...o, g: 0.9 });
        thump(t, r, l, { g: 0.7 });
        tone(t, r, l, { f0: 1100, at: 0.02, dur: 0.12, gain: 0.14, lp: 3000, atk: 0.002 });
    }, 'click + thump + small tail (buttons, CTA)'],
    popsoft: [0.22, (t, r, l, o) => RECIPES.pop[1](t, r, l * 0.5, o), 'pop at half level'],
    plop: [0.16, (t, r, l, o) => {
        thump(t, r, l, { g: 1 });
        click(t, r, l, { ...o, g: 0.6, bright: 0.8 });
    }, 'thump + soft click'],
    hover: [0.05, (t, r, l, o) => {
        noise(t, l, { kind: 'bandpass', f0: 3500 * r, dur: 0.006, gain: 0.5, q: 0.8, atk: 0.0004 });
        tone(t, r, l, { type: 'triangle', f0: 900, f1: 700, sweep: 0.02, dur: 0.025, gain: 0.07, lp: 3000, atk: 0.001 });
    }, 'very soft tick (hover)'],

    // materials
    thump: [0.12, (t, r, l, o) => thump(t, r, l, o), 'bread: body 150 to 80 Hz + muffled noise'],
    clink: [0.18, (t, r, l, o) => clink(t, r, l, o), 'spoon: partials 2100, 3170, 4450 Hz'],
    snap: [0.08, (t, r, l, o) => snap(t, r, l, o), 'wafer: noise crack 3 to 7 kHz + crackle'],
    puf: [0.18, (t, r, l, o) => {
        thump(t, r, l, { g: 0.9 });
        noise(t, l, { f0: 500, dur: 0.14, gain: 0.3, atk: 0.004 });
    }, 'soft bread (Studio bread)'],
    scoop: [0.2, (t, r, l, o) => {
        tone(t, r, l, { f0: 240, f1: 130, dur: 0.16, gain: 0.6, lp: 1400, atk: 0.002 });
        noise(t, l, { f0: 700, dur: 0.07, gain: 0.3, atk: 0.003 });
        clink(t, r, l, { g: 0.3, at: 0.025, base: 0.7 });
    }, 'ice cream: soft scoop + tiny spoon touch'],
    sprinkle: [0.26, (t, r, l) => {
        const count = 4 + Math.floor(Math.random() * 3);
        for (let i = 0; i < count; i++) clink(t, r, l, { g: 0.35, at: Math.random() * 0.17, base: 1.1 + Math.random() * 0.8 });
    }, 'toppings: small clinks'],
    squish: [0.14, (t, r, l) => {
        tone(t, r, l, { f0: 330, f1: 170, sweep: 0.06, dur: 0.1, gain: 0.6, lp: 900, atk: 0.003 });
        noise(t, l, { f0: 600, dur: 0.05, gain: 0.2, atk: 0.004 });
    }, 'marshmallow: soft squish'],
    gummy: [0.2, (t, r, l) => {
        tone(t, r, l, { type: 'triangle', f0: 420, f1: 620, sweep: 0.05, dur: 0.09, gain: 0.45, lp: 1500, atk: 0.004 });
        tone(t, r, l, { f0: 620, f1: 380, sweep: 0.06, dur: 0.1, at: 0.07, gain: 0.35, lp: 1400, atk: 0.004 });
        thump(t, r, l, { g: 0.3 });
    }, 'gummy: chewy rubbery press'],
    drizzle: [0.32, (t, r, l) => {
        noise(t, l, { f0: 1800, f1: 700, dur: 0.22, gain: 0.25, q: 0.7, lp: 2200, atk: 0.02 });
        for (let i = 0; i < 3; i++) {
            tone(t, r, l, { f0: 500 + Math.random() * 400, f1: 280, sweep: 0.04, at: 0.03 + Math.random() * 0.17, dur: 0.06, gain: 0.25, lp: 1800, atk: 0.004 });
        }
    }, 'chocolate sauce: liquid drizzle with small bubbles'],
    swish: [0.34, (t, r, l) => noise(t, l, { kind: 'bandpass', f0: 400, f1: 1800, dur: 0.32, gain: 0.35, q: 1.2, lp: 2600, atk: 0.04 }), 'noise sweep up'],
    whoosh: [0.28, (t, r, l, o) => {
        const closing = o.dir === 'down';
        noise(t, l, { kind: 'bandpass', f0: closing ? 1200 : 300, f1: closing ? 300 : 1200, dur: 0.26, gain: 0.32, q: 1, lp: 2200, atk: 0.05 });
    }, 'drawer sweep'],
    poof: [0.24, (t, r, l) => {
        noise(t, l, { f0: 900, f1: 250, dur: 0.18, gain: 0.45, atk: 0.003 });
        thump(t, r, l, { g: 0.6 });
    }, 'puff + thump (remove)'],

    // confirmations
    ding: [0.5, (t, r, l) => {
        bell(t, r, l, { note: 988, dur: 0.4, g: 0.6 });
        bell(t, r, l, { note: 1319, dur: 0.4, g: 0.45 });
    }, 'bell pair 988 + 1319 Hz'],
    bell: [0.6, (t, r, l, o) => bell(t, r, l, o), 'bell 880 Hz + partials 2.76x, 5.4x'],
    unlock: [0.5, (t, r, l) => {
        bell(t, r, l, { note: 880, dur: 0.3, g: 0.8 });
        bell(t, r, l, { note: 1318, dur: 0.4, at: 0.09, g: 0.8 });
    }, 'two bells up'],
    register: [0.7, (t, r, l) => {
        bell(t, r, l, { note: 1047, dur: 0.5, g: 0.9 });
        bell(t, r, l, { note: 1568, dur: 0.5, at: 0.05, g: 0.75 });
        thump(t, r, l, { g: 0.35 });
    }, 'warm full bell + soft body'],
    chime: [0.7, (t, r, l) => {
        bell(t, r, l, { note: 784, dur: 0.5, g: 0.9 });
        bell(t, r, l, { note: 1175, dur: 0.5, at: 0.1, g: 0.8 });
    }, 'two bells 784 + 1175 Hz'],
    marimba: [0.8, (t, r, l) => {
        [523, 659, 784, 1047].forEach((f, i) => bell(t, r, l, { note: f, dur: 0.25, at: i * 0.11, g: 0.8, ratios: [1, 3.9] }));
    }, 'four wooden notes'],
    sparkle: [0.6, (t, r, l) => {
        [660, 880, 1175, 1568, 2093].forEach((f, i) => bell(t, r, l, { note: f, dur: 0.25, at: i * 0.045, g: 0.55, ratios: [1, 2.76] }));
    }, 'five rising bells'],
};

// Loudness per recipe, measured offline so the loudest click peaks near -10 dBFS (hover near -26) before the
// limiter. Re-measure with /sfx-lab after changing a voice.
const TRIM = {"click": 0.162, "tock": 0.21, "tick": 0.174, "softtick": 0.171, "tockup": 0.128, "tockdown": 0.184, "blipup": 0.15, "blipdown": 0.191, "pop": 0.181, "popsoft": 0.224, "plop": 0.219, "hover": 0.12, "thump": 0.254, "clink": 0.258, "snap": 0.231, "puf": 0.314, "scoop": 0.323, "sprinkle": 0.422, "squish": 0.319, "gummy": 0.316, "drizzle": 0.63, "swish": 1.009, "whoosh": 1.23, "poof": 0.355, "ding": 0.367, "bell": 0.32, "unlock": 0.384, "register": 0.265, "chime": 0.338, "marimba": 0.437, "sparkle": 0.536, "plopding": 1.04};

// plop then ding, the quick add on the menu: both parts are scheduled on the audio clock, not with a timer.
const SEQUENCES = { plopding: [['plop', 0], ['ding', 0.1]] };

// Round-robin: each recipe cycles through three variants and never repeats the one before.
const lastVariant = {};
function nextVariant(name) {
    const v = ((lastVariant[name] ?? -1) + 2) % 3 - 1; // -1, 1, 0, -1 ...
    lastVariant[name] = v;
    return v;
}

function build(name, t, level, opts) {
    const recipe = RECIPES[name];
    if (!recipe) return 0;
    const fileLength = playFile(name, t, level, {});
    if (fileLength) return fileLength;
    const rate = 1 + (Math.random() * 2 - 1) * 0.035; // pitch +-3.5 %
    const jitter = dbToGain((Math.random() * 2 - 1) * 1); // gain +-1 dB
    recipe[1](t, rate, level * jitter * (TRIM[name] ?? 1), { ...opts, v: nextVariant(name) });
    return recipe[0];
}

function play(name, opts = {}) {
    if (muted || document.hidden || !ctx || !bus) return;

    if (SEQUENCES[name]) {
        if (activeVoices >= MAX_VOICES) return;
        const t = ctx.currentTime + 0.001;
        const level = (opts.gain ?? 1) * (TRIM[name] ?? 1);
        let total = 0;
        try {
            SEQUENCES[name].forEach(([part, delay]) => {
                total = Math.max(total, delay + build(part, t + delay, level, opts));
            });
        } catch {
            // A sound must never break the page.
        }
        activeVoices++;
        setTimeout(() => (activeVoices = Math.max(0, activeVoices - 1)), (total + 0.05) * 1000);
        window.brreadSfx?.onPlay?.(name, opts);
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

    // Reduced motion keeps the confirmations and softens everything else by 40 %.
    const level = (opts.gain ?? 1) * (reducedMotion?.matches && !ESSENTIAL.has(name) ? 0.6 : 1);

    activeVoices++;
    setTimeout(() => (activeVoices = Math.max(0, activeVoices - 1)), (recipe[0] + 0.05) * 1000);

    try {
        build(name, ctx.currentTime + 0.001, level, opts);
    } catch {
        // A sound must never break the page.
    }

    window.brreadSfx?.onPlay?.(name, opts);
}

// Renders one recipe offline (no sound) for the audition page and for level checks.
async function render(name, opts = {}) {
    const OfflineCtx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    const recipe = RECIPES[name] ?? RECIPES[SEQUENCES[name]?.[0]?.[0]];
    if (!OfflineCtx || !recipe) return null;

    const seconds = (SEQUENCES[name] ? 0.7 : recipe[0]) + 0.35;
    const offline = new OfflineCtx(1, Math.ceil(48000 * seconds), 48000);
    await loadFiles(offline);
    const saved = { ctx, bus, noiseBuffer };

    try {
        const rendered = buildGraph(offline);
        ctx = offline;
        bus = rendered.master;
        noiseBuffer = rendered.noise;
        const t = 0.01;
        if (SEQUENCES[name]) SEQUENCES[name].forEach(([part, delay]) => build(part, t + delay, (opts.gain ?? 1) * (TRIM[name] ?? 1), opts));
        else build(name, t, opts.gain ?? 1, opts);
        return await offline.startRendering();
    } finally {
        ({ ctx, bus, noiseBuffer } = saved);
    }
}

function names() {
    return [...Object.keys(RECIPES), ...Object.keys(SEQUENCES)];
}

function describe() {
    return Object.fromEntries([
        ...Object.entries(RECIPES).map(([name, [duration, , text]]) => [name, { duration, text }]),
        ...Object.entries(SEQUENCES).map(([name, parts]) => [name, { duration: 0.7, text: parts.map(([part, delay]) => `${part}@${delay}`).join(' + ') }]),
    ]);
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
    if (graph) graph.master.gain.value = volume;
}

function setSmallSpeaker(value) {
    smallSpeaker = Boolean(value);
    applySmallSpeaker();
}

// ---- delegation ----------------------------------------------------------------------------------------------

// [selector, sound, options]. The innermost element that matches (or carries data-sfx) wins.
const CLICK_REGISTRY = [
    ['.menu-tile', 'snap'],
    ['.btn-chunky', 'pop'],
];
// No automatic hover sound: sounds are for things that do something, not for passing the mouse over a page.
const HOVER_REGISTRY = [];

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

// Hover ticks: never on touch screens or with reduced motion, very soft, and spaced out so sweeping the mouse
// over many elements stays quiet.
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
    if (now - lastHover < HOVER_GAP_MS) return;
    lastHover = now;
    play(hit.name, { gain: 0.8 });
});

window.addEventListener('storage', (event) => {
    if (event.key === STORE_KEY) {
        muted = event.newValue === '1';
        syncButtons();
    }
});

document.addEventListener('DOMContentLoaded', syncButtons);
document.addEventListener('alpine:initialized', syncButtons);

window.brreadSfx = { play, setMuted, toggle, isMuted: () => muted, setVolume, context: () => ensureContext(), render, names, describe, setSmallSpeaker };
