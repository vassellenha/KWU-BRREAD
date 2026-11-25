// Background music for the customer pages: window.brreadBgm.toggle(), setMuted(bool), isMuted(), isAvailable().
// Web Audio only, no loop=true: each cycle hands over to a second source with an equal-power crossfade, and the
// position is derived from a stored start time so the music carries on across page loads instead of restarting.
// Never on a staff, superadmin, trap or sign-in page: no context, no fetch, no button, no ducking.
const BLOCKED = /^\/(staff|superadmin|admin|administrator|wp-admin|login|dashboard|panel|cpanel|phpmyadmin|backend)(\/|$)/i;
const allowed = !BLOCKED.test(location.pathname) && document.body?.hasAttribute('data-customer-site');

if (allowed) {
const SRC = '/audio/brread-bgm.mp3';
const MUTE_KEY = 'brread-bgm-muted';
const POS_KEY = 'brread-bgm-pos';
const HINT_KEY = 'brread-bgm-hinted';
const TARGET = 0.45;
const FADE_IN = 0.8;
const FADE_OUT = 0.4;
const CROSSFADE = 2.5;
const SILENCE = 10 ** (-50 / 20);
const DUCK_LEVEL = 0.6;
const DUCK_ON = new Set(['register', 'marimba', 'chime']);

const store = {
    get(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* storage unavailable */ }
    },
};

let muted = store.get(MUTE_KEY) === '1';
let available = null; // null: still checking
let ctx = null;
let level = null; // mute fades, 0..TARGET
let duck = null; // ducking under loud sound effects
let buffer = null;
let trimStart = 0;
let trimLength = 0;
let loading = null;
let playing = false;
let voices = [];
let nextAt = 0;
let timer = null;
let startedOnce = false;

const fadeCurve = (rising) => Float32Array.from({ length: 48 }, (_, i) => Math.sin((rising ? i / 47 : 1 - i / 47) * Math.PI / 2));

function buttons() {
    return document.querySelectorAll('[data-bgm-toggle]');
}

function syncButtons() {
    const on = available === true && !muted;
    buttons().forEach((button) => {
        button.hidden = available !== true;
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.setAttribute('aria-label', on ? 'Matikan musik latar' : 'Nyalakan musik latar');
        button.title = on ? 'Matikan musik latar' : 'Nyalakan musik latar';
        button.classList.toggle('is-playing', on && playing);
    });
}

function setAvailable(value) {
    available = value;
    syncButtons();
}

// ---- loading -------------------------------------------------------------------------------------------------

async function checkFile() {
    try {
        const response = await fetch(SRC, { method: 'HEAD', cache: 'no-cache' });
        const type = response.headers.get('content-type') || '';
        setAvailable(response.ok && type.startsWith('audio'));
        if (available) hint();
    } catch {
        setAvailable(false);
    }
}

function context() {
    if (ctx) return ctx;
    const shared = window.brreadSfx?.context?.();
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    ctx = shared || (AudioCtx ? new AudioCtx() : null);
    if (!ctx) return null;

    level = ctx.createGain();
    level.gain.value = 0;
    duck = ctx.createGain();
    level.connect(duck).connect(ctx.destination);
    return ctx;
}

// The decoded buffer minus the silence at both ends, so the loop point has no gap.
function trim(decoded) {
    const channels = Array.from({ length: decoded.numberOfChannels }, (_, i) => decoded.getChannelData(i));
    const loud = (i) => channels.some((data) => Math.abs(data[i]) > SILENCE);
    let first = 0;
    let last = decoded.length - 1;
    while (first < last && !loud(first)) first++;
    while (last > first && !loud(last)) last--;
    trimStart = first / decoded.sampleRate;
    trimLength = (last - first + 1) / decoded.sampleRate;

    const dev = Number(store.get('brreadBgmDevLength')); // dev only: a short loop to watch the crossfade
    if (dev > 2 * CROSSFADE && dev < trimLength) trimLength = dev;
}

function load() {
    if (buffer) return Promise.resolve(true);
    loading ??= (async () => {
        try {
            const response = await fetch(SRC);
            if (!response.ok) throw new Error('missing');
            const decoded = await context().decodeAudioData(await response.arrayBuffer());
            trim(decoded);
            buffer = decoded;
            return trimLength > CROSSFADE * 3;
        } catch {
            return false;
        }
    })();
    return loading.then((ok) => {
        if (!ok) setAvailable(false);
        return ok;
    });
}

// ---- playing -------------------------------------------------------------------------------------------------

const period = () => trimLength - Math.min(CROSSFADE, trimLength / 4);
const cross = () => Math.min(CROSSFADE, trimLength / 4);

function position() {
    let saved = null;
    try { saved = JSON.parse(store.get(POS_KEY)); } catch { /* none yet */ }
    const length = Math.round(trimLength * 1000);
    if (!saved || saved.length !== length || !Number.isFinite(saved.start)) {
        saved = { start: Date.now(), length };
        store.set(POS_KEY, JSON.stringify(saved));
    }
    const p = period();
    return (((Date.now() - saved.start) / 1000) % p + p) % p;
}

function startVoice(when, offset, fadeIn) {
    const source = ctx.createBufferSource();
    const gain = ctx.createGain();
    source.buffer = buffer;
    source.connect(gain).connect(level);

    gain.gain.setValueAtTime(0, when);
    if (fadeIn > 0) gain.gain.setValueCurveAtTime(fadeCurve(true), when, fadeIn);
    else gain.gain.setValueAtTime(1, when);

    // The tail of this voice fades out while the next one fades in.
    const remaining = trimLength - offset;
    const fadeStart = when + remaining - cross();
    if (remaining > cross() + fadeIn) gain.gain.setValueCurveAtTime(fadeCurve(false), fadeStart, cross());

    source.start(when, trimStart + offset);
    source.stop(when + remaining + 0.05);
    const voice = { source, gain };
    voices.push(voice);
    source.onended = () => {
        voices = voices.filter((v) => v !== voice);
        try { gain.disconnect(); } catch { /* already gone */ }
    };
    nextAt = when + (period() - offset);
}

// A light look-ahead on the audio clock: the next voice is scheduled a little before it is needed.
function tick() {
    if (!playing || !ctx || ctx.state !== 'running') return;
    if (ctx.currentTime >= nextAt - 1) startVoice(nextAt, 0, cross());
}

function rampLevel(to, seconds) {
    const now = ctx.currentTime;
    level.gain.cancelScheduledValues(now);
    level.gain.setValueAtTime(level.gain.value, now);
    level.gain.linearRampToValueAtTime(to, now + seconds);
}

async function begin() {
    if (playing || muted || available === false || document.hidden || !context()) return;
    if (document.getElementById('brread-intro')) return; // wait for the intro; the next gesture or the poll starts it
    playing = true; // claimed up front: two quick gestures must not start two copies
    if (!(await load())) {
        playing = false;
        return;
    }
    if (muted || document.hidden) {
        playing = false;
        return;
    }
    if (ctx.state === 'suspended') await ctx.resume().catch(() => {});
    startedOnce = true;
    startVoice(ctx.currentTime + 0.05, position(), FADE_IN);
    rampLevel(TARGET, FADE_IN);
    timer = setInterval(tick, 250);
    syncButtons();
}

function end() {
    if (!playing) return;
    playing = false;
    clearInterval(timer);
    syncButtons();
    if (!ctx) return;
    rampLevel(0, FADE_OUT);
    const stopping = voices;
    voices = [];
    setTimeout(() => stopping.forEach(({ source }) => { try { source.stop(); } catch { /* already stopped */ } }), FADE_OUT * 1000 + 60);
}

function setMuted(value) {
    muted = Boolean(value);
    store.set(MUTE_KEY, muted ? '1' : '0');
    if (muted) end();
    else begin();
    syncButtons();
}

function toggle() {
    setMuted(!muted);
}

// ---- ducking under the loud sound effects -------------------------------------------------------------------

function duckFor(seconds) {
    if (!ctx || !duck) return;
    const now = ctx.currentTime;
    duck.gain.cancelScheduledValues(now);
    duck.gain.setValueAtTime(duck.gain.value, now);
    duck.gain.linearRampToValueAtTime(DUCK_LEVEL, now + 0.08);
    duck.gain.setValueAtTime(DUCK_LEVEL, now + seconds);
    duck.gain.linearRampToValueAtTime(1, now + seconds + 0.6);
}

function hookDucking() {
    const sfx = window.brreadSfx;
    if (!sfx) return;
    const previous = sfx.onPlay;
    sfx.onPlay = (name, opts) => {
        previous?.(name, opts);
        if (DUCK_ON.has(name)) duckFor(name === 'marimba' ? 0.9 : 0.6);
    };
}

// ---- first visit hint, gestures, visibility, button ----------------------------------------------------------

function hint() {
    if (store.get(HINT_KEY)) return;
    store.set(HINT_KEY, '1');
    buttons().forEach((button) => {
        button.classList.add('bgm-hint');
        setTimeout(() => button.classList.remove('bgm-hint'), 4000);
    });
}

['pointerdown', 'keydown', 'touchstart'].forEach((type) => {
    window.addEventListener(type, () => {
        if (muted || available !== true) return;
        if (!playing) begin();
        else if (ctx?.state === 'suspended' && !document.hidden) ctx.resume().catch(() => {});
    }, { passive: true, capture: true });
});

document.addEventListener('visibilitychange', () => {
    if (!ctx || !playing) return;
    if (document.hidden) ctx.suspend().catch(() => {});
    else ctx.resume().catch(() => {});
});

window.addEventListener('storage', (event) => {
    if (event.key !== MUTE_KEY) return;
    muted = event.newValue === '1';
    if (muted) end();
    syncButtons();
});

// The intro covers the page for a few seconds; the music starts once it is gone.
const introWatch = setInterval(() => {
    if (document.getElementById('brread-intro')) return;
    clearInterval(introWatch);
    if (startedOnce || muted || available !== true) return;
    // Only begins by itself when the browser already allows audio (a previous page had the gesture).
    if (navigator.userActivation?.hasBeenActive) begin();
}, 300);

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-bgm-toggle]') : null;
    if (!button) return;
    store.set(HINT_KEY, '1');
    button.classList.remove('bgm-hint');
    window.brreadSfx?.play?.('tick');
    toggle();
});

hookDucking();

// This module is imported after the page has loaded, so it may be too late for DOMContentLoaded.
const init = () => {
    syncButtons();
    checkFile();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
document.addEventListener('alpine:initialized', syncButtons);

window.brreadBgm = { toggle, setMuted, isMuted: () => muted, isAvailable: () => available === true };
}
