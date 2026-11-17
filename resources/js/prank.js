// What a person meets when they guess passwords on /staff or /superadmin by hand.
// Wrong password 1, 2 and 3 each pop up a cheeky terminal window with the "fahh" sound (the
// third one reads out their real IP, city and provider); on the fourth the terminal grows to
// fill the screen, switches on like an old TV and, Rickroll style, plays the "Kewer Kewer" video
// while sign-in is paused for that address (1, 3, then 5 minutes; the server keeps the real
// lock). Loaded on demand from app.js.

const VIDEO = '/video/prank-kewer.mp4';
const HIT = '/audio/lockdown-hit.mp3';
const FAHH = '/audio/prank-fahh.mp3';

const S = {
    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    context: null,
    bytes: {},
    buffers: {},
    root: null,
    busy: null,
    party: null,
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

    preloading = Promise.all([FAHH].map(async (url) => {
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

async function playSound(url, { loop = false, volume = 1, analyser = false } = {}) {
    const context = S.context;
    const audio = await buffer(url);
    if (!context || !audio) return null;

    const source = context.createBufferSource();
    const gain = context.createGain();
    source.buffer = audio;
    source.loop = loop;
    gain.gain.value = volume;
    source.connect(gain);

    let meter = null;
    if (analyser) {
        meter = context.createAnalyser();
        meter.fftSize = 256;
        gain.connect(meter);
    }

    gain.connect(context.destination);
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
 * A small terminal that pops up next to the form and types a script:
 * ['cmd', text] types a command, ['out'|'ok'|'err'|'say'|'big', text] prints a line,
 * ['bar', label] runs a progress bar, ['wait', ms] pauses.
 */
function terminal(title, { width = 380, place = 'right' } = {}) {
    const box = el('div', 'prank-term');
    box.style.width = `${Math.min(width, window.innerWidth - 24)}px`;
    box.setAttribute('role', 'status');

    const bar = el('div', 'prank-term-bar');
    const dots = el('span', 'prank-term-dots');
    dots.append(el('i'), el('i'), el('i'));
    const name = el('span', 'prank-term-title', title);
    const close = el('button', 'prank-term-x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close');
    bar.append(dots, name, close);

    const body = el('div', 'prank-term-body');
    const cursor = el('span', 'prank-term-cursor');
    body.append(cursor);
    box.append(bar, body);
    stage().append(box);

    // Where it pops up: beside the form on wide screens, under the header on narrow ones.
    const form = card()?.getBoundingClientRect();
    const w = box.offsetWidth;
    const h = Math.max(box.offsetHeight, 220);
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
                } else if (kind === 'cmd') {
                    const line = el('p', 'is-cmd');
                    body.insertBefore(line, cursor);
                    await typeInto(line, text, 34);
                    await wait(220);
                } else if (kind === 'bar') {
                    await progress(body, cursor, text);
                } else {
                    const line = el('p', `is-${kind}`, text);
                    body.insertBefore(line, cursor);
                    if (!S.reduced) line.animate([{ opacity: 0, transform: 'translateX(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 160 });
                    await wait(kind === 'big' ? 420 : 260);
                }
                body.scrollTop = body.scrollHeight;
            }
        },

        async dismiss() {
            if (closed) return;
            closed = true;
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

    return term;
}

async function typeInto(line, text, speed) {
    if (S.reduced) {
        line.textContent = text;
        return;
    }
    for (const letter of text) {
        line.textContent += letter;
        await wait(speed + Math.random() * 30);
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
        ['say', 'caught you. bored hands, huh?'],
        ['out', 'relax, that was only try 1 of 4.'],
    ]);
    await wait(2600);
    await term.dismiss();
}

async function sceneTwo() {
    shake(card(), 14);
    fahh();
    const term = terminal('guess_password.exe', { place: 'left' });
    await term.run([
        ['cmd', './guess_password --mode=random'],
        ['bar', 'guessing'],
        ['err', 'still wrong, chief'],
        ['say', 'go on, press it again. maybe you get lucky.'],
        ['out', 'hint: it is not "password123"'],
        ['out', 'lives left: ♥ ♥ ♡'],
    ]);
    await wait(2600);
    await term.dismiss();
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
    const label = el('p', 'prank-trace-label', 'tracing signal...');
    const ip = el('p', 'prank-trace-ip', '');
    ip.dataset.text = '';
    const city = el('p', 'prank-trace-city', '');
    const net = el('p', 'prank-trace-net', '');
    const whisper = el('p', 'prank-trace-whisper', '');
    const info = el('div', 'prank-trace-info');
    info.append(label, ip, city, net, whisper);
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
        label.textContent = 'target locked';
        ip.textContent = scene.ip || '???';
        city.textContent = place.toUpperCase();
        city.dataset.text = city.textContent;
        net.textContent = scene.isp ? `via ${scene.isp}` : '';
        whisper.textContent = `oh, so you're from ${(scene.city || 'somewhere').toLowerCase()}... we know where you are.`;
    } else {
        sight.classList.add('is-hunting');
        riser(1.3);
        await wait(1300);
        if (closed) return;
        sight.classList.add('is-locked');
        label.textContent = 'target locked';
        playSound(HIT, { volume: 0.8 });
        heartbeat(5);

        await decodeInto(ip, scene.ip || '???', tickSound);
        if (closed) return;
        trace.classList.add('is-hit');

        await wait(350);
        city.textContent = (place || 'location unknown').toUpperCase();
        city.dataset.text = city.textContent;
        city.classList.add('is-in');
        stinger();
        await wait(450);
        if (scene.isp) net.textContent = `via ${scene.isp}`;
        net.classList.add('is-in');

        await wait(700);
        if (closed) return;
        fahh();
        const breath = whisperSound(5.5);
        await typeInto(whisper, `oh, so you're from ${(scene.city || 'somewhere').toLowerCase()}...`, 55);
        await wait(500);
        if (closed) return;
        await typeInto(whisper, '\nwe know where you are.', 70);
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

async function sceneFour(scene) {
    curse();
    arm();
    shake(card(), 18);
    fahh();
    const seconds = scene.retry_after || 60;
    const term = terminal('punishment.sh', { width: 460, place: 'center' });
    stage().classList.add('is-dim');
    lockForm(seconds);
    await term.run([
        ['cmd', 'sudo rm -rf patience/'],
        ['err', 'the admin is out of patience.'],
        ['out', `sign-in locked for ${clockText(seconds)} for IP ${scene.ip || 'yours'}.`],
        ['cmd', 'play punishment.mp4 --fullscreen'],
        ['bar', 'loading'],
        ['big', '3'],
        ['big', '2'],
        ['big', '1'],
    ]);
    if (term.closed) return;
    S.current = null;
    await party(seconds, scene.ip, term.box);
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
    lockForm(seconds);
    curse();
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

    let raf = 0;

    const start = async () => {
        if (!tv.isConnected) return;
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

    if (from?.isConnected && !S.reduced) {
        // The terminal swells to fill the screen, then the screen switches on like an old TV.
        tv.style.opacity = '0';
        start();
        await grow(from);
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

    const until = Date.now() + seconds * 1000;
    let timer = 0;
    const tick = () => {
        const secs = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        clock.textContent = `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
        if (secs <= 0) end();
    };

    const finish = async () => {
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
        release();
        uncurse();
        unlockForm();
        if (tv.isConnected) finish();
    };

    tick();
    timer = window.setInterval(tick, 250);
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
export function curse() {
    if (S.curse) return;
    const body = document.body;
    const main = document.querySelector('main');
    const formCard = card();
    if (!main || !formCard) return;

    body.classList.add('is-broken');
    const timers = [];
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

    S.curse = () => {
        timers.forEach((timer) => window.clearInterval(timer));
        restore.forEach(([node, prop, value]) => {
            node[prop] = value;
        });
        [defs, ghost, junk].forEach((node) => node.remove());
        body.classList.remove('is-broken');
    };
}

function uncurse() {
    S.curse?.();
    S.curse = null;
}

/* ---------- the way out is shut ---------- */

const pick = (list) => list[Math.floor(Math.random() * list.length)];

const TAUNTS = {
    back: [
        ['cd /brread', 'permission denied: the way back is closed.'],
        ['cd ..', 'there is no ".." here. only kewer.'],
        ['git checkout main', 'error: you have unsaved patience.'],
        ['ssh brread.shop', 'connection refused. take a seat.'],
    ],
    reload: [
        ['sudo reload --force', 'ctrl+R? we took that key away.'],
        ['F5', 'refresh is busy watching kewer kewer.'],
        ['kill -9 this_page', 'operation not permitted.'],
    ],
};

const JABS = [
    'nice try. escape attempt #N logged.',
    'the back button has left the building.',
    'history is gone. only kewer remains.',
    'still here? the video is not over.',
    'attempt #N to leave. the answer is still no.',
];

/** A terminal pops up to make fun of whoever tried to leave: Back to Brread, Ctrl+R, F5, the Back button. */
async function taunt(kind) {
    S.hits = (S.hits || 0) + 1;
    const [command, refusal] = pick(TAUNTS[kind] || TAUNTS.back);

    shake(document.querySelector('.prank-tv') || card(), 16);
    fahh();

    S.taunting?.dismiss();
    const term = terminal('escape.sh', { width: 400, place: 'center' });
    S.taunting = term;
    await term.run([
        ['cmd', command],
        ['err', refusal],
        ['say', pick(JABS).replace('N', String(S.hits))],
        ['out', 'exits: close the tab, or enjoy the video.'],
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
 * closing the tab cannot be stopped by a website (reload at least asks first, and lands on the same
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
    on(window, 'beforeunload', (event) => {
        event.preventDefault();
        event.returnValue = '';
    });
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

    // A quicker next miss never gets lost: the window still on screen makes way for the new one.
    S.current?.dismiss();
    S.current = null;

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
    party(seconds, ip, null);
}
