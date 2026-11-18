// What a person meets when they guess passwords on /staff or /superadmin by hand.
// Wrong password 1, 2 and 3 each pop up a cheeky terminal window with the "fahh" sound (the
// third one reads out their real IP, city and provider); on the fourth the terminal grows to
// fill the screen, switches on like an old TV and, Rickroll style, plays the "Kewer Kewer" video
// while sign-in is paused for that address (1, 3, then 5 minutes; the server keeps the real
// lock). Loaded on demand from app.js.

const VIDEO = '/video/prank-kewer.mp4';
const HIT = '/audio/lockdown-hit.mp3';
const FAHH = '/audio/prank-fahh.mp3';
const VICTORY = '/audio/prank-fanfare.mp3';
const SANS = '/audio/prank-sanstalk.mp3';
const NOTIFY = '/audio/prank-winnotify.mp3';
const XPERROR = '/audio/prank-xperror.mp3';
const BSOD = '/video/prank-bsod.mp4';

// Kewer lasts at least this long after a blue screen, and spamming escape this often brings the blue screen.
const AFTER_BSOD = 120;
const SPAM_TRIES = 6;
const SPAM_WINDOW = 15000;
const BSOD_MS = 10000;
const TOAST_MS = 15000;

const S = {
    z: 10,
    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    context: null,
    bytes: {},
    buffers: {},
    root: null,
    busy: null,
    party: null,
    terms: new Set(),
    tv: null,
    place: '',
    hits: 0,
    tries: [],
    ip: null,
    kewer: null,
    bsod: false,
    toast: null,
};

const later = (fn, ms) => window.setTimeout(fn, ms);
const wait = (ms) => new Promise((resolve) => later(resolve, ms));
const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

/* ---------- loading ---------- */

let preloading = null;

/** Fetches the sound once, so the first wrong password reacts at once. */
export function preload() {
    if (preloading) return preloading;

    preloading = Promise.all([FAHH, SANS].map(async (url) => {
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            S.bytes[url] = await response.arrayBuffer();
        } catch {
            // Without the sound the scenes still play.
        }
    }));

    return preloading;
}

/* ---------- sound ---------- */

function adopt(context) {
    if (context && context.state !== 'closed') {
        if (S.context && S.context !== context && S.context.state !== 'closed') {
            context.close?.();
        } else {
            S.context = context;
        }
    }

    if (!S.context || S.context.state === 'closed') {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            S.context = AudioContextClass ? new AudioContextClass() : null;
        } catch {
            S.context = null;
        }
    }

    S.context?.resume?.();

    return S.context;
}

async function buffer(url) {
    if (S.buffers[url]) return S.buffers[url];

    await preload();
    if (!S.bytes[url]) {
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            S.bytes[url] = await response.arrayBuffer();
        } catch {
            return null;
        }
    }
    const bytes = S.bytes[url];
    if (!bytes || !S.context) return null;

    try {
        // decodeAudioData detaches the bytes it is given, so it gets a copy.
        S.buffers[url] = await S.context.decodeAudioData(bytes.slice(0));
    } catch {
        return null;
    }

    return S.buffers[url];
}

async function playSound(url, { loop = false, volume = 1, analyser = false, rate = 1, pan = 0 } = {}) {
    const context = S.context;
    const audio = await buffer(url);
    if (!context || !audio) return null;

    const source = context.createBufferSource();
    const gain = context.createGain();
    source.buffer = audio;
    source.loop = loop;
    source.playbackRate.value = rate;
    gain.gain.value = volume;
    source.connect(gain);

    let meter = null;
    if (analyser) {
        meter = context.createAnalyser();
        meter.fftSize = 256;
        gain.connect(meter);
    }

    if (pan && context.createStereoPanner) {
        const panner = context.createStereoPanner();
        panner.pan.value = pan;
        gain.connect(panner).connect(context.destination);
    } else {
        gain.connect(context.destination);
    }
    source.start();

    return {
        meter,
        stop(fade = 0.4) {
            try {
                gain.gain.setTargetAtTime(0, context.currentTime, fade / 4);
                source.stop(context.currentTime + fade);
            } catch {
                // Already stopped.
            }
        },
    };
}

const fahh = () => playSound(FAHH, { volume: 0.95 });

/** Makes sure sound can play: creates or wakes the audio context and decodes what the scenes need. */
function awake() {
    adopt(null);
    S.context?.resume?.().catch?.(() => {});
    [HIT, FAHH, SANS, NOTIFY, XPERROR].forEach((url) => buffer(url));
}

/* ---------- Sans: every letter the terminals type is a little voice blip ---------- */

let lastBlip = 0;

/** One short grain of the voice clip at a given time, pitch and length (the clip is a steady voice, so any slice sounds like him). */
function sansAt(when, rate = 1, length = 0.08, volume = 0.5) {
    const context = ready();
    const audio = S.buffers[SANS];
    if (!context || !audio) return;

    const source = context.createBufferSource();
    const gain = context.createGain();
    source.buffer = audio;
    source.playbackRate.value = rate;
    gain.gain.setValueAtTime(0.0001, when);
    gain.gain.linearRampToValueAtTime(volume, when + 0.006);
    gain.gain.setValueAtTime(volume, when + length - 0.022);
    gain.gain.linearRampToValueAtTime(0.0001, when + length);
    source.connect(gain).connect(context.destination);
    source.start(when, 0.86 + Math.random() * 0.5, length + 0.02);
    source.stop(when + length + 0.03);
}

/** The typing sound of the terminals: hacker keyboard clacks, now and then a digital blip. */
function typeKey() {
    const now = performance.now();
    if (now - lastBlip < 40) return;
    lastBlip = now;
    clack(0.12);
    if (Math.random() < 0.16) tickSound();
}

/* ---------- shared pieces ---------- */

function stage() {
    if (S.root?.isConnected) return S.root;

    S.root = el('div', 'prank');
    S.root.setAttribute('aria-live', 'polite');
    document.body.append(S.root);

    return S.root;
}

function clearStage() {
    S.root?.replaceChildren();
    S.root?.classList.remove('is-dim');
}

function card() {
    return document.getElementById('login-card');
}

function shake(target, strength = 10, duration = 420) {
    if (S.reduced || !target) return;

    const steps = [0, -1, 0.8, -0.6, 0.4, -0.2, 0].map((f) => ({ transform: `translateX(${f * strength}px)` }));
    target.animate(steps, { duration, easing: 'ease-out' });
}

const clockText = (secs) => `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;

/* ---------- the terminal window ---------- */

/**
 * Builds the window every terminal in the prank wears: an ice cream sandwich. The title bar is the
 * top slice of bread, the text sits in the strawberry cream and a second slice closes it below.
 * Both slices are handles to drag it by.
 */
function sandwich(title) {
    const box = el('div', 'prank-term');

    const bar = el('div', 'prank-term-bar');
    const dots = el('span', 'prank-term-dots');
    dots.append(el('i'), el('i'), el('i'));
    const name = el('span', 'prank-term-title', title);
    const close = el('button', 'prank-term-x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close');
    bar.append(dots, name, close);

    const body = el('div', 'prank-term-body');
    const foot = el('div', 'prank-term-foot');
    box.append(bar, body, foot);
    draggable(box, [bar, foot]);

    return { box, bar, body, close };
}

/** Lets a window be picked up by its handles and dropped anywhere on screen (mouse, pen and touch). */
function draggable(box, handles) {
    let drag = null;

    const down = (event) => {
        if (event.button > 0 || event.target.closest?.('button')) return;
        const rect = box.getBoundingClientRect();
        drag = { id: event.pointerId, dx: event.clientX - rect.left, dy: event.clientY - rect.top, handle: event.currentTarget };
        event.currentTarget.setPointerCapture?.(event.pointerId);
        box.style.zIndex = String(++S.z);
        box.classList.add('is-dragging');
        event.preventDefault();
    };
    const move = (event) => {
        if (!drag || event.pointerId !== drag.id) return;
        const x = event.clientX - drag.dx;
        const y = event.clientY - drag.dy;
        box.style.left = `${Math.min(Math.max(x, 90 - box.offsetWidth), window.innerWidth - 90)}px`;
        box.style.top = `${Math.min(Math.max(y, 0), window.innerHeight - 44)}px`;
    };
    const up = (event) => {
        if (!drag || event.pointerId !== drag.id) return;
        drag.handle.releasePointerCapture?.(event.pointerId);
        drag = null;
        box.classList.remove('is-dragging');
    };

    handles.forEach((handle) => {
        handle.addEventListener('pointerdown', down);
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', up);
        handle.addEventListener('pointercancel', up);
    });
}

/**
 * A little terminal that pops up next to the form and types a script, every letter with a voice
 * blip: ['cmd', text] types a command, ['out'|'ok'|'err'|'say'|'big', text] types a line,
 * ['bar', label] runs a progress bar, ['wait', ms] pauses.
 */
function terminal(title, { width = 380, place = 'right', dark = false } = {}) {
    const { box, body, close } = sandwich(title);
    if (dark) box.classList.add('prank-term--dark');
    box.style.width = `${Math.min(width, window.innerWidth - 36)}px`;
    box.setAttribute('role', 'status');

    const cursor = el('span', 'prank-term-cursor');
    body.append(cursor);
    stage().append(box);
    buffer(SANS);

    // Where it pops up: beside the form on wide screens, under the header on narrow ones.
    const form = card()?.getBoundingClientRect();
    const w = box.offsetWidth;
    const h = Math.max(box.offsetHeight, 240);
    let x = (window.innerWidth - w) / 2;
    let y = (window.innerHeight - h) / 2;
    if (form && place !== 'center' && window.innerWidth >= w * 2 + form.width) {
        x = place === 'right' ? form.right + 28 : form.left - w - 28;
        y = form.top + (place === 'right' ? 10 : 70);
    } else if (form && place !== 'center') {
        y = Math.max(12, form.top - 40);
    }
    box.style.left = `${Math.max(12, Math.min(x, window.innerWidth - w - 12))}px`;
    box.style.top = `${Math.max(12, Math.min(y, window.innerHeight - h - 12))}px`;

    const pop = S.reduced
        ? box.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 150 })
        : box.animate([
            { transform: 'scale(.3) rotate(-6deg)', opacity: 0 },
            { transform: 'scale(1.06) rotate(1.5deg)', opacity: 1, offset: 0.6 },
            { transform: 'scale(.98) rotate(-.5deg)', offset: 0.82 },
            { transform: 'scale(1) rotate(0)' },
        ], { duration: 460, easing: 'cubic-bezier(.2,.9,.3,1)' });

    let closed = false;
    const term = {
        box,
        body,
        get closed() {
            return closed;
        },

        async run(script) {
            await pop.finished.catch(() => {});
            for (const [kind, text] of script) {
                if (closed) return;
                if (kind === 'wait') {
                    await wait(text);
                } else if (kind === 'bar') {
                    await progress(body, cursor, text);
                } else {
                    const line = el('p', `is-${kind}`);
                    body.insertBefore(line, cursor);
                    await typeInto(line, text, kind === 'cmd' ? 36 : 26, typeKey, kind === 'cmd' ? 30 : 18);
                    await wait(kind === 'cmd' ? 220 : kind === 'big' ? 360 : 200);
                }
                body.scrollTop = body.scrollHeight;
            }
        },

        async dismiss() {
            if (closed) return;
            closed = true;
            S.terms.delete(term);
            if (S.current === term) S.current = null;
            await box.animate([
                { transform: 'scale(1) translateY(0)', opacity: 1 },
                { transform: 'scale(.6) translateY(40px)', opacity: 0 },
            ], { duration: S.reduced ? 1 : 260, easing: 'cubic-bezier(.6,0,.9,.5)', fill: 'forwards' }).finished.catch(() => {});
            box.remove();
        },
    };

    close.addEventListener('click', () => term.dismiss());
    S.current = term;
    S.terms.add(term);

    return term;
}

async function typeInto(line, text, speed, onKey = null, jitter = 30) {
    if (S.reduced) {
        line.textContent = text;
        return;
    }
    let count = 0;
    for (const letter of text) {
        line.textContent += letter;
        if (letter.trim() && onKey && count++ % 2 === 0) onKey();
        await wait(speed + Math.random() * jitter);
    }
}

async function progress(body, cursor, label) {
    const line = el('p', 'is-out');
    body.insertBefore(line, cursor);
    const width = 16;
    for (let step = 0; step <= width; step++) {
        line.textContent = `${label} [${'#'.repeat(step)}${'.'.repeat(width - step)}] ${Math.round((step / width) * 100)}%`;
        await wait(S.reduced ? 0 : 40 + Math.random() * 70);
    }
}

/* ---------- the four scenes ---------- */

async function sceneOne() {
    shake(card());
    fahh();
    const term = terminal('brread@counter: ~', { place: 'right' });
    await term.run([
        ['cmd', 'sudo login superadmin'],
        ['err', 'access denied: wrong password'],
        ['say', 'Pencet sekali lagi wak kalo hoki kamu dapet ayam bakar'],
    ]);
}

async function sceneTwo() {
    shake(card(), 14);
    fahh();
    const term = terminal('guess_password.exe', { place: 'left' });
    await term.run([
        ['cmd', './cari_ayam --mode=random'],
        ['bar', 'nyari ayam'],
        ['err', 'ayam not found'],
        ['say', 'yahh ayam nya kabur.. coba tebak lagi deh passwordnya kali aja bener'],
        ['out', 'coba admin12345678 dehh'],
    ]);
}

// 3: not a window this time. The screen drops into a red trace: a crosshair locks on, their real
// IP decodes in big glitching digits, their city is stamped underneath, and a whisper types out.
async function sceneThree(scene) {
    // The video is next; start fetching it now.
    if (!S.warmVideo) {
        S.warmVideo = document.createElement('video');
        S.warmVideo.preload = 'auto';
        S.warmVideo.muted = true;
        S.warmVideo.src = VIDEO;
    }

    shake(card(), 16);
    const root = stage();
    const trace = el('div', 'prank-trace');
    const scan = el('div', 'prank-trace-scan');
    const sight = el('div', 'prank-trace-sight');
    sight.append(el('i'), el('i'), el('b'));
    const label = el('p', 'prank-trace-label', 'melacak sinyal...');
    const ip = el('p', 'prank-trace-ip', '');
    ip.dataset.text = '';
    const city = el('p', 'prank-trace-city', '');
    const whisper = el('p', 'prank-trace-whisper', '');
    const info = el('div', 'prank-trace-info');
    info.append(label, ip, city, whisper);
    trace.append(scan, sight, info);
    root.append(trace);

    let closed = false;
    const done = async () => {
        if (closed) return;
        closed = true;
        if (S.current === handle) S.current = null;
        drone?.stop();
        staticSound(0.45);
        trace.classList.add('is-leaving');
        await wait(S.reduced ? 1 : 420);
        trace.remove();
    };
    const handle = { dismiss: done };
    S.current = handle;

    buffer(HIT);
    staticSound(0.9);
    const drone = dread();
    const place = [scene.city, scene.country].filter(Boolean).join(', ');

    if (S.reduced) {
        label.textContent = 'target terkunci';
        ip.textContent = scene.ip || '???';
        city.textContent = place.toUpperCase();
        city.dataset.text = city.textContent;
        whisper.textContent = `yey selamat kita dapet IP kamu, ternyata kamu orang ${scene.city || 'entah mana'}...`;
    } else {
        sight.classList.add('is-hunting');
        riser(1.3);
        chirps(16);
        await wait(1300);
        if (closed) return;
        sight.classList.add('is-locked');
        label.textContent = 'target terkunci';
        playSound(HIT, { volume: 0.8 });
        heartbeat(5);

        await decodeInto(ip, scene.ip || '???', tickSound);
        if (closed) return;
        trace.classList.add('is-hit');

        await wait(350);
        city.textContent = (place || 'lokasi tidak diketahui').toUpperCase();
        city.dataset.text = city.textContent;
        city.classList.add('is-in');
        stinger();
        await wait(450);

        await wait(700);
        if (closed) return;
        modem(1.4);
        const breath = whisperSound(5.5);
        await typeInto(whisper, `yey selamat kita dapet IP kamu, ternyata kamu orang ${scene.city || 'entah mana'}...`, 48, clack);
        alarm(1.8);
        breath?.stop();
    }

    await wait(2600);
    await done();
    curse();
}

// Digits roll and settle one by one; the text is mirrored in data-text for the red/cyan split.
function decodeInto(node, text, onTick = null) {
    return new Promise((resolve) => {
        const pool = '0123456789';
        let frame = 0;
        const total = text.length * 4 + 10;
        const tick = () => {
            frame++;
            const shown = [...text].map((letter, index) => (
                frame > index * 4 + 10 || letter === '.' || letter === ':' ? letter : pool[Math.floor(Math.random() * pool.length)]
            )).join('');
            node.textContent = shown;
            node.dataset.text = shown;
            if (onTick && frame % 2 === 0) onTick();
            if (frame < total) {
                window.setTimeout(tick, 33);
            } else {
                resolve();
            }
        };
        tick();
    });
}

/* ---------- horror sounds for the trace (Web Audio, nothing to download) ---------- */

const ready = () => (S.context && S.context.state === 'running' ? S.context : null);

/* A low, slowly beating drone under the whole trace. */
function dread() {
    const context = ready();
    if (!context) return null;
    const now = context.currentTime;
    const out = context.createGain();
    out.gain.setValueAtTime(0.0001, now);
    out.gain.exponentialRampToValueAtTime(0.22, now + 1.2);
    const low = context.createBiquadFilter();
    low.type = 'lowpass';
    low.frequency.value = 320;
    low.connect(out).connect(context.destination);
    const voices = [55, 58.3, 82.4, 110.8].map((frequency) => {
        const osc = context.createOscillator();
        osc.type = 'sawtooth';
        osc.frequency.value = frequency;
        osc.connect(low);
        osc.start(now);
        return osc;
    });
    // A slow wobble on the filter makes it breathe.
    const lfo = context.createOscillator();
    const depth = context.createGain();
    lfo.frequency.value = 0.35;
    depth.gain.value = 140;
    lfo.connect(depth).connect(low.frequency);
    lfo.start(now);

    return {
        stop() {
            const t = context.currentTime;
            out.gain.cancelScheduledValues(t);
            out.gain.setValueAtTime(out.gain.value, t);
            out.gain.exponentialRampToValueAtTime(0.0001, t + 0.6);
            [...voices, lfo].forEach((osc) => osc.stop(t + 0.65));
        },
    };
}

/* A dissonant rising swell while the crosshair hunts. */
function riser(seconds) {
    const context = ready();
    if (!context) return;
    const now = context.currentTime;
    const gain = context.createGain();
    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.16, now + seconds * 0.9);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + seconds + 0.05);
    gain.connect(context.destination);
    [220, 233.1, 311.1].forEach((frequency) => {
        const osc = context.createOscillator();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(frequency, now);
        osc.frequency.exponentialRampToValueAtTime(frequency * 3.2, now + seconds);
        osc.connect(gain);
        osc.start(now);
        osc.stop(now + seconds + 0.1);
    });
}

/* A tiny dry click for each step of the digits rolling. */
function tickSound() {
    const context = ready();
    if (!context) return;
    const now = context.currentTime;
    const osc = context.createOscillator();
    const gain = context.createGain();
    osc.type = 'square';
    osc.frequency.value = 1400 + Math.random() * 900;
    gain.gain.setValueAtTime(0.04, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.03);
    osc.connect(gain).connect(context.destination);
    osc.start(now);
    osc.stop(now + 0.035);
}

/* The city lands: a sub boom under a detuned, out-of-tune chord that rings off. */
function stinger() {
    const context = ready();
    if (!context) return;
    const now = context.currentTime;

    const boom = context.createOscillator();
    const boomGain = context.createGain();
    boom.type = 'sine';
    boom.frequency.setValueAtTime(90, now);
    boom.frequency.exponentialRampToValueAtTime(32, now + 0.9);
    boomGain.gain.setValueAtTime(0.7, now);
    boomGain.gain.exponentialRampToValueAtTime(0.0001, now + 1.2);
    boom.connect(boomGain).connect(context.destination);
    boom.start(now);
    boom.stop(now + 1.25);

    [466.2, 493.9, 698.5, 739.9].forEach((frequency) => {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(frequency * 1.02, now);
        osc.frequency.exponentialRampToValueAtTime(frequency * 0.97, now + 2.2);
        gain.gain.setValueAtTime(0.045, now);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 2.4);
        osc.connect(gain).connect(context.destination);
        osc.start(now);
        osc.stop(now + 2.5);
    });
    staticSound(0.3);
}

/* Breathy, filtered noise that comes and goes under the whisper. */
function whisperSound(seconds) {
    const context = ready();
    if (!context) return null;
    const now = context.currentTime;
    const noise = context.createBuffer(2, Math.ceil(context.sampleRate * seconds), context.sampleRate);
    for (let channel = 0; channel < 2; channel++) {
        const data = noise.getChannelData(channel);
        for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
    }
    const source = context.createBufferSource();
    source.buffer = noise;
    const band = context.createBiquadFilter();
    band.type = 'bandpass';
    band.frequency.value = 2400;
    band.Q.value = 1.4;
    const gain = context.createGain();
    gain.gain.setValueAtTime(0.0001, now);
    // In and out, like breathing close to the ear.
    for (let t = 0; t < seconds; t += 1.4) {
        gain.gain.exponentialRampToValueAtTime(0.09, now + t + 0.5);
        gain.gain.exponentialRampToValueAtTime(0.012, now + t + 1.3);
    }
    source.connect(band).connect(gain).connect(context.destination);
    source.start(now);

    return {
        stop() {
            const t = context.currentTime;
            gain.gain.cancelScheduledValues(t);
            gain.gain.setValueAtTime(Math.max(gain.gain.value, 0.0001), t);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
            source.stop(t + 0.45);
        },
    };
}

/* A burst of radio static. */
function staticSound(seconds) {
    const context = S.context;
    if (!context || context.state !== 'running') return;
    const now = context.currentTime;
    const noise = context.createBuffer(1, Math.ceil(context.sampleRate * seconds), context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = (Math.random() * 2 - 1) * (Math.random() < 0.08 ? 1 : 0.35);
    const source = context.createBufferSource();
    source.buffer = noise;
    const band = context.createBiquadFilter();
    band.type = 'bandpass';
    band.frequency.value = 1800;
    band.Q.value = 0.7;
    const gain = context.createGain();
    gain.gain.setValueAtTime(0.25, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + seconds);
    source.connect(band).connect(gain).connect(context.destination);
    source.start(now);
}

/* Low double thumps, like a heartbeat in the dark. */
function heartbeat(beats) {
    const context = S.context;
    if (!context || context.state !== 'running') return;
    const start = context.currentTime + 0.05;
    for (let beat = 0; beat < beats; beat++) {
        [0, 0.22].forEach((offset, index) => {
            const at = start + beat * 0.85 + offset;
            const tone = context.createOscillator();
            const gain = context.createGain();
            tone.type = 'sine';
            tone.frequency.setValueAtTime(70, at);
            tone.frequency.exponentialRampToValueAtTime(38, at + 0.18);
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(index ? 0.35 : 0.55, at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.25);
            tone.connect(gain).connect(context.destination);
            tone.start(at);
            tone.stop(at + 0.26);
        });
    }
}

/* ---------- hacker sounds ---------- */

/* One key press of a keyboard: a dry noise tick over a soft thump. */
function clack(volume = 0.09) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    const length = Math.ceil(context.sampleRate * 0.03);
    const noise = context.createBuffer(1, length, context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / length);
    const source = context.createBufferSource();
    const high = context.createBiquadFilter();
    const gain = context.createGain();
    source.buffer = noise;
    high.type = 'highpass';
    high.frequency.value = 1800 + Math.random() * 1500;
    gain.gain.value = volume;
    source.connect(high).connect(gain).connect(context.destination);
    source.start(t);

    const thump = context.createOscillator();
    const thumpGain = context.createGain();
    thump.frequency.setValueAtTime(160, t);
    thump.frequency.exponentialRampToValueAtTime(60, t + 0.04);
    thumpGain.gain.setValueAtTime(volume * 0.66, t);
    thumpGain.gain.exponentialRampToValueAtTime(0.0001, t + 0.05);
    thump.connect(thumpGain).connect(context.destination);
    thump.start(t);
    thump.stop(t + 0.06);
}

/* A stream of tiny digital blips, like data scrolling past. */
function chirps(count = 8) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    let at = t;
    for (let i = 0; i < count; i++) {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'square';
        osc.frequency.value = 500 + Math.random() * 3200;
        gain.gain.setValueAtTime(0.035, at);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.03);
        osc.connect(gain).connect(context.destination);
        osc.start(at);
        osc.stop(at + 0.035);
        at += 0.03 + Math.random() * 0.06;
    }
}

/* A dial-up style burst: a square wave hopping between tones, then a hiss. */
function modem(seconds) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    const osc = context.createOscillator();
    const band = context.createBiquadFilter();
    const gain = context.createGain();
    osc.type = 'square';
    band.type = 'bandpass';
    band.frequency.value = 1800;
    band.Q.value = 1.2;
    const tones = [1200, 2100, 980, 1650, 2400, 750, 1500];
    for (let at = 0; at < seconds; at += 0.07) osc.frequency.setValueAtTime(tones[Math.floor(Math.random() * tones.length)], t + at);
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(0.05, t + 0.1);
    gain.gain.setValueAtTime(0.05, t + seconds - 0.2);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + seconds);
    osc.connect(band).connect(gain).connect(context.destination);
    osc.start(t);
    osc.stop(t + seconds + 0.05);
}

/* A low warning siren that pulses and sinks. */
function alarm(seconds) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    const osc = context.createOscillator();
    const low = context.createBiquadFilter();
    const gain = context.createGain();
    const pulse = context.createOscillator();
    const depth = context.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(116, t);
    osc.frequency.exponentialRampToValueAtTime(62, t + seconds);
    low.type = 'lowpass';
    low.frequency.value = 520;
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(0.16, t + 0.15);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + seconds);
    pulse.type = 'square';
    pulse.frequency.value = 3;
    depth.gain.value = 0.07;
    pulse.connect(depth).connect(gain.gain);
    osc.connect(low).connect(gain).connect(context.destination);
    [osc, pulse].forEach((node) => {
        node.start(t);
        node.stop(t + seconds + 0.05);
    });
}

/* ---------- miss 4: the punishment ---------- */

const device = () => {
    const ua = navigator.userAgent;
    const os = /Windows/.test(ua) ? 'Windows' : /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Mac/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : 'OS tidak dikenal';
    const browser = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : 'browser tidak dikenal';
    return `${os} / ${browser} / ${window.screen.width}x${window.screen.height}`;
};

/**
 * Not a window: the whole screen is taken over. A black console types out everything the page can
 * tell about them (their IP, place, network, device and time zone), "uploads" their history,
 * slams "KAMU YANG MINTA." and counts down to the video, under a drone, key clicks, a siren and a
 * heartbeat. Everything is dark red on black and never flashes bright.
 */
async function doom(scene, seconds) {
    const root = stage();
    root.classList.remove('is-dim');
    const box = el('div', 'prank-doom');
    const log = el('div', 'prank-doom-log');
    const slam = el('p', 'prank-trace-ip prank-doom-slam');
    slam.dataset.text = '';
    box.append(log, slam);
    root.append(box);

    box.animate([{ opacity: 0 }, { opacity: 1 }], { duration: S.reduced ? 1 : 90, fill: 'both' });
    playSound(HIT, { volume: 1 });
    staticSound(0.9);
    shake(card(), 26);
    const drone = dread();
    await wait(S.reduced ? 1 : 700);

    const line = async (text, kind = '', speed = 8) => {
        const row = el('p', kind);
        log.append(row);
        await typeInto(row, text, speed, clack, 5);
        log.scrollTop = log.scrollHeight;
        await wait(S.reduced ? 1 : 90);
    };
    const place = [scene.city, scene.country].filter(Boolean).join(', ') || 'tidak diketahui';

    await line(`root@brread:~# ./hukuman.sh --target ${scene.ip || 'kamu'}`);
    modem(1.2);
    await line('[ OK ] menembus sesi ........ berhasil');
    chirps(10);
    await line(`TARGET      : ${scene.ip || '???'}`);
    await line(`LOKASI      : ${place.toUpperCase()}`);
    await line(`JARINGAN    : ${(scene.isp || 'tidak diketahui').toUpperCase()}`);
    await line(`PERANGKAT   : ${device()}`);
    await line(`ZONA WAKTU  : ${Intl.DateTimeFormat().resolvedOptions().timeZone || '?'}`);
    await line('');
    await line('[ !! ] menyalin riwayat percobaan login', 'is-warn');
    const bar = el('p', 'is-warn');
    log.append(bar);
    for (let step = 0; step <= 24; step++) {
        bar.textContent = `       [${'#'.repeat(step)}${'.'.repeat(24 - step)}] ${Math.round((step / 24) * 100)}%`;
        if (step % 2 === 0) chirps(3);
        await wait(S.reduced ? 0 : 55 + Math.random() * 60);
    }
    await line('[ !! ] mengirim laporan ke admin ........ terkirim', 'is-warn');
    await wait(S.reduced ? 1 : 500);
    await line('kamu udah diperingatin 3 kali.', 'is-dim-line', 40);
    await wait(S.reduced ? 1 : 900);

    // The slam.
    box.classList.add('is-slam');
    const text = 'KAMU YANG MINTA.';
    slam.textContent = text;
    slam.dataset.text = text;
    playSound(HIT, { volume: 1 });
    alarm(2.6);
    riser(2.2);
    shake(box, 30, 600);
    shake(card(), 30, 600);
    if (!S.reduced) slam.animate([{ transform: 'scale(1.7)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }], { duration: 260, easing: 'cubic-bezier(.2,.9,.3,1)', fill: 'both' });
    await wait(S.reduced ? 1 : 2200);

    const locked = `login dikunci ${clockText(seconds)} untuk IP ${scene.ip || 'kamu'}`;
    slam.textContent = locked.toUpperCase();
    slam.dataset.text = slam.textContent;
    slam.classList.add('is-small');
    heartbeat(3);
    await wait(S.reduced ? 1 : 2100);

    for (const digit of ['3', '2', '1']) {
        slam.classList.remove('is-small');
        slam.textContent = digit;
        slam.dataset.text = digit;
        playSound(HIT, { volume: 0.7 });
        shake(box, 16, 300);
        await wait(S.reduced ? 1 : 900);
    }

    drone?.stop();
    return box;
}

async function sceneFour(scene) {
    stopBed(0.3);
    curse({ sound: false });
    arm();
    S.place = scene.city || '';
    S.ip = scene.ip || null;
    const seconds = scene.retry_after || 60;
    hold(seconds);
    lockForm(seconds);
    const box = await doom(scene, seconds);
    S.current = null;
    await party(seconds, scene.ip, box);
}

/* ---------- the Kewer Kewer video ---------- */

/** The terminal window grows from where it is to cover the whole screen, its text falling away. */
async function grow(box) {
    const r = box.getBoundingClientRect();
    const inner = [...box.children];
    inner.forEach((child) => child.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.9)' }], { duration: 220, fill: 'forwards' }));

    await box.animate([
        { transform: 'scale(1)' },
        { transform: 'scale(.94)' },
    ], { duration: 160, easing: 'ease-out', fill: 'forwards' }).finished.catch(() => {});

    box.getAnimations().forEach((animation) => animation.cancel());
    await box.animate([
        { left: `${r.left}px`, top: `${r.top}px`, width: `${r.width}px`, height: `${r.height}px`, borderRadius: '14px', transform: 'scale(.94)' },
        { left: '0px', top: '0px', width: `${window.innerWidth}px`, height: `${window.innerHeight}px`, borderRadius: '0px', transform: 'scale(1)' },
    ], { duration: 560, easing: 'cubic-bezier(.7,0,.2,1)', fill: 'forwards' }).finished.catch(() => {});
}

async function party(seconds, ip, from = null) {
    const root = stage();
    root.classList.add('is-dim');
    S.ip = ip || S.ip;
    S.bsod = false;
    S.tries = [];
    awake();
    forceFullscreen();
    lockForm(seconds);
    hold(seconds);
    stopBed(0.3);
    curse({ sound: false });
    arm();

    const tv = el('div', 'prank-tv');
    const video = el('video');
    video.src = VIDEO;
    video.loop = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.setAttribute('playsinline', '');

    const bar = el('div', 'prank-tv-bar');
    const label = el('span', '', 'sign-in locked');
    const clock = el('b');
    const who = el('span', 'prank-tv-ip', ip ? `IP ${ip} logged` : '');
    bar.append(label, clock, who);

    const back = el('button', 'prank-close', 'Back to Brread!');
    back.type = 'button';
    const unmute = el('button', 'prank-hint', 'click to turn the sound on');
    unmute.type = 'button';
    unmute.hidden = true;

    tv.append(video, bar, back, unmute);
    clock.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    if (from?.isConnected) root.insertBefore(tv, from); else root.append(tv);
    S.tv = tv;
    window.clearTimeout(S.harass);
    S.harass = later(harass, 5000);

    let raf = 0;

    const start = async () => {
        if (!tv.isConnected || S.bsod) return;
        video.muted = false;
        video.volume = 1;
        try {
            await video.play();
            unmute.hidden = true;
        } catch (error) {
            if (error?.name === 'NotAllowedError') {
                // No click to ride on (a reload): play it silent and ask for one.
                video.muted = true;
                video.play().catch(() => {});
                unmute.hidden = false;
            }
        }
    };

    unmute.addEventListener('click', (event) => {
        event.stopPropagation();
        start();
    });
    tv.addEventListener('pointerdown', () => {
        if (!unmute.hidden) start();
    });

    // The sound cannot be switched off from the page: the first touch anywhere turns it on, any
    // later mute or volume change on the video is undone, and the media keys cannot pause it.
    // (A website cannot see or undo the browser's own tab mute or the system volume.)
    const guard = new AbortController();
    let touched = false;
    const onGesture = () => {
        touched = true;
        awake();
        forceFullscreen();
        if (!unmute.hidden || video.muted) start();
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, onGesture, { signal: guard.signal, capture: true }));
    const loud = () => {
        if (!tv.isConnected || S.bsod) return;
        // The sound effects ride on an audio context that the browser can put to sleep; wake it again.
        if (S.context && S.context.state !== 'running' && S.context.state !== 'closed') S.context.resume?.().catch?.(() => {});
        if (video.paused) video.play().catch(() => {});
        if (touched && (video.muted || video.volume < 1)) {
            video.volume = 1;
            video.muted = false;
        }
    };
    video.addEventListener('volumechange', loud);
    const loudTimer = window.setInterval(loud, 400);
    const keys = ['pause', 'stop', 'seekbackward', 'seekforward', 'seekto'];
    try {
        keys.forEach((action) => navigator.mediaSession?.setActionHandler(action, () => {
            if (!S.bsod) video.play().catch(() => {});
        }));
    } catch {
        // This browser has no such media key.
    }

    if (from?.isConnected && !S.reduced) {
        // The terminal swells to fill the screen, then the screen switches on like an old TV.
        tv.style.opacity = '0';
        start();
        if (from.classList.contains('prank-doom')) {
            await from.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160, fill: 'forwards' }).finished.catch(() => {});
        } else {
            await grow(from);
        }
        const main = document.querySelector('main');
        main?.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 1, fill: 'forwards' });
        tv.style.opacity = '1';
        from.remove();
        await tv.animate([
            { clipPath: 'inset(50% 0 50% 0)', filter: 'brightness(3) saturate(0)' },
            { clipPath: 'inset(49.6% 0 49.6% 0)', filter: 'brightness(4) saturate(0)', offset: 0.25 },
            { clipPath: 'inset(0 0 0 0)', filter: 'brightness(1.6) saturate(.6)', offset: 0.6 },
            { clipPath: 'inset(0 0 0 0)', filter: 'brightness(1) saturate(1)' },
        ], { duration: 650, easing: 'cubic-bezier(.2,.8,.3,1)' }).finished.catch(() => {});
    } else {
        from?.remove();
        tv.animate([{ opacity: 0 }, { opacity: 1 }], { duration: S.reduced ? 1 : 300 });
        start();
    }

    let until = Date.now() + seconds * 1000;
    let timer = 0;
    const tick = () => {
        const secs = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        clock.textContent = `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
        if (secs <= 0) end();
    };

    const finish = async () => {
        window.clearTimeout(S.harass);
        window.clearTimeout(S.toastTimer);
        S.toast?.remove();
        S.toast = null;
        S.kewer = null;
        S.tv = null;
        leaveFullscreen();
        guard.abort();
        window.clearInterval(loudTimer);
        try {
            keys.forEach((action) => navigator.mediaSession?.setActionHandler(action, null));
        } catch {
            // Nothing to undo.
        }
        window.clearInterval(timer);
        window.cancelAnimationFrame(raf);
        video.pause();
        await tv.animate([{ opacity: 1 }, { opacity: 0 }], { duration: S.reduced ? 1 : 400, fill: 'forwards' }).finished.catch(() => {});
        video.removeAttribute('src');
        video.load();
        clearStage();
        document.querySelector('main')?.getAnimations().forEach((animation) => animation.cancel());
    };

    // There is no way out of the video: "back" only gets a terminal that makes fun of the try.
    back.addEventListener('click', (event) => {
        event.stopPropagation();
        taunt('back');
    });

    const end = () => {
        window.clearInterval(timer);
        letGo();
        release();
        uncurse();
        unlockForm();
        if (tv.isConnected) finish();
    };

    // The blue screen resets all of this: the video from zero, the clock at no less than `minimum`.
    S.kewer = {
        hush() {
            window.clearTimeout(S.harass);
            video.pause();
        },
        reset(minimum) {
            const left = Math.ceil((until - Date.now()) / 1000);
            const next = Math.max(minimum, left);
            until = Date.now() + next * 1000;
            lockForm(next);
            hold(next);
            video.currentTime = 0;
            start();
            window.clearTimeout(S.harass);
            S.harass = later(harass, 5000);
            window.clearTimeout(S.toastTimer);
            S.toastTimer = later(toastLoop, 6000);
            tick();
            return next;
        },
    };

    window.clearTimeout(S.toastTimer);
    S.toastTimer = later(toastLoop, 6000);
    tick();
    timer = window.setInterval(tick, 250);
}

/* ---------- the Windows notification, forced fullscreen and the blue screen ---------- */

const SHIELD = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M8 .8 2.1 2.7v4.7c0 3.3 2.3 5.8 5.9 7.4 3.6-1.6 5.9-4.1 5.9-7.4V2.7z" fill="#1c6fd1"/><path d="M8 .8v14c3.6-1.6 5.9-4.1 5.9-7.4V2.7z" fill="#4cb0ff"/></svg>';
const CROSS = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" fill="none"/></svg>';

/** A plain sound that does not need the audio context (the context may be asleep after a reload). */
function plain(url, volume = 1) {
    if (S.context?.state === 'running') {
        playSound(url, { volume });
        return;
    }
    try {
        const audio = new Audio(url);
        audio.volume = volume;
        audio.play().catch(() => {});
    } catch {
        // No sound this time.
    }
}

/**
 * The page asks for the whole screen. Browsers only grant that inside a click or a key press, so
 * this is tried on every one of them; holding Esc leaves fullscreen, and the next click is back in.
 */
function forceFullscreen() {
    const root = document.documentElement;
    if (document.fullscreenElement || !root.requestFullscreen) return;

    try {
        root.requestFullscreen({ navigationUI: 'hide' })
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

/** A Windows 11 notification in the bottom right corner. It cannot be closed: it leaves by itself after 15 seconds. */
function winToast() {
    if (S.toast?.isConnected || S.bsod || !S.tv) return;

    const toast = el('div', 'prank-wintoast');
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="wt-head">${SHIELD}<span>Windows Security</span><b class="wt-dots">···</b><button type="button" class="wt-x" aria-label="Close">${CROSS}</button></div>
        <p class="wt-app">Windows Security</p>
        <p class="wt-title">Turn on virus protection</p>
        <p class="wt-text">Virus protection is turned off. Tap or click to turn on Microsoft Defender Antivirus.</p>
        <button type="button" class="wt-dismiss">Dismiss</button>`;
    stage().append(toast);
    S.toast = toast;
    plain(NOTIFY, 1);

    // Every control on it is a dummy: it only wobbles.
    toast.addEventListener('click', (event) => {
        event.stopPropagation();
        shake(toast, 5, 220);
    });

    if (!S.reduced) {
        toast.animate([
            { transform: 'translateX(calc(100% + 24px))', opacity: 0 },
            { transform: 'translateX(0)', opacity: 1 },
        ], { duration: 420, easing: 'cubic-bezier(.1,.9,.2,1)' });
    }

    later(async () => {
        if (!toast.isConnected) return;
        await toast.animate(S.reduced
            ? [{ opacity: 1 }, { opacity: 0 }]
            : [{ transform: 'translateX(0)', opacity: 1 }, { transform: 'translateX(calc(100% + 24px))', opacity: 0 }],
        { duration: S.reduced ? 1 : 260, easing: 'ease-in', fill: 'forwards' }).finished.catch(() => {});
        toast.remove();
        if (S.toast === toast) S.toast = null;
    }, TOAST_MS);
}

/** The first one shows a few seconds into the video, then one now and then for as long as it plays. */
function toastLoop() {
    window.clearTimeout(S.toastTimer);
    if (!S.tv) return;

    winToast();
    S.toastTimer = later(toastLoop, 28000 + Math.random() * 10000);
}

/**
 * Too many tries to leave in a short time: a blue screen takes over for 10 seconds, the video starts
 * from zero and the pause is at least two minutes from now.
 */
async function bsod() {
    S.bsod = true;
    window.clearTimeout(S.harass);
    window.clearTimeout(S.toastTimer);
    S.toast?.remove();
    S.toast = null;
    S.taunting?.dismiss();
    S.taunting = null;
    S.root?.querySelectorAll('.prank-mini, .prank-term').forEach((node) => node.remove());
    S.kewer?.hush();

    const screen = el('div', 'prank-bsod');
    const video = el('video');
    video.src = BSOD;
    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.setAttribute('playsinline', '');
    screen.append(video);
    document.body.append(screen);

    // Nothing gets through while it is up.
    const stop = new AbortController();
    ['keydown', 'keyup', 'contextmenu', 'wheel'].forEach((type) => window.addEventListener(type, (event) => {
        event.preventDefault();
        event.stopPropagation();
    }, { signal: stop.signal, capture: true, passive: false }));

    forceFullscreen();
    plain(XPERROR, 1);
    video.play().catch(() => {});
    later(() => plain(XPERROR, 0.9), 4800);

    await wait(BSOD_MS);

    stop.abort();
    await breakOut(screen, video);
}

/** Breaking glass: a crack, a low thump and a spray of tiny shards ringing off. */
function glass() {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;

    const hit = (when, length, frequency, volume, type = 'highpass') => {
        const size = Math.ceil(context.sampleRate * length);
        const noise = context.createBuffer(1, size, context.sampleRate);
        const data = noise.getChannelData(0);
        for (let i = 0; i < size; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / size) ** 2;
        const source = context.createBufferSource();
        const filter = context.createBiquadFilter();
        const gain = context.createGain();
        source.buffer = noise;
        filter.type = type;
        filter.frequency.value = frequency;
        gain.gain.value = volume;
        source.connect(filter).connect(gain).connect(context.destination);
        source.start(t + when);
    };

    const ping = (when, frequency) => {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(0.07, t + when);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + when + 0.3);
        osc.connect(gain).connect(context.destination);
        osc.start(t + when);
        osc.stop(t + when + 0.32);
    };

    hit(0, 0.2, 1500, 0.8);
    hit(0.015, 0.4, 4800, 0.45);
    for (let i = 0; i < 30; i++) {
        const when = 0.14 + Math.random() * 1.0;
        hit(when, 0.05 + Math.random() * 0.06, 3000 + Math.random() * 5500, 0.14 + Math.random() * 0.14, 'bandpass');
        if (i % 3 === 0) ping(when, 2200 + Math.random() * 4800);
    }

    const boom = context.createOscillator();
    const boomGain = context.createGain();
    boom.frequency.setValueAtTime(130, t);
    boom.frequency.exponentialRampToValueAtTime(38, t + 0.45);
    boomGain.gain.setValueAtTime(0.6, t);
    boomGain.gain.exponentialRampToValueAtTime(0.0001, t + 0.55);
    boom.connect(boomGain).connect(context.destination);
    boom.start(t);
    boom.stop(t + 0.6);
}

/** The last frame of the blue screen, cracked from one point and cut into triangular shards. */
function shards(video) {
    const w = window.innerWidth;
    const h = window.innerHeight;
    const canvas = el('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#0078d7';
    ctx.fillRect(0, 0, w, h);
    try {
        const vw = video.videoWidth || 1280;
        const vh = video.videoHeight || 720;
        const k = Math.min(w / vw, h / vh);
        ctx.drawImage(video, (w - vw * k) / 2, (h - vh * k) / 2, vw * k, vh * k);
    } catch {
        // The plain blue stays.
    }

    const hx = w * (0.35 + Math.random() * 0.3);
    const hy = h * (0.3 + Math.random() * 0.35);
    ctx.strokeStyle = 'rgba(255,255,255,.9)';
    ctx.lineWidth = 2;
    for (let i = 0; i < 16; i++) {
        let angle = Math.random() * Math.PI * 2;
        let x = hx;
        let y = hy;
        ctx.beginPath();
        ctx.moveTo(x, y);
        for (let step = 0; step < 6; step++) {
            angle += (Math.random() - 0.5) * 0.6;
            const length = Math.max(w, h) * (0.04 + Math.random() * 0.08);
            x += Math.cos(angle) * length;
            y += Math.sin(angle) * length;
            ctx.lineTo(x, y);
        }
        ctx.stroke();
    }

    let image = '';
    try {
        image = canvas.toDataURL('image/jpeg', 0.85);
    } catch {
        // No picture, the shards are plain blue.
    }

    const cols = Math.max(4, Math.ceil(w / 150));
    const rows = Math.max(4, Math.ceil(h / 150));
    const grid = [];
    for (let j = 0; j <= rows; j++) {
        grid.push([]);
        for (let i = 0; i <= cols; i++) {
            const x = (i * w) / cols + (i === 0 || i === cols ? 0 : (Math.random() - 0.5) * 0.7 * (w / cols));
            const y = (j * h) / rows + (j === 0 || j === rows ? 0 : (Math.random() - 0.5) * 0.7 * (h / rows));
            grid[j].push([x, y]);
        }
    }

    const box = el('div', 'prank-shards');
    const pieces = [];
    for (let j = 0; j < rows; j++) {
        for (let i = 0; i < cols; i++) {
            const [a, b, c, d] = [grid[j][i], grid[j][i + 1], grid[j + 1][i + 1], grid[j + 1][i]];
            const triangles = Math.random() < 0.5 ? [[a, b, c], [a, c, d]] : [[a, b, d], [b, c, d]];
            triangles.forEach((points) => {
                const piece = el('div', 'prank-shard');
                if (image) piece.style.backgroundImage = `url(${image})`;
                piece.style.clipPath = `polygon(${points.map(([x, y]) => `${x.toFixed(1)}px ${y.toFixed(1)}px`).join(',')})`;
                const mx = (points[0][0] + points[1][0] + points[2][0]) / 3;
                const my = (points[0][1] + points[1][1] + points[2][1]) / 3;
                piece.style.transformOrigin = `${mx.toFixed(1)}px ${my.toFixed(1)}px`;
                box.append(piece);
                pieces.push({ piece, mx, my });
            });
        }
    }

    // They drop away from the point of impact outwards, the nearest ones first.
    const far = Math.hypot(Math.max(hx, w - hx), Math.max(hy, h - hy));
    const fall = () => Promise.all(pieces.map(({ piece, mx, my }) => {
        const distance = Math.hypot(mx - hx, my - hy);
        const sign = Math.random() < 0.5 ? -1 : 1;
        return piece.animate([
            { transform: 'translate(0, 0) rotate(0deg)', opacity: 1 },
            { transform: `translate(${(mx - hx) * 0.25 + (Math.random() - 0.5) * 120}px, ${h * (0.55 + Math.random() * 0.6)}px) rotate(${sign * (40 + Math.random() * 200)}deg)`, opacity: 0.9, offset: 0.85 },
            { transform: `translate(${(mx - hx) * 0.3}px, ${h * 1.3}px) rotate(${sign * 260}deg)`, opacity: 0 },
        ], {
            duration: 760 + Math.random() * 380,
            delay: (distance / far) * 220 + Math.random() * 80,
            easing: 'cubic-bezier(.5,0,.9,.4)',
            fill: 'forwards',
        }).finished.catch(() => {});
    }));

    return { box, fall };
}

/**
 * The way out of the blue screen: it cracks and breaks like glass under a big "PRENGGG!", and the
 * Kewer video starts over from zero behind the falling pieces.
 */
async function breakOut(screen, video) {
    const word = el('div', 'prank-prengg', 'PRENGGG!');
    const cracked = S.reduced ? null : shards(video);
    if (cracked) document.body.append(cracked.box);
    screen.remove();
    document.body.append(word);
    glass();

    word.animate(S.reduced
        ? [{ opacity: 0 }, { opacity: 1, offset: 0.1 }, { opacity: 1, offset: 0.8 }, { opacity: 0 }]
        : [
            { transform: 'translate(-50%, -50%) scale(2.8) rotate(-9deg)', opacity: 0 },
            { transform: 'translate(-50%, -50%) scale(1) rotate(-4deg)', opacity: 1, offset: 0.14 },
            { transform: 'translate(-50%, -50%) scale(1.08) rotate(-3deg)', opacity: 1, offset: 0.7 },
            { transform: 'translate(-50%, -50%) scale(1.7) rotate(-4deg)', opacity: 0 },
        ], { duration: 1700, fill: 'forwards' });

    if (cracked) shake(cracked.box, 16, 280);
    await wait(S.reduced ? 500 : 300);

    // Behind the pieces the video is already running again.
    S.hits = 0;
    S.tries = [];
    S.bsod = false;
    S.kewer?.reset(AFTER_BSOD);

    if (cracked) {
        await cracked.fall();
        cracked.box.remove();
    }
    await wait(S.reduced ? 800 : 250);
    word.remove();
    whisperTv('lagian lu iseng sih nambah kan waktunya', 4200);
}

/* ---------- the pause follows the browser ---------- */

const HOLD_KEY = 'brread-prank';

/** Remembers the pause in the browser so every page of the shop sends it back here (see the lockdown-guard partial). */
function hold(seconds) {
    const value = JSON.stringify({ until: Date.now() + seconds * 1000, path: window.location.pathname, ip: S.ip || null });
    try {
        window.localStorage.setItem(HOLD_KEY, value);
        window.sessionStorage.setItem(HOLD_KEY, value);
    } catch {
        // Storage is blocked: the server still keeps the pause.
    }
}

function readHold() {
    try {
        return JSON.parse(window.localStorage.getItem(HOLD_KEY) || window.sessionStorage.getItem(HOLD_KEY) || 'null');
    } catch {
        return null;
    }
}

function letGo() {
    try {
        window.localStorage.removeItem(HOLD_KEY);
        window.sessionStorage.removeItem(HOLD_KEY);
    } catch {
        // Nothing stored.
    }
}

/** A page that opens without a pause on the server forgets any stale hold. */
export function forgetHold() {
    letGo();
}

/* ---------- the form while paused ---------- */

let formTimer = 0;

function lockForm(seconds) {
    const form = document.querySelector('#login-card form');
    const fieldset = form?.querySelector('fieldset');
    const button = document.getElementById('login-submit');
    const label = button?.querySelector('[data-login-label]');
    const timer = button?.querySelector('[data-login-timer]');
    if (!fieldset || !label) return;

    fieldset.disabled = true;
    label.dataset.original ??= label.textContent;
    label.textContent = 'Locked';

    const until = Date.now() + seconds * 1000;
    window.clearInterval(formTimer);
    const tick = () => {
        const secs = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        if (timer) timer.textContent = `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
        if (secs <= 0) unlockForm();
    };
    tick();
    formTimer = window.setInterval(tick, 250);
}

function unlockForm() {
    window.clearInterval(formTimer);
    const fieldset = document.querySelector('#login-card form fieldset');
    const button = document.getElementById('login-submit');
    const label = button?.querySelector('[data-login-label]');
    const timer = button?.querySelector('[data-login-timer]');
    if (fieldset) fieldset.disabled = false;
    if (label?.dataset.original) label.textContent = label.dataset.original;
    if (timer) timer.textContent = '';
}

/* ---------- the broken form before the last try ---------- */

const JUNK = '█▓▒░#@%&$!?<>/\\|_~^*01';

// Diacritics stacked on each letter: the text looks corrupted but is still readable.
function corrupt(text, amount = 0.6) {
    const marks = ['̴', '̵', '̶', '̷', '̸', '̕', '̛', '̀', '́', '͘', '̡', '̢', '̧', '̨', '̴'];
    return [...text].map((letter) => (letter.trim() && Math.random() < amount
        ? letter + marks[Math.floor(Math.random() * marks.length)] + (Math.random() < 0.4 ? marks[Math.floor(Math.random() * marks.length)] : '')
        : letter)).join('');
}

function scramble(text, amount) {
    return [...text].map((letter) => (letter.trim() && Math.random() < amount ? JUNK[Math.floor(Math.random() * JUNK.length)] : letter)).join('');
}

/**
 * After the trace the sign-in page looks properly broken: warped, torn, colour-split, with dead
 * pixels and corrupted labels. The fields and the button still work, so the last try is possible.
 */
export function curse({ sound = true } = {}) {
    if (S.curse) return;
    const body = document.body;
    const main = document.querySelector('main');
    const formCard = card();
    if (!main || !formCard) return;

    body.classList.add('is-broken');
    const timers = [];
    const gate = new AbortController();

    // A glitchy horror bed plays under the form. A reload has no click to start sound on yet, so
    // it waits for the first touch. The winning fanfare is decoded now so it starts on time.
    if (sound) {
        adopt(null);
        buffer(VICTORY);
        startBed();
        if (!S.bed) {
            ['pointerdown', 'keydown'].forEach((type) => window.addEventListener(type, async () => {
                adopt(null);
                await S.context?.resume?.().catch(() => {});
                startBed();
            }, { once: true, signal: gate.signal }));
        }
    }
    const restore = [];
    const keep = (node, prop) => {
        if (node) restore.push([node, prop, node[prop]]);
    };

    // Warp filters: strong on the header, gentle on the card so the fields stay legible.
    const ns = 'http://www.w3.org/2000/svg';
    const defs = document.createElementNS(ns, 'svg');
    defs.setAttribute('class', 'prank-defs');
    defs.setAttribute('aria-hidden', 'true');
    defs.innerHTML = [
        '<filter id="prank-warp"><feTurbulence type="fractalNoise" baseFrequency="0.01 0.09" numOctaves="2" seed="3"><animate attributeName="seed" values="1;2;3;4;5;6;7;8" dur="0.8s" repeatCount="indefinite"/></feTurbulence><feDisplacementMap in="SourceGraphic" scale="14"/></filter>',
        '<filter id="prank-warp-soft"><feTurbulence type="fractalNoise" baseFrequency="0.004 0.08" numOctaves="1" seed="2"><animate attributeName="seed" values="1;2;3;4" dur="1.6s" repeatCount="indefinite"/></feTurbulence><feDisplacementMap in="SourceGraphic" scale="5"/></filter>',
    ].join('');
    body.append(defs);

    // A colour-split ghost of the card that tears in bands. It is only a picture: inert and hidden from readers.
    const ghost = formCard.cloneNode(true);
    ghost.removeAttribute('id');
    ghost.querySelectorAll('[id],[name]').forEach((node) => {
        node.removeAttribute('id');
        node.removeAttribute('name');
    });
    ghost.classList.remove('staff-login-card');
    ghost.classList.add('prank-ghost');
    ghost.setAttribute('aria-hidden', 'true');
    ghost.inert = true;
    formCard.parentElement.style.position = 'relative';
    formCard.after(ghost);
    const placeGhost = () => {
        ghost.style.top = `${formCard.offsetTop}px`;
        ghost.style.left = `${formCard.offsetLeft}px`;
        ghost.style.width = `${formCard.offsetWidth}px`;
        ghost.style.height = `${formCard.offsetHeight}px`;
    };
    placeGhost();
    timers.push(window.setInterval(placeGhost, 500));

    // Dead pixels and broken blocks popping up around the screen.
    const junk = el('div', 'prank-junk');
    junk.setAttribute('aria-hidden', 'true');
    body.append(junk);
    const block = () => {
        const bit = el('span');
        const wide = Math.random() < 0.3;
        bit.style.left = `${Math.random() * 100}vw`;
        bit.style.top = `${Math.random() * 100}vh`;
        bit.style.width = `${wide ? 80 + Math.random() * 260 : 6 + Math.random() * 40}px`;
        bit.style.height = `${wide ? 2 + Math.random() * 6 : 6 + Math.random() * 30}px`;
        bit.style.background = ['#ff003c', '#7a0016', '#14030a', '#ffc2ca', '#b80028', '#3a0610'][Math.floor(Math.random() * 6)];
        junk.append(bit);
        window.setTimeout(() => bit.remove(), 90 + Math.random() * 400);
    };
    if (!S.reduced) timers.push(window.setInterval(block, 140));

    // Labels, tagline, title and button text keep re-corrupting; the inputs are left alone.
    const labels = [...document.querySelectorAll('#login-card label, [data-login-tagline], [data-login-title], #login-submit [data-login-label], #login-back')];
    const texts = labels.map((node) => {
        const target = node.querySelector('[data-login-label]') || node;
        const textNode = [...target.childNodes].reverse().find((child) => child.nodeType === Node.TEXT_NODE && child.nodeValue.trim());
        if (!textNode) return null;
        keep(textNode, 'nodeValue');
        return { node: textNode, text: textNode.nodeValue };
    }).filter(Boolean);
    const rot = () => texts.forEach(({ node, text }) => {
        node.nodeValue = Math.random() < 0.15 ? scramble(text, 0.45) : corrupt(text, 0.55);
    });
    rot();
    if (!S.reduced) timers.push(window.setInterval(rot, 650));

    const password = document.getElementById('password');
    keep(password, 'placeholder');
    if (password) password.placeholder = corrupt('password', 0.9);

    // Heals the page over `ms`: the warping, the tearing and the corrupt text wind down to nothing.
    S.heal = (ms) => new Promise((resolve) => {
        timers.forEach((timer) => window.clearInterval(timer));
        timers.length = 0;
        body.classList.add('is-healing');
        const maps = [...defs.querySelectorAll('feDisplacementMap')];
        const base = maps.map((map) => Number(map.getAttribute('scale')));
        const began = performance.now();
        const tick = window.setInterval(() => {
            const left = Math.max(0, 1 - (performance.now() - began) / ms);
            maps.forEach((map, i) => map.setAttribute('scale', String(base[i] * left)));
            texts.forEach(({ node, text }) => {
                node.nodeValue = left > 0.05 ? corrupt(text, 0.5 * left) : text;
            });
            if (password) password.placeholder = left > 0.05 ? corrupt('password', 0.9 * left) : 'password';
            if (left <= 0) {
                window.clearInterval(tick);
                resolve();
            }
        }, 70);
        timers.push(tick);
    });

    S.curse = () => {
        gate.abort();
        stopBed(0.5);
        timers.forEach((timer) => window.clearInterval(timer));
        restore.forEach(([node, prop, value]) => {
            node[prop] = value;
        });
        [defs, ghost, junk].forEach((node) => node.remove());
        body.classList.remove('is-broken', 'is-healing');
        S.heal = null;
    };
}

function uncurse() {
    S.curse?.();
    S.curse = null;
}

/* ---------- the sound of the broken form ---------- */

function startBed() {
    if (!S.bed && ready()) S.bed = glitchBed();
}

function stopBed(fade = 0.8) {
    S.bed?.stop(fade);
    S.bed = null;
}

/**
 * A looping horror bed made of glitches: a detuned sub drone with a slow wobble, a thin whine and
 * random events (stuttering static, bit-crushed blips, dropouts, dull thuds) every second or so.
 */
function glitchBed() {
    const context = ready();
    if (!context) return null;
    const now = context.currentTime;
    const out = context.createGain();
    out.gain.setValueAtTime(0.0001, now);
    out.gain.exponentialRampToValueAtTime(0.55, now + 1.2);
    out.connect(context.destination);

    const lowpass = context.createBiquadFilter();
    lowpass.type = 'lowpass';
    lowpass.frequency.value = 260;
    lowpass.Q.value = 6;
    const droneGain = context.createGain();
    droneGain.gain.value = 0.2;
    lowpass.connect(droneGain).connect(out);

    const voices = [55, 58.4, 82.4].map((frequency, i) => {
        const osc = context.createOscillator();
        osc.type = i === 2 ? 'square' : 'sawtooth';
        osc.frequency.value = frequency;
        osc.detune.value = (i - 1) * 9;
        osc.connect(lowpass);
        osc.start(now);
        return osc;
    });

    const wobble = context.createOscillator();
    const wobbleDepth = context.createGain();
    wobble.frequency.value = 0.17;
    wobbleDepth.gain.value = 150;
    wobble.connect(wobbleDepth).connect(lowpass.frequency);
    wobble.start(now);

    const whine = context.createOscillator();
    const whineGain = context.createGain();
    whine.type = 'sine';
    whine.frequency.value = 2093;
    whineGain.gain.value = 0.01;
    whine.connect(whineGain).connect(out);
    whine.start(now);

    const noise = context.createBuffer(1, context.sampleRate, context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;

    const hiss = () => {
        const t = context.currentTime;
        const source = context.createBufferSource();
        source.buffer = noise;
        source.loop = true;
        const band = context.createBiquadFilter();
        band.type = 'bandpass';
        band.frequency.value = 600 + Math.random() * 6400;
        band.Q.value = 3 + Math.random() * 9;
        const gate = context.createGain();
        let at = t;
        for (let i = 0; i < 3 + Math.floor(Math.random() * 5); i++) {
            gate.gain.setValueAtTime(0.2, at);
            at += 0.02 + Math.random() * 0.03;
            gate.gain.setValueAtTime(0, at);
            at += 0.015 + Math.random() * 0.04;
        }
        source.connect(band).connect(gate).connect(out);
        source.start(t);
        source.stop(at + 0.05);
    };

    const blip = () => {
        const t = context.currentTime;
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = Math.random() < 0.5 ? 'square' : 'sawtooth';
        const length = 0.1 + Math.random() * 0.2;
        for (let at = 0; at < length; at += 0.025) osc.frequency.setValueAtTime(120 + Math.random() * 2300, t + at);
        gain.gain.setValueAtTime(0.06, t);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + length);
        osc.connect(gain).connect(out);
        osc.start(t);
        osc.stop(t + length + 0.02);
    };

    const thud = () => {
        const t = context.currentTime;
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(95, t);
        osc.frequency.exponentialRampToValueAtTime(36, t + 0.35);
        gain.gain.setValueAtTime(0.5, t);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
        osc.connect(gain).connect(out);
        osc.start(t);
        osc.stop(t + 0.42);
    };

    const dropout = () => {
        const t = context.currentTime;
        out.gain.cancelScheduledValues(t);
        out.gain.setValueAtTime(out.gain.value, t);
        out.gain.linearRampToValueAtTime(0.04, t + 0.01);
        out.gain.setValueAtTime(0.04, t + 0.14);
        out.gain.linearRampToValueAtTime(0.55, t + 0.2);
    };

    let alive = true;
    let timer = 0;
    const events = [hiss, hiss, blip, blip, thud, dropout];
    const next = () => {
        if (!alive) return;
        events[Math.floor(Math.random() * events.length)]();
        timer = window.setTimeout(next, 200 + Math.random() * 1100);
    };
    timer = window.setTimeout(next, 600);

    return {
        stop(fade = 0.8) {
            alive = false;
            window.clearTimeout(timer);
            const t = context.currentTime;
            out.gain.cancelScheduledValues(t);
            out.gain.setValueAtTime(Math.max(out.gain.value, 0.0001), t);
            out.gain.exponentialRampToValueAtTime(0.0001, t + fade);
            [...voices, wobble, whine].forEach((osc) => {
                try {
                    osc.stop(t + fade + 0.05);
                } catch {
                    // Already stopped.
                }
            });
        },
    };
}

/* ---------- the right password on the last try ---------- */

/**
 * A sheet of melting strawberry, the same one that wipes the shop's entry intro: it comes down from
 * the top edge with drips hanging off it. Covering the screen is one animation, leaving downward
 * another, so a scene can change behind it.
 */
function meltSheet(seed = 1) {
    const sheet = el('div', 'prank-melt');
    const top = el('div', `drip drip--strawberry-${seed % 3} prank-melt-top`);
    const bottom = el('div', `drip drip--strawberry-${(seed + 1) % 3} prank-melt-bottom`);
    [top, bottom].forEach((drip) => drip.setAttribute('aria-hidden', 'true'));
    sheet.append(top, bottom);
    stage().append(sheet);

    const down = 'translateY(calc(-100% - 130px))';
    const away = 'translateY(calc(100% + 130px))';
    sheet.style.transform = down;

    return {
        sheet,
        cover: () => (S.reduced
            ? sheet.animate([{ opacity: 0, transform: 'none' }, { opacity: 1, transform: 'none' }], { duration: 200, fill: 'forwards' }).finished
            : sheet.animate([{ transform: down }, { transform: 'none' }], { duration: 760, easing: 'cubic-bezier(.65,0,.35,1)', fill: 'forwards' }).finished).catch(() => {}),
        leave: () => (S.reduced
            ? sheet.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 200, fill: 'forwards' }).finished
            : sheet.animate([{ transform: 'none' }, { transform: away }], { duration: 820, easing: 'cubic-bezier(.65,0,.35,1)', fill: 'forwards' }).finished).catch(() => {}),
    };
}

/**
 * The right password on the last try. The broken page heals while the victory fanfare plays, then a
 * sheet of strawberry melts down over it and moves on, leaving a cream "access granted" screen:
 * a sandwich drops together, sprinkles pop, a loading bar fills, and a second sheet melts down to
 * carry the browser on to the menu (the next page lifts the same pink away, see the lockdown guard).
 */
export async function success(redirect, { context = null, username = '' } = {}) {
    adopt(context);
    stopBed(0.6);
    S.current?.dismiss();
    S.current = null;
    playSound(VICTORY, { volume: 1 });
    awake();

    const root = stage();
    root.classList.remove('is-dim');
    root.classList.add('is-granting');

    // The glitch winds down while the melt is already on its way.
    const healing = S.heal?.(S.reduced ? 1 : 1900);
    if (!S.reduced) await wait(850);
    const first = meltSheet(1);
    await first.cover();
    await healing;
    uncurse();

    // Behind the sheet: the cream screen, built while nobody can see it.
    const ok = el('div', 'prank-ok doodle');
    const stack = el('div', 'prank-ok-stack');
    const bottom = el('img', 'prank-ok-piece prank-ok-bottom');
    const ice = el('div', 'prank-ok-piece prank-ok-ice');
    const cream = el('img');
    const topping = el('img', 'prank-ok-topping');
    const crown = el('img', 'prank-ok-piece prank-ok-top');
    [[bottom, 'bread-white'], [cream, 'icecream-strawberry'], [topping, 'topping-sprinkles'], [crown, 'bread-white']].forEach(([image, name]) => {
        image.src = `/img/intro/${name}.webp`;
        image.alt = '';
        image.width = 420;
    });
    ice.append(cream, topping);
    stack.append(bottom, ice, crown);

    const colors = ['#f4a7b9', '#9cc9cf', '#f3c969', '#b7a6e0', '#ffffff', '#f58fa6'];
    const sprinkles = [];
    if (!S.reduced) {
        for (let i = 0; i < 26; i++) {
            const bit = el('span', 'prank-ok-bit');
            bit.style.background = colors[i % colors.length];
            stack.append(bit);
            sprinkles.push(bit);
        }
    }

    const title = el('h2', 'prank-ok-title');
    [...'ACCESS GRANTED'].forEach((letter) => title.append(el('span', '', letter === ' ' ? ' ' : letter)));
    const hello = el('p', 'prank-ok-hello');
    const meter = el('div', 'prank-ok-bar');
    const fill = el('i');
    meter.append(fill);
    const status = el('p', 'prank-ok-status', 'verifying session');
    ok.append(stack, title, hello, meter, status);
    root.insertBefore(ok, first.sheet);

    if (!S.reduced) {
        [bottom, ice, crown].forEach((piece, index) => piece.animate([
            { opacity: 0, transform: 'translateY(-70vh) rotate(-8deg)' },
            { opacity: 1, offset: 0.6 },
            { opacity: 1, transform: 'none' },
        ], { duration: 520, delay: 80 + index * 170, easing: 'cubic-bezier(.34,1.56,.64,1)', fill: 'both' }));
        title.querySelectorAll('span').forEach((letter) => letter.animate([{ opacity: 0 }, { opacity: 0 }], { duration: 1, fill: 'both' }));
    } else {
        title.style.opacity = '1';
    }

    // The sheet moves on down and the screen is there.
    await wait(S.reduced ? 1 : 180);
    await first.leave();
    first.sheet.remove();

    if (!S.reduced) {
        stack.animate([{ transform: 'scale(1.05, .92)' }, { transform: 'none' }], { duration: 420, delay: 520, easing: 'cubic-bezier(.34,1.56,.64,1)', fill: 'both' });
        sprinkles.forEach((bit, i) => {
            const angle = (i / sprinkles.length) * Math.PI * 2 + Math.random() * 0.4;
            const distance = 110 + Math.random() * 180;
            bit.animate([
                { transform: 'translate(-50%, -50%) rotate(0) scale(.4)', opacity: 1 },
                { transform: `translate(calc(-50% + ${Math.cos(angle) * distance}px), calc(-50% + ${Math.sin(angle) * distance * 0.8}px)) rotate(${Math.random() * 540}deg) scale(1)`, opacity: 0 },
            ], { duration: 900 + Math.random() * 400, delay: 640 + Math.random() * 120, easing: 'cubic-bezier(.1,.7,.3,1)', fill: 'both' });
        });
        [...title.children].forEach((letter, i) => {
            letter.animate([
                { opacity: 0, transform: 'translateY(22px) scale(1.5)' },
                { opacity: 1, transform: 'translateY(-4px) scale(.96)', offset: 0.7 },
                { opacity: 1, transform: 'none' },
            ], { duration: 420, delay: 600 + i * 42, easing: 'cubic-bezier(.2,.9,.3,1)', fill: 'both' });
        });
    }

    await wait(S.reduced ? 1 : 1150);
    await typeInto(hello, `welcome back${username ? `, ${username.trim().slice(0, 24)}` : ''}`, 34);

    // The loading bar fills while the status changes, a sandwich-pink bar with its own sprinkles.
    const steps = ['verifying session', 'unlocking the panel', 'opening the menu...'];
    const total = S.reduced ? 400 : 1800;
    fill.animate([{ width: '0%' }, { width: '100%' }], { duration: total, easing: 'cubic-bezier(.5,0,.2,1)', fill: 'forwards' });
    for (const step of steps) {
        status.textContent = step;
        await wait(total / steps.length);
    }

    // Out: a second sheet melts down over the screen and the next page lifts the same pink away.
    const second = meltSheet(2);
    await second.cover();
    try {
        window.sessionStorage.setItem('brread-arrive', String(Date.now()));
    } catch {
        // Without storage the menu simply appears.
    }
    window.location.href = redirect;
}

/* ---------- the way out is shut ---------- */

const pick = (list) => list[Math.floor(Math.random() * list.length)];

const TAUNTS = {
    back: [
        ['cd /brread', 'permission denied: jalan baliknya ditutup.'],
        ['cd ..', 'ga ada ".." disini. cuma kewer.'],
        ['git checkout main', 'error: kesabaran kamu belum di-commit.'],
        ['ssh brread.shop', 'connection refused. duduk manis aja.'],
    ],
    reload: [
        ['sudo reload --force', 'ctrl+R? tombolnya udah kami ambil.'],
        ['F5', 'refresh lagi sibuk nonton kewer kewer.'],
        ['kill -9 this_page', 'operation not permitted.'],
        ['location.reload()', 'reload-nya ikut nonton kewer.'],
    ],
    nav: [
        ['cd /', 'permission denied: mau kemana sih?'],
        ['open https://brread', 'error: kamu masih dihukum, balik sini.'],
        ['curl -L kabur.id', 'connection refused. dibawa pulang.'],
        ['xdg-open /menu', 'ga ada menu buat yang kabur.'],
    ],
};

const JABS = [
    'nice try. percobaan kabur #N tercatat.',
    'tombol back-nya udah pulang duluan.',
    'history udah hilang. cuma kewer yang tersisa.',
    'masih disini? videonya belum abis.',
    'percobaan kabur #N. jawabannya tetep nggak.',
];

const CROWD = [
    ['no_escape.exe', 'ga ada jalan keluar.'],
    ['kewer.exe', 'tonton sampai habis.'],
    ['ayam_bakar.exe', 'ayamnya udah kabur.'],
    ['laporan.pdf', 'sudah dikirim ke admin.'],
    ['ip_logger.exe', 'IP kamu sudah tercatat.'],
    ['pasrah.bat', 'pasrah aja, lebih tenang.'],
    ['kapok.sh', 'kapok belom?'],
    ['sabar.txt', 'sebentar lagi. mungkin.'],
];

const harassLines = () => [
    S.place ? `${S.place} ga jauh kok.` : 'kami tau kamu dimana.',
    'kami lihat kamu.',
    'masih disini? sabar ya.',
    'nonton aja yang anteng.',
    'kapok belom?',
    'jangan tutup tabnya... eh, boleh sih.',
    'IP kamu udah tercatat.',
];

/* ---------- sounds that make the way out miserable ---------- */

/** A game show "wrong answer" buzzer: detuned square waves that sag. */
function buzzer(level = 1) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    const peak = Math.min(0.2 + level * 0.02, 0.32);
    const gain = context.createGain();
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(peak, t + 0.012);
    gain.gain.setValueAtTime(peak, t + 0.27);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.36);
    gain.connect(context.destination);
    [150, 157, 75].forEach((frequency, index) => {
        const osc = context.createOscillator();
        osc.type = index === 2 ? 'sawtooth' : 'square';
        osc.frequency.setValueAtTime(frequency, t);
        osc.frequency.exponentialRampToValueAtTime(frequency * 0.8, t + 0.34);
        osc.connect(gain);
        osc.start(t);
        osc.stop(t + 0.38);
    });
}

/** A clown horn: "pa-PAA". */
function honk() {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    const band = context.createBiquadFilter();
    band.type = 'bandpass';
    band.frequency.value = 1300;
    band.Q.value = 1.6;
    band.connect(context.destination);
    [[0, 0.1, 300], [0.12, 0.3, 430]].forEach(([at, length, frequency]) => {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(frequency, t + at);
        osc.frequency.exponentialRampToValueAtTime(frequency * 0.78, t + at + length);
        gain.gain.setValueAtTime(0.0001, t + at);
        gain.gain.exponentialRampToValueAtTime(0.34, t + at + 0.015);
        gain.gain.setValueAtTime(0.34, t + at + length - 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + at + length);
        osc.connect(gain).connect(band);
        osc.start(t + at);
        osc.stop(t + at + length + 0.02);
    });
}

/** The desktop "error" ding. */
function ding(pitch = 880) {
    const context = ready();
    if (!context) return;
    const t = context.currentTime;
    [[0, pitch], [0.13, pitch * 0.75]].forEach(([at, frequency]) => {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'triangle';
        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, t + at);
        gain.gain.exponentialRampToValueAtTime(0.3, t + at + 0.008);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + at + 0.5);
        osc.connect(gain).connect(context.destination);
        osc.start(t + at);
        osc.stop(t + at + 0.52);
    });
}

/** Sans, laughing: a quick, high run of voice blips. */
function giggle(count = 8) {
    const context = ready();
    if (!context) return;
    let at = context.currentTime;
    for (let i = 0; i < count; i++) {
        sansAt(at, 1.25 + (i % 2) * 0.22 + Math.random() * 0.08, 0.07, 0.62);
        at += 0.085;
    }
}

/** "fahh", chopped and stuttered, each repeat higher and from another side of the head. */
function stutterFahh(times, level) {
    for (let i = 0; i < times; i++) {
        later(() => playSound(FAHH, {
            volume: Math.min(0.85 + level * 0.03, 1.2),
            rate: 0.75 + i * 0.2 + Math.random() * 0.12,
            pan: (Math.random() * 2 - 1) * 0.85,
        }), i * 170);
    }
}

/**
 * Every attempt to leave gets a noise that is meant to be unbearable, and it gets worse each time:
 * a buzzer, an error ding, a stuttering "fahh", then a horn, Sans laughing, and finally a slammed
 * boom with a siren.
 */
function annoy(level = 1) {
    awake();
    buzzer(level);
    later(() => ding(700 + Math.random() * 500), 120);
    stutterFahh(Math.min(1 + Math.floor(level / 2), 4), level);
    if (level % 2 === 0) later(honk, 300);
    if (level >= 3) later(() => giggle(5 + Math.min(level, 8)), 440);
    if (level >= 5) {
        playSound(HIT, { volume: 1, rate: 0.7 });
        alarm(1.1);
    }
}

/** Big red words over the video for a moment. */
function whisperTv(text, ms = 2200) {
    if (!S.tv) return;
    const word = el('p', 'prank-tv-whisper', text);
    S.tv.append(word);
    if (!S.reduced) word.animate([{ opacity: 0, transform: 'translate(-50%, -50%) scale(1.3)' }, { opacity: 1, transform: 'translate(-50%, -50%) scale(1)' }], { duration: 160, fill: 'both' });
    later(() => word.remove(), ms);
}

/** The video tears for a moment: dark colour shifts and slices, never a bright flash. */
function glitchTv() {
    if (!S.tv || S.reduced) return;
    S.tv.classList.add('is-glitching');
    later(() => S.tv?.classList.remove('is-glitching'), 950);
}

/** Something creepy every few seconds, so sitting through the video is not restful. */
function harass() {
    if (!S.tv) return;
    if (S.bsod) {
        S.harass = later(harass, 3000);
        return;
    }
    whisperTv(pick(harassLines()));
    glitchTv();
    heartbeat(2);
    chirps(10);
    pick([() => giggle(9), honk, () => ding(600 + Math.random() * 400), () => buzzer(2)])();
    S.harass = later(harass, 7000 + Math.random() * 6000);
}

/** A little window nobody asked for. Closing it opens two more, up to a crowd. */
function spawnWindow() {
    if (S.bsod) return;
    const root = stage();
    if (root.querySelectorAll('.prank-mini').length >= 16) return;

    const [title, text] = pick(CROWD);
    const { box, body, close } = sandwich(title);
    box.classList.add('prank-mini', 'prank-term--dark');
    body.append(el('p', 'is-err', text));
    root.append(box);

    const w = Math.min(260, window.innerWidth - 36);
    box.style.width = `${w}px`;
    box.style.left = `${18 + Math.random() * Math.max(0, window.innerWidth - w - 36)}px`;
    box.style.top = `${56 + Math.random() * Math.max(0, window.innerHeight - 190)}px`;
    if (!S.reduced) box.animate([{ transform: 'scale(.4)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }], { duration: 220, easing: 'cubic-bezier(.2,.9,.3,1)' });
    ding(520 + Math.random() * 700);

    close.addEventListener('click', () => {
        box.remove();
        buzzer(1);
        later(spawnWindow, 80);
        later(spawnWindow, 220);
    });
}

/** A terminal pops up to make fun of whoever tried to leave: Back to Brread, Ctrl+R, F5, the Back button. */
async function taunt(kind) {
    if (S.bsod) return;

    // Every try to leave asks for the whole screen again, and a burst of them brings the blue screen.
    forceFullscreen();
    if (S.tv) {
        const now = Date.now();
        S.tries = S.tries.filter((time) => now - time < SPAM_WINDOW);
        S.tries.push(now);
        if (S.tries.length >= SPAM_TRIES) {
            bsod();
            return;
        }
    }

    S.hits = (S.hits || 0) + 1;
    const [command, refusal] = pick(TAUNTS[kind] || TAUNTS.back);

    shake(S.tv || card(), Math.min(30, 12 + S.hits * 3));
    glitchTv();
    annoy(S.hits);
    chirps(8);
    if (S.hits % 4 === 0) {
        whisperTv('KAPOK?', 1600);
        alarm(1.3);
    }
    for (let i = 0; i < Math.min(1 + S.hits, 5); i++) later(spawnWindow, 500 + i * 160);

    S.taunting?.dismiss();
    const term = terminal('escape.sh', { width: 400, place: 'center', dark: true });
    S.taunting = term;
    await term.run([
        ['cmd', command],
        ['err', refusal],
        ['say', pick(JABS).replace('#N', `#${S.hits}`)],
        ['out', 'jalan keluar: tutup tab, atau nikmatin videonya.'],
    ]);
    await wait(2000);
    if (S.taunting === term) {
        S.taunting = null;
        await term.dismiss();
    }
}

/**
 * While the address is paused the page cannot be left by the usual ways: Ctrl+R, F5, the Back
 * button and every link only get a terminal that mocks the try. The browser's own reload button and
 * closing the tab cannot be stopped by a website (reload lands on the same
 * paused page), and the trap lets go the moment the pause is over.
 */
function arm() {
    if (S.trap) return;

    const stop = new AbortController();
    const on = (target, type, listener, options = {}) => target.addEventListener(type, listener, { ...options, signal: stop.signal });
    const bait = () => {
        try {
            history.pushState({ prank: Date.now() }, '', window.location.href);
        } catch {
            // No history to fill.
        }
    };

    bait();
    bait();
    // Browsers skip history entries that never got a click, so one more is added on the first touch.
    on(window, 'pointerdown', bait, { once: true });
    on(window, 'keydown', bait, { once: true });
    on(window, 'popstate', () => {
        bait();
        taunt('back');
    });
    on(window, 'keydown', (event) => {
        const key = event.key.toLowerCase();
        const mod = event.ctrlKey || event.metaKey;
        const reload = event.key === 'F5' || (mod && key === 'r');
        const back = (event.altKey && event.key === 'ArrowLeft') || (event.metaKey && event.key === '[');
        if (!reload && !back) return;

        event.preventDefault();
        event.stopPropagation();
        taunt(reload ? 'reload' : 'back');
    }, { capture: true });
    on(document, 'contextmenu', (event) => event.preventDefault());
    on(document, 'click', (event) => {
        if (!event.target.closest?.('a[href]')) return;

        event.preventDefault();
        event.stopPropagation();
        taunt('back');
    }, { capture: true });

    S.trap = stop;
}

function release() {
    window.clearTimeout(S.harass);
    S.trap?.abort();
    S.trap = null;
    S.taunting?.dismiss();
    S.taunting = null;
}

/* ---------- entry points ---------- */

/**
 * Plays the scene the server picked for a wrong password: { stage: 1-4 | 'paused', ip, city, country, retry_after }.
 */
export function play(scene, { context = null } = {}) {
    adopt(context);
    preload();

    // The windows of miss 1 and 2 stay on screen until the trace (miss 3) takes over.
    if (scene.stage !== 2) {
        [...S.terms].forEach((term) => term.dismiss());
        S.current?.dismiss();
        S.current = null;
    }

    const run = {
        1: sceneOne,
        2: sceneTwo,
        3: sceneThree,
        4: sceneFour,
        paused: (s) => party(s.retry_after || 60, s.ip),
    }[scene.stage];

    return run ? Promise.resolve(run(scene)).catch(() => {}) : Promise.resolve();
}

/** A page opened (or reloaded) while its address is still paused. */
export function resumePause(seconds, ip) {
    preload();

    // A blue screen can stretch the pause past what the server remembers: the longer one wins.
    const kept = readHold();
    const left = kept?.until ? Math.ceil((kept.until - Date.now()) / 1000) : 0;
    party(Math.max(seconds, left), ip || kept?.ip || null, null);

    // Coming back to the paused page is an escape attempt that deserves its own answer: the guard
    // flags a bounce from another address, and the browser says whether this load was a reload or Back.
    let flag = null;
    try {
        flag = window.sessionStorage.getItem('brread-bounce');
        window.sessionStorage.removeItem('brread-bounce');
    } catch {
        // No storage, no flag.
    }
    const type = window.performance?.getEntriesByType?.('navigation')?.[0]?.type;
    const kind = flag ? 'nav' : type === 'reload' ? 'reload' : type === 'back_forward' ? 'back' : null;
    if (kind) later(() => taunt(kind), 1400);
}

/** The server has no pause, but the browser still holds one (the blue screen made it longer): carry on with that. */
export function resumeHold() {
    const kept = readHold();
    const left = kept?.until ? Math.ceil((kept.until - Date.now()) / 1000) : 0;
    if (left > 0) resumePause(left, kept.ip || null);
    else letGo();
}
