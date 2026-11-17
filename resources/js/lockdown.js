// The "system locked" screen. The sign-in form glitches with a synthesized static burst, then
// the page turns on whoever is knocking: music, a heartbeat that speeds up, the dark closing in,
// blood rising from the floor, eyes opening in the dark, hand prints slamming onto the glass, a
// figure that steps closer every few seconds and, once it is close enough, its face.
// Everything loads only when a lockdown is triggered, so normal sign-ins never download it.
//
// Nothing here flashes bright: every flicker is dark on dark and slower than three a second.

const MUSIC_VOLUME = 0.8;

let started = false;

const S = {
    root: null,
    card: null,
    main: null,
    context: null,
    out: null,
    music: null,
    musicGain: null,
    analyser: null,
    reduced: false,
    small: false,
    trace: {},
    name: 'stranger',
    born: 0,
    pointer: { x: window.innerWidth / 2, y: window.innerHeight / 2, moved: true },
    lastShake: 0,
    eyes: [],
};

const rand = (min, max) => min + Math.random() * (max - min);
const pick = (list) => list[Math.floor(Math.random() * list.length)];
const later = (fn, ms) => setTimeout(fn, ms);

function closeContext(context) {
    try {
        context?.close?.().catch(() => {});
    } catch {
        // Already closed.
    }
}

/* ------------------------------------------------------------------ sound */

function makeContext() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        return AudioContextClass ? new AudioContextClass() : null;
    } catch {
        return null;
    }
}

/**
 * One graph for everything: synthesized effects and the music both go through a compressor,
 * and the music also feeds an analyser so the glow and the shakes follow its low end.
 */
function setupAudio(context) {
    S.music = document.getElementById('lockdown-music');
    S.context = context || makeContext();
    const ctx = S.context;
    if (!ctx) return;

    const limiter = ctx.createDynamicsCompressor();
    limiter.threshold.value = -12;
    limiter.ratio.value = 8;
    limiter.connect(ctx.destination);
    S.out = ctx.createGain();
    S.out.gain.value = 0.9;
    S.out.connect(limiter);

    if (S.music) {
        try {
            const source = ctx.createMediaElementSource(S.music);
            S.musicGain = ctx.createGain();
            S.musicGain.gain.value = 0;
            source.connect(S.musicGain);
            S.musicGain.connect(S.out);
            S.analyser = ctx.createAnalyser();
            S.analyser.fftSize = 256;
            S.analyser.smoothingTimeConstant = 0.6;
            source.connect(S.analyser);
        } catch {
            S.musicGain = null;
            S.analyser = null;
        }
    }
}

function playMusic() {
    const music = S.music;
    if (!music) return;
    music.preload = 'auto';
    if (S.musicGain && S.context) {
        const now = S.context.currentTime;
        S.musicGain.gain.cancelScheduledValues(now);
        S.musicGain.gain.setValueAtTime(0, now);
        S.musicGain.gain.linearRampToValueAtTime(MUSIC_VOLUME, now + 1.6);
    } else {
        music.volume = MUSIC_VOLUME;
    }
    music.play().catch(() => {
        // No click yet (a page that opened already locked); the first touch of anything starts it.
    });
}

/** A page that opens already locked has no click behind it yet: the first touch starts the sound. */
function unlockSoundOnFirstTouch() {
    const go = () => {
        S.context?.resume?.();
        if (S.music?.paused) playMusic();
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => document.addEventListener(type, go, { once: true, capture: true }));
}

let noiseCache = null;
function noiseBuffer(ctx) {
    if (noiseCache) return noiseCache;
    const length = Math.floor(ctx.sampleRate * 2);
    noiseCache = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = noiseCache.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
    return noiseCache;
}

function live() {
    return S.context && S.out && S.context.state === 'running';
}

function envelope(ctx, at, peak, attack, release) {
    const gain = ctx.createGain();
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(peak, at + attack);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + attack + release);
    return gain;
}

function tone(type, from, to, at, peak, attack, release, destination = S.out) {
    const ctx = S.context;
    const osc = ctx.createOscillator();
    osc.type = type;
    osc.frequency.setValueAtTime(from, at);
    osc.frequency.exponentialRampToValueAtTime(to, at + attack + release);
    const gain = envelope(ctx, at, peak, attack, release);
    osc.connect(gain);
    gain.connect(destination);
    osc.start(at);
    osc.stop(at + attack + release + 0.05);
    return osc;
}

function burst(at, length, peak, filterType, from, to, q = 1, pan = 0) {
    const ctx = S.context;
    const source = ctx.createBufferSource();
    source.buffer = noiseBuffer(ctx);
    const filter = ctx.createBiquadFilter();
    filter.type = filterType;
    filter.Q.value = q;
    filter.frequency.setValueAtTime(from, at);
    filter.frequency.exponentialRampToValueAtTime(to, at + length);
    const gain = envelope(ctx, at, peak, Math.min(0.05, length / 4), length);
    source.connect(filter);
    filter.connect(gain);
    if (pan && ctx.createStereoPanner) {
        const panner = ctx.createStereoPanner();
        panner.pan.value = pan;
        gain.connect(panner);
        panner.connect(S.out);
    } else {
        gain.connect(S.out);
    }
    source.start(at, Math.random());
    source.stop(at + length + 0.1);
}

/** About a second of digital static, a falling zap and a low thump. */
function glitchSound() {
    const ctx = S.context;
    const start = ctx.currentTime;
    const length = Math.floor(ctx.sampleRate * 1.1);
    const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = buffer.getChannelData(0);
    let held = 0;
    for (let i = 0; i < length; i++) {
        if (i % 18 === 0) held = Math.random() * 2 - 1;
        data[i] = held;
    }
    const noise = ctx.createBufferSource();
    noise.buffer = buffer;
    const band = ctx.createBiquadFilter();
    band.type = 'bandpass';
    band.Q.value = 0.9;
    band.frequency.setValueAtTime(500, start);
    band.frequency.exponentialRampToValueAtTime(5000, start + 0.9);
    const stutter = ctx.createGain();
    stutter.gain.value = 0;
    for (let t = 0; t < 0.95; t += 0.045) {
        stutter.gain.setValueAtTime(Math.random() > 0.4 ? 0.5 : 0, start + t);
    }
    noise.connect(band);
    band.connect(stutter);
    stutter.connect(S.out);
    noise.start(start);
    noise.stop(start + 1);

    tone('sawtooth', 1400, 70, start, 0.16, 0.01, 0.74);
    tone('sine', 70, 30, start + 0.95, 0.9, 0.01, 0.8);
}

/** Lub-dub. */
function heartbeatSound(strength) {
    if (!live()) return;
    const at = S.context.currentTime + 0.01;
    tone('sine', 72, 42, at, 0.55 * strength, 0.012, 0.22);
    tone('triangle', 140, 80, at, 0.12 * strength, 0.01, 0.12);
    tone('sine', 60, 38, at + 0.19, 0.4 * strength, 0.012, 0.2);
}

/** A body hitting glass. */
function thudSound(strength = 1) {
    if (!live()) return;
    const at = S.context.currentTime + 0.005;
    tone('sine', 110, 38, at, 0.8 * strength, 0.005, 0.35);
    burst(at, 0.18, 0.5 * strength, 'lowpass', 1800, 300, 0.7, rand(-0.6, 0.6));
}

/** The sting when the figure moves: a scrape, a screech and a low swell. */
function stingSound() {
    if (!live()) return;
    const ctx = S.context;
    const at = ctx.currentTime + 0.01;
    burst(at, 0.9, 0.35, 'bandpass', 900, 4200, 6, rand(-0.8, 0.8));
    [1760, 1867, 1990].forEach((f) => tone('sawtooth', f, f * 0.94, at, 0.035, 0.03, 1.1));
    tone('sawtooth', 55, 41, at, 0.22, 0.2, 1.4);
    tone('sawtooth', 58.3, 43, at, 0.18, 0.2, 1.4);
}

/** Breath close to one ear. */
function breathSound() {
    if (!live()) return;
    const at = S.context.currentTime + 0.01;
    const pan = pick([-0.9, 0.9]);
    burst(at, 1.6, 0.28, 'bandpass', 700, 1300, 1.6, pan);
    burst(at + 0.15, 1.2, 0.12, 'highpass', 2600, 4800, 0.8, pan);
}

/** Short crackle for a key press. */
function crackleSound() {
    if (!live()) return;
    const at = S.context.currentTime + 0.005;
    burst(at, 0.22, 0.35, 'highpass', 2000, 6000, 0.7, rand(-0.5, 0.5));
    tone('square', 220, 55, at, 0.06, 0.005, 0.2);
}

/** The scream that comes with the face. */
function screamSound() {
    if (!live()) return;
    const ctx = S.context;
    const at = ctx.currentTime + 0.005;
    const bus = ctx.createGain();
    bus.gain.value = 1;
    const vibrato = ctx.createOscillator();
    vibrato.frequency.value = 23;
    const depth = ctx.createGain();
    depth.gain.value = 26;
    vibrato.connect(depth);
    vibrato.start(at);
    vibrato.stop(at + 1.2);
    bus.connect(S.out);
    [420, 447, 466, 503].forEach((f) => {
        const osc = tone('sawtooth', f, f * 0.42, at, 0.13, 0.02, 1.0, bus);
        depth.connect(osc.frequency);
    });
    burst(at, 1.0, 0.7, 'bandpass', 1200, 3400, 1.2);
    tone('sine', 90, 28, at, 1, 0.005, 0.9);
}

/* ------------------------------------------------------------- movement */

function shake(power = 1) {
    if (S.reduced) return;
    const now = performance.now();
    if (now - S.lastShake < 450) return;
    S.lastShake = now;
    const d = 4 + 10 * power;
    const frames = [{ transform: 'translate(0, 0)' }];
    for (let i = 0; i < 5; i++) {
        frames.push({ transform: `translate(${rand(-d, d).toFixed(1)}px, ${rand(-d / 2, d / 2).toFixed(1)}px) rotate(${rand(-0.6, 0.6).toFixed(2)}deg)` });
    }
    frames.push({ transform: 'translate(0, 0)' });
    S.main?.animate(frames, { duration: 320, easing: 'linear' });
    S.root.querySelector('[data-whisper]')?.animate(frames, { duration: 320, easing: 'linear', composite: 'add' });
}

/** Lub-dub on screen: the dark around the card tightens with each beat. */
function heartbeat() {
    const pulse = S.root.querySelector('[data-pulse]');
    const beat = () => {
        const elapsed = (performance.now() - S.born) / 1000;
        const bpm = Math.min(132, 54 + elapsed * 0.9);
        const strength = Math.min(1, 0.55 + elapsed / 120);
        pulse?.animate(
            [
                { transform: 'scale(1)' },
                { transform: 'scale(0.9)', offset: 0.12 },
                { transform: 'scale(0.98)', offset: 0.28 },
                { transform: 'scale(0.93)', offset: 0.4 },
                { transform: 'scale(1)' },
            ],
            { duration: Math.min(800, 60000 / bpm), easing: 'ease-out' },
        );
        if (!S.analyser) {
            S.root.querySelector('[data-glow]')?.animate([{ opacity: 0.1 }, { opacity: 0.45 * strength, offset: 0.15 }, { opacity: 0.1 }], { duration: 700 });
        }
        heartbeatSound(strength);
        later(beat, 60000 / bpm);
    };
    later(beat, 600);
}

/** One loop per frame: the glow follows the music, the eyes follow the cursor. */
function frameLoop() {
    const glow = S.root.querySelector('[data-glow]');
    const bins = S.analyser ? new Uint8Array(S.analyser.frequencyBinCount) : null;
    let level = 0;
    let average = 0;

    const step = () => {
        if (bins) {
            S.analyser.getByteFrequencyData(bins);
            let sum = 0;
            for (let i = 1; i <= 6; i++) sum += bins[i];
            const bass = sum / (6 * 255);
            level += (bass - level) * 0.25;
            average += (bass - average) * 0.02;
            if (glow) glow.style.opacity = Math.min(0.6, 0.06 + level * 0.65).toFixed(3);
            if (bass > 0.6 && bass - average > 0.2) shake(Math.min(1, (bass - average) * 2));
        }

        if (S.pointer.moved) {
            S.pointer.moved = false;
            S.eyes.forEach((eye) => {
                if (!eye.open) return;
                const dx = S.pointer.x - eye.cx;
                const dy = S.pointer.y - eye.cy;
                const len = Math.hypot(dx, dy) || 1;
                const reach = eye.size * 0.28;
                const x = ((dx / len) * reach).toFixed(1);
                const y = ((dy / len) * reach * 0.35).toFixed(1);
                eye.pupils.forEach((pupil) => {
                    pupil.style.transform = `translate(${x}px, ${y}px)`;
                });
            });
        }
        requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

/* ----------------------------------------------------------------- eyes */

function freeSpot(margin = 60) {
    const box = S.card?.getBoundingClientRect();
    for (let tries = 0; tries < 12; tries++) {
        const x = rand(margin, window.innerWidth - margin);
        const y = rand(margin, window.innerHeight * 0.8);
        if (!box || x < box.left - 80 || x > box.right + 80 || y < box.top - 120 || y > box.bottom + 60) return { x, y };
    }
    return { x: rand(margin, window.innerWidth * 0.2), y: rand(margin, window.innerHeight * 0.7) };
}

function eyes() {
    const holder = S.root.querySelector('[data-eyes]');
    if (!holder) return;
    const count = S.small ? 4 : 9;

    for (let i = 0; i < count; i++) {
        const element = document.createElement('div');
        element.className = 'lockdown-eye';
        element.innerHTML = '<i><b></b></i><i><b></b></i>';
        holder.append(element);
        const eye = { element, pupils: [...element.querySelectorAll('b')], open: false, cx: 0, cy: 0, size: 20 };
        S.eyes.push(eye);

        const place = () => {
            const spot = freeSpot();
            eye.size = rand(10, 34);
            element.style.fontSize = `${eye.size.toFixed(1)}px`;
            element.style.left = `${spot.x.toFixed(0)}px`;
            element.style.top = `${spot.y.toFixed(0)}px`;
            element.style.opacity = (0.35 + (eye.size / 34) * 0.6).toFixed(2);
            eye.cx = spot.x + eye.size;
            eye.cy = spot.y + eye.size * 0.2;
        };

        if (S.reduced) {
            if (i < 3) {
                place();
                element.classList.add('is-open');
            }
            continue;
        }

        const cycle = () => {
            place();
            later(() => {
                eye.open = true;
                S.pointer.moved = true;
                element.classList.add('is-open');
                const blinks = Math.floor(rand(0, 3));
                for (let b = 0; b < blinks; b++) {
                    later(() => {
                        element.classList.add('is-blinking');
                        later(() => element.classList.remove('is-blinking'), 120);
                    }, rand(900, 5000));
                }
                later(() => {
                    eye.open = false;
                    element.classList.remove('is-open');
                    later(cycle, rand(700, 3500));
                }, rand(3500, 9000));
            }, rand(200, 4000));
        };
        later(cycle, rand(1500, 7000));
    }
}

/* ---------------------------------------------------------- hands, blood */

function slamHand(x, y, rotation = rand(-25, 25)) {
    const holder = S.root.querySelector('[data-hands]');
    const template = S.root.querySelector('[data-hand-template]');
    if (!holder || !template || S.reduced) return;

    const hand = template.content.firstElementChild.cloneNode(true);
    const flip = Math.random() < 0.5 ? -1 : 1;
    hand.style.left = `${x}px`;
    hand.style.top = `${y}px`;
    holder.append(hand);
    while (holder.children.length > 9) holder.firstElementChild.remove();

    const base = `rotate(${rotation.toFixed(1)}deg) scaleX(${flip})`;
    hand.animate(
        [
            { opacity: 0, transform: `${base} scale(1.4)` },
            { opacity: 0.95, transform: `${base} scale(1)` },
        ],
        { duration: 110, easing: 'cubic-bezier(0.2, 0.9, 0.3, 1)', fill: 'forwards' },
    );
    hand.animate(
        [
            { opacity: 0.95, transform: `${base} translateY(0)` },
            { opacity: 0, transform: `${base} translateY(${rand(40, 90).toFixed(0)}px)` },
        ],
        { duration: rand(9000, 15000), delay: 1400, easing: 'ease-in', fill: 'forwards' },
    ).finished.then(() => hand.remove(), () => {});

    thudSound(0.9);
    shake(0.6);
}

function handsLoop() {
    const go = () => {
        const left = Math.random() < 0.5;
        const x = left ? rand(10, window.innerWidth * 0.32) : rand(window.innerWidth * 0.6, window.innerWidth - 150);
        slamHand(x, rand(40, window.innerHeight * 0.6));
        if (Math.random() < 0.3) later(() => slamHand(x + rand(-120, 120), rand(40, window.innerHeight * 0.6)), rand(250, 600));
        later(go, rand(5000, 11000));
    };
    later(go, 2600);
}

function splat(x, y) {
    const holder = S.root.querySelector('[data-hands]');
    if (!holder) return;
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '-75 -75 150 150');
    svg.setAttribute('class', 'lockdown-splat');
    const group = document.createElementNS(ns, 'g');
    group.setAttribute('filter', 'url(#lockdown-rough)');
    group.setAttribute('fill', 'url(#lockdown-blood-wet)');
    const dot = (cx, cy, r) => {
        const circle = document.createElementNS(ns, 'circle');
        circle.setAttribute('cx', cx.toFixed(1));
        circle.setAttribute('cy', cy.toFixed(1));
        circle.setAttribute('r', r.toFixed(1));
        group.append(circle);
    };
    dot(0, 0, rand(16, 24));
    for (let i = 0; i < 14; i++) {
        const angle = rand(0, Math.PI * 2);
        const distance = rand(18, 66);
        dot(Math.cos(angle) * distance, Math.sin(angle) * distance, rand(1.5, 8) * (1 - distance / 90));
    }
    svg.append(group);
    svg.style.left = `${x}px`;
    svg.style.top = `${y}px`;
    holder.append(svg);
    svg.animate([{ opacity: 0, transform: 'scale(0.3)' }, { opacity: 0.92, transform: 'scale(1)' }], { duration: 90, fill: 'forwards' });
    svg.animate([{ opacity: 0.92 }, { opacity: 0 }], { duration: 2500, delay: 5000, fill: 'forwards' }).finished.then(() => svg.remove(), () => {});
}

/* -------------------------------------------------------------- stalker */

// Where it stands (the centre, as a share of the screen width), how big, how clear.
const STEPS = [
    { x: 0.1, scale: 0.34, opacity: 0.5 },
    { x: 0.88, scale: 0.46, opacity: 0.62 },
    { x: 0.15, scale: 0.62, opacity: 0.75 },
    { x: 0.84, scale: 0.84, opacity: 0.88 },
    { x: 0.12, scale: 1.08, opacity: 0.96 },
    { x: 0.8, scale: 1.25, opacity: 1 },
];

/** Keeps where a finished animation left off, without piling up finished animations. */
function settle(animation) {
    animation.finished.then(() => {
        try {
            animation.commitStyles();
        } catch {
            // The element is gone.
        }
        animation.cancel();
    }, () => {});
    return animation;
}

function stalker() {
    const figure = S.root.querySelector('[data-stalker]');
    if (!figure) return;
    let index = 0;

    // The figure scales around its bottom centre, so placing its centre is enough.
    const pose = (step) => `translateX(${(step.x * window.innerWidth - figure.offsetWidth / 2).toFixed(0)}px) scale(${step.scale})`;

    if (S.reduced) {
        figure.style.opacity = '0.6';
        figure.style.transform = pose(STEPS[1]);
        return;
    }

    const move = () => {
        const step = STEPS[index];
        const from = getComputedStyle(figure).opacity;
        // Out in a stutter, in at the new spot in a stutter: never a bright flash.
        settle(figure.animate([{ opacity: from }, { opacity: 0, offset: 0.3 }, { opacity: from * 0.6, offset: 0.5 }, { opacity: 0 }], { duration: 260, fill: 'forwards' }))
            .finished.then(() => {
                figure.style.transform = pose(step);
                settle(figure.animate(
                    [{ opacity: 0 }, { opacity: step.opacity * 0.7, offset: 0.3 }, { opacity: 0.1, offset: 0.5 }, { opacity: step.opacity }],
                    { duration: 340, fill: 'forwards' },
                ));
                stingSound();
                shake(step.scale / 1.25);
            }, () => {});

        index += 1;
        if (index >= STEPS.length) {
            // Close enough. Show the face, then it is gone and starts again from far away.
            later(() => {
                scare();
                settle(figure.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 120, fill: 'forwards' }));
                index = 0;
                later(move, rand(9000, 14000));
            }, 3200);
            return;
        }
        later(move, rand(6500, 9500));
    };
    later(move, 3500);
}

function scare() {
    if (S.reduced) return;
    const element = S.root.querySelector('[data-scare]');
    if (!element) return;
    element.style.visibility = 'visible';
    const svg = element.querySelector('svg');
    svg?.animate(
        [
            { transform: 'scale(0.6)' },
            { transform: 'scale(1.12)', offset: 0.14 },
            { transform: 'scale(1.06) translate(-8px, 5px)', offset: 0.32 },
            { transform: 'scale(1.16) translate(7px, -4px)', offset: 0.55 },
            { transform: 'scale(1.1) translate(-3px, 2px)', offset: 0.8 },
            { transform: 'scale(1.35)' },
        ],
        { duration: 820, easing: 'linear' },
    );
    element
        .animate([{ opacity: 0 }, { opacity: 1, offset: 0.08 }, { opacity: 1, offset: 0.82 }, { opacity: 0 }], { duration: 820 })
        .finished.then(() => {
            element.style.visibility = 'hidden';
        }, () => {});
    screamSound();
    S.lastShake = 0;
    shake(1);
    later(() => {
        for (let i = 0; i < 5; i++) {
            const spot = freeSpot(80);
            splat(spot.x, spot.y);
        }
    }, 700);
}

/* -------------------------------------------------------------- words */

function upper(value) {
    return String(value || '').toUpperCase();
}

function whisper(text) {
    const holder = S.root.querySelector('[data-whisper]');
    if (!holder) return;
    holder.replaceChildren();
    const spans = [...text].map((character) => {
        const span = document.createElement('span');
        span.textContent = character;
        span.style.opacity = '0';
        holder.append(span);
        return span;
    });
    if (S.reduced) {
        spans.forEach((span) => {
            span.style.opacity = '1';
        });
        return;
    }
    breathSound();
    spans.forEach((span, i) => {
        span.animate(
            [
                { opacity: 0, transform: `translateY(${rand(-30, 30).toFixed(0)}px) scale(1.5)` },
                { opacity: 1, transform: 'translateY(0) scale(1)' },
            ],
            { duration: 700, delay: i * 65, easing: 'cubic-bezier(0.2, 0.8, 0.3, 1)', fill: 'forwards' },
        );
        span.animate(
            [
                { opacity: 1, transform: 'translateY(0) scale(1)' },
                { opacity: 0, transform: `translateY(${rand(20, 80).toFixed(0)}px) scale(${rand(1.2, 1.8).toFixed(2)})` },
            ],
            { duration: 900, delay: 3600 + rand(0, 700), easing: 'ease-in', fill: 'forwards' },
        );
    });
}

function whisperLoop() {
    const t = S.trace;
    const place = [t.city, t.country].filter(Boolean).join(', ');
    const lines = [
        'I CAN SEE YOU',
        place ? `${upper(t.city || t.country)} IS NOT FAR` : 'YOU ARE NOT FAR',
        "DON'T LOOK BEHIND YOU",
        'YOU SHOULD NOT BE HERE',
        t.browser ? `${upper(t.browser)} WON'T HELP YOU` : 'NOTHING WILL HELP YOU',
        "IT'S ALREADY INSIDE",
        'WE KEPT YOUR ADDRESS',
        'THERE IS NO WAY OUT',
        `${upper(S.name)}...`,
        'STAY.',
    ];
    let index = 0;
    whisper(`WELCOME, ${upper(S.name)}`);
    if (S.reduced) return;
    const next = () => {
        whisper(lines[index % lines.length]);
        index += 1;
        later(next, rand(8000, 11000));
    };
    later(next, 8500);
}

const MARKS = ['̴', '̵', '̶', '̷', '̸', '̀', '́', '̕', '̛', '͒', '͓', '͔', '҉', '̾', '͆'];

function corrupt(text) {
    return [...text]
        .map((character) => {
            if (character === ' ') return character;
            let out = Math.random() < 0.08 ? pick(['#', '%', '@', '§', 'Ø', '¥']) : character;
            const count = Math.floor(rand(1, 4));
            for (let i = 0; i < count; i++) out += pick(MARKS);
            return out;
        })
        .join('');
}

/** Labels and headings that rot for a moment, then come back. */
function rotLoop() {
    const scope = document.querySelector('main');
    if (!scope || S.reduced) return;
    const nodes = [];
    const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const node = walker.currentNode;
        const parent = node.parentElement;
        if (!node.nodeValue.trim() || !parent || parent.closest('#lockdown-banner, button, [data-login-timer]')) continue;
        if (parent.matches('label, h1, p, a')) nodes.push(node);
    }
    if (!nodes.length) return;

    const go = () => {
        const node = pick(nodes);
        const original = node.nodeValue;
        node.nodeValue = corrupt(original);
        later(() => {
            node.nodeValue = original;
        }, rand(160, 420));
        later(go, rand(900, 2600));
    };
    later(go, 1500);
}

function setBanner(lines) {
    const banner = document.getElementById('lockdown-banner');
    if (!banner) return;
    banner.replaceChildren();
    lines.forEach((line, i) => {
        if (i) banner.append(document.createElement('br'));
        banner.append(document.createTextNode(line));
    });
}

function bannerLoop() {
    const banner = document.getElementById('lockdown-banner');
    if (!banner || S.reduced) return;
    const short = S.name.length > 18 ? `${S.name.slice(0, 17)}...` : S.name;
    const sets = [
        ['TOO MANY ATTEMPTS.', 'WE KNOW.', 'SIGN-IN HALTED.'],
        ['WE SEE YOU,', `${upper(short)}.`],
        ['DO NOT', 'TRY', 'AGAIN.'],
        ["IT'S", 'RIGHT', 'BEHIND YOU.'],
        ['YOUR ADDRESS', S.trace.ip || 'IS', 'HAS BEEN KEPT.'],
        ['TOO MANY ATTEMPTS.', 'WE KNOW.', 'SIGN-IN HALTED.'],
    ];
    let index = 1;
    const go = () => {
        banner.animate(
            [
                { clipPath: 'inset(0 0 0 0)', transform: 'translateX(0)' },
                { clipPath: 'inset(0 0 60% 0)', transform: 'translateX(-12px)', offset: 0.3 },
                { clipPath: 'inset(45% 0 0 0)', transform: 'translateX(10px)', offset: 0.6 },
                { clipPath: 'inset(0 0 0 0)', transform: 'translateX(0)' },
            ],
            { duration: 300, easing: 'steps(4, end)' },
        );
        later(() => setBanner(sets[index % sets.length]), 150);
        index += 1;
        later(go, rand(3800, 5200));
    };
    later(go, 4500);
}

function titleLoop() {
    const titles = [`Welcome, ${S.name}`, 'I SEE YOU', S.trace.ip || 'I SEE YOU', "DON'T LEAVE", 'SYSTEM LOCKED', 'it is behind you'];
    let index = 0;
    setInterval(() => {
        document.title = document.hidden ? 'COME BACK.' : titles[index++ % titles.length];
    }, 1400);

    const corner = S.root.querySelector('[data-corner]');
    if (corner && !S.reduced) {
        const words = ['no access', 'no exit', 'no way out', 'stay'];
        let i = 0;
        setInterval(() => {
            corner.textContent = words[++i % words.length];
        }, 5000);
    }
}

/* -------------------------------------------------------- reacting */

function reactions() {
    let lastKey = 0;
    let lastExit = 0;
    let lastClick = 0;
    let hiddenAt = 0;

    document.addEventListener('pointermove', (event) => {
        S.pointer.x = event.clientX;
        S.pointer.y = event.clientY;
        S.pointer.moved = true;
    }, { passive: true });

    document.addEventListener('pointerdown', (event) => {
        const now = performance.now();
        if (now - lastClick < 200 || S.reduced) return;
        lastClick = now;
        splat(event.clientX, event.clientY);
        thudSound(0.5);
        shake(0.3);
    });

    document.addEventListener('keydown', () => {
        const now = performance.now();
        if (now - lastKey < 700 || S.reduced) return;
        lastKey = now;
        crackleSound();
        shake(0.5);
        setBanner(['STOP', 'TYPING.']);
    });

    // Heading for the tab bar or the close button.
    document.addEventListener('mouseout', (event) => {
        if (event.relatedTarget || event.clientY > 10 || S.reduced) return;
        const now = performance.now();
        if (now - lastExit < 6000) return;
        lastExit = now;
        whisper('WHERE ARE YOU GOING?');
        slamHand(rand(40, window.innerWidth * 0.4), 10, rand(160, 200));
        later(() => slamHand(rand(window.innerWidth * 0.55, window.innerWidth - 160), 10, rand(160, 200)), 260);
        stingSound();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            hiddenAt = performance.now();
            return;
        }
        if (S.reduced || performance.now() - hiddenAt < 3000) return;
        whisper('YOU CAME BACK.');
        later(scare, 1200);
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && !S.reduced) whisper("YOU CAN'T HIDE");
    });
}

/* ---------------------------------------------------------- the page */

function countdown(seconds) {
    const button = document.getElementById('login-submit');
    if (!button) return;
    const label = button.querySelector('[data-login-label]');
    const timer = button.querySelector('[data-login-timer]');
    const end = Date.now() + seconds * 1000;
    label.textContent = 'SYSTEM LOCKED';

    const tick = () => {
        const left = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        timer.textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;

        if (left === 0) {
            clearInterval(interval);
            // The lock has run out, so a fresh page is the normal sign-in form again.
            window.location.reload();
        }
    };

    const interval = setInterval(tick, 1000);
    tick();
}

function lockForm() {
    const form = document.querySelector('#login-card form');
    form?.querySelectorAll('input, button').forEach((field) => {
        field.disabled = true;
    });
    const back = document.getElementById('login-back');
    if (back) {
        back.setAttribute('aria-disabled', 'true');
        back.tabIndex = -1;
    }
    const banner = document.getElementById('lockdown-banner');
    if (banner) banner.hidden = false;
}

/** "behind the counter / Superadmin" becomes a greeting, by the name they typed. */
function greet() {
    const t = S.trace;
    const tagline = document.querySelector('[data-login-tagline]');
    const title = document.querySelector('[data-login-title]');
    const whoami = document.querySelector('[data-login-whoami]');
    if (tagline) tagline.textContent = 'welcome,';
    if (title) title.textContent = S.name;
    if (whoami) {
        whoami.textContent = [
            [t.browser, t.os].filter(Boolean).join(' on '),
            [t.city, t.country].filter(Boolean).join(', '),
        ].filter(Boolean).join('  ·  ');
        whoami.hidden = false;
    }
}

/** Same rule as Lockdown::greeting() on the server. */
function nameFor(typed, trace) {
    const clean = String(typed || '').replace(/\s+/g, ' ').trim();
    if (clean) return clean.length > 22 ? `${clean.slice(0, 22)}...` : clean;
    const os = String(trace.os || '').replace(/\s*[\d./]+$/, '').trim();
    return os && !os.startsWith('Unknown') ? `${os} user` : 'stranger';
}

/** Red system log lines that type themselves down both sides of the screen. */
function typeLogs() {
    const t = S.trace;
    const still = S.reduced;
    const stamp = () => new Date().toTimeString().slice(0, 8);
    const place = [t.city, t.country].filter(Boolean).join(', ') || 'resolving...';
    const left = [
        `[${stamp()}] auth.guard = LOCKED`,
        'kernel = ERR_LOGIN_OVERFLOW',
        'kernel = SOCKET_FORCE_CLOSE',
        '',
        `user     = "${S.name}"`,
        `src_ip   = ${t.ip || 'unknown'}`,
        `geo      = ${place}`,
        `isp      = ${t.isp || 'unknown'}`,
        'kernel = MALICIOUS_ACTIVITY_DETECTED',
        '',
        'log = ERR_LOGIN_OVERFLOW',
        'log = SYS_PANIC',
        'log = security.lockdown -> written',
    ];
    const right = [
        '[ session.trace(active) ]',
        'fingerprint.capture( {',
        `    ip: "${t.ip || 'unknown'}",`,
        `    browser: "${t.browser || 'unknown'}",`,
        `    os: "${t.os || 'unknown'}",`,
        `    device: "${t.device || 'unknown'}",`,
        `    at: "${new Date().toISOString().slice(0, 19)}"`,
        '} );',
        '',
        'sys-overload ( !! );',
        'access = DENIED',
        'observed_for = 00:00',
    ];

    const write = (element, lines, delay, done) => {
        if (!element) return;
        if (still) {
            element.textContent = lines.join('\n');
            done?.(element, lines);
            return;
        }
        let line = 0;
        let char = 0;
        const step = () => {
            if (line >= lines.length) {
                done?.(element, lines);
                return;
            }
            const text = lines[line];
            char += 2;
            const before = lines.slice(0, line).join('\n');
            element.textContent = (before ? `${before}\n` : '') + text.slice(0, char);
            if (char >= text.length) {
                line += 1;
                char = 0;
                later(step, 140);
            } else {
                later(step, 22);
            }
        };
        later(step, delay);
    };

    write(S.root.querySelector('.lockdown-log--left'), left, 200);
    write(S.root.querySelector('.lockdown-log--right'), right, 900, (element, lines) => {
        // The last line keeps counting how long they have been watched.
        const head = lines.slice(0, -1).join('\n');
        setInterval(() => {
            const seconds = Math.floor((performance.now() - S.born) / 1000);
            element.textContent = `${head}\nobserved_for = ${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        }, 1000);
    });

    const traceLine = S.root.querySelector('[data-trace-line]');
    if (traceLine) {
        traceLine.textContent = [t.ip, [t.city, t.country].filter(Boolean).join(', '), t.isp, [t.browser, t.os].filter(Boolean).join(' / ')]
            .filter(Boolean)
            .join('  ·  ');
    }
}

/**
 * Torn strips of pixel noise that hug the edges of the sign-in card and flicker around the
 * page, redrawn about eight times a second (well under any flashing threshold).
 */
function noise() {
    const canvas = S.root.querySelector('.lockdown-noise');
    const card = S.card;
    if (!canvas || !card) return;
    const context = canvas.getContext('2d');

    const resize = () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    };
    resize();
    window.addEventListener('resize', resize);

    const strip = (x, y, width, height) => {
        for (let i = 0; i < width; i += 2) {
            const shade = Math.random();
            if (shade < 0.35) continue;
            const value = Math.floor(shade * 200);
            context.fillStyle = Math.random() < 0.1 ? 'rgba(220, 30, 40, 0.9)' : `rgba(${value}, ${value}, ${value}, 0.8)`;
            context.fillRect(x + i, y, 2, height);
        }
    };

    const draw = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        const box = card.getBoundingClientRect();

        for (let i = 0; i < 16; i++) {
            const y = box.top + Math.random() * box.height;
            const width = 20 + Math.random() * 110;
            const fromLeft = Math.random() < 0.5;
            const x = fromLeft ? box.left - width * Math.random() : box.right - width * (1 - Math.random());
            strip(x, y, width, 1 + Math.floor(Math.random() * 3));
        }

        for (let i = 0; i < 5; i++) {
            if (Math.random() < 0.5) continue;
            const width = 30 + Math.random() * 160;
            const height = 4 + Math.random() * 26;
            const x = Math.random() * (canvas.width - width);
            const y = Math.random() * (canvas.height - height);
            for (let row = 0; row < height; row += 2) strip(x + (Math.random() * 8 - 4), y + row, width, 2);
        }
    };

    if (S.reduced) {
        draw();
        return;
    }
    setInterval(draw, 125);
}

function goFullscreen() {
    // Only works while the click that caused this still counts; otherwise nothing happens.
    try {
        document.documentElement.requestFullscreen?.({ navigationUI: 'hide' })?.catch?.(() => {});
    } catch {
        // Not allowed here.
    }
}

/**
 * @param {{ seconds?: number, instant?: boolean, context?: AudioContext | null, ip?: string, trace?: object | null, username?: string }} options
 *   instant skips the glitch (a page that opens already locked); context is an AudioContext
 *   created inside the click that sent the sign-in, which is what lets browsers play the sound.
 */
export function start({ seconds = 60, instant = false, context = null, ip = '', trace = null, username = '' } = {}) {
    const root = document.getElementById('lockdown');
    if (!root || started) {
        closeContext(context);
        return;
    }
    started = true;

    S.root = root;
    S.card = document.getElementById('login-card');
    S.main = document.querySelector('main');
    S.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    S.small = window.innerWidth < 900;
    S.trace = { ...(trace || {}), ip: (trace && trace.ip) || ip || root.dataset.ip };
    S.name = nameFor(username || document.getElementById('username')?.value, S.trace);

    setupAudio(context);
    if (!live()) unlockSoundOnFirstTouch();

    const reveal = () => {
        S.born = performance.now();
        document.body.classList.remove('is-glitching');
        document.body.classList.add('is-locked');
        root.hidden = false;
        lockForm();
        greet();
        typeLogs();
        noise();
        playMusic();
        countdown(seconds);
        eyes();
        stalker();
        whisperLoop();
        titleLoop();
        frameLoop();
        if (!S.reduced) {
            heartbeat();
            handsLoop();
            rotLoop();
            bannerLoop();
            reactions();
        }
        document.getElementById('lockdown-banner')?.focus?.();
    };

    if (instant || S.reduced) {
        reveal();
        return;
    }

    goFullscreen();
    document.body.classList.add('is-glitching');
    if (live()) glitchSound();
    later(reveal, 1000);
}
