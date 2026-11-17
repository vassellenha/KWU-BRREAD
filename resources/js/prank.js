// What a person meets when they guess passwords on /staff or /superadmin by hand.
// Wrong password 1, 2 and 3 each bring a different Batman scene with the "fahh" sound; the
// fourth sucks the form into a vortex and, Rickroll style, the whole screen turns into the
// "Kewer Kewer" video while sign-in is paused for that address (1, 3, then 5 minutes; the
// server keeps the real lock). Loaded on demand from app.js.

const PHOTO = '/img/prank-batman.webp';
const VIDEO = '/video/prank-kewer.mp4';
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

/** Fetches the photo and both sounds once, so the first wrong password reacts at once. */
export function preload() {
    if (preloading) return preloading;

    const image = new Image();
    image.src = PHOTO;

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

function photo(className = '') {
    const frame = el('div', `prank-photo ${className}`);
    const image = el('img');
    image.src = PHOTO;
    image.alt = 'Batman';
    image.decoding = 'async';
    frame.append(image);

    return frame;
}

function bubble(text, className = '') {
    const node = el('p', `prank-bubble ${className}`);
    node.setAttribute('role', 'status');
    typeOut(node, text);

    return node;
}

function typeOut(node, text, speed = 38) {
    if (S.reduced) {
        node.textContent = text;
        return;
    }

    node.textContent = '';
    [...text].forEach((letter, index) => later(() => {
        node.textContent += letter;
    }, 260 + index * speed));
}

function card() {
    return document.getElementById('login-card');
}

function shake(target, strength = 10, duration = 420) {
    if (S.reduced || !target) return;

    const steps = [0, -1, 0.8, -0.6, 0.4, -0.2, 0].map((f) => ({ transform: `translateX(${f * strength}px)` }));
    target.animate(steps, { duration, easing: 'ease-out' });
}

function screenShake(strength = 12, duration = 520) {
    if (S.reduced) return;

    const frames = Array.from({ length: 9 }, (_, i) => {
        const f = (1 - i / 8) * strength;
        return { transform: `translate(${(Math.random() - 0.5) * 2 * f}px, ${(Math.random() - 0.5) * 2 * f}px) rotate(${(Math.random() - 0.5) * f * 0.12}deg)` };
    });
    document.querySelector('main')?.animate(frames, { duration, easing: 'linear' });
}

/* ---------- the grappling hook ---------- */

// Where the rope meets the photo: his raised fist, as a share of the cut-out's width and height.
const HAND = { x: 0.62, y: 0.37 };
const PHOTO_RATIO = 760 / 676;
// The hook's own height below the ceiling, where the rope starts.
const HOOK_DROP = 50;

function hookSvg() {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 44 40');
    svg.setAttribute('class', 'prank-hook');
    svg.setAttribute('aria-hidden', 'true');
    const claws = 'M22 36 V8 M22 13 Q8 13 5 1 M22 13 Q36 13 39 1 M22 9 V0';
    [['#1b1a20', 7], ['#c9ccd3', 4]].forEach(([stroke, width]) => {
        const path = document.createElementNS(ns, 'path');
        path.setAttribute('d', claws);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', stroke);
        path.setAttribute('stroke-width', width);
        path.setAttribute('stroke-linecap', 'round');
        svg.append(path);
    });
    const grip = document.createElementNS(ns, 'rect');
    Object.entries({ x: 17, y: 22, width: 10, height: 14, rx: 3, fill: '#40000e', stroke: '#1b1a20', 'stroke-width': 2 })
        .forEach(([key, value]) => grip.setAttribute(key, value));
    svg.append(grip);

    return svg;
}

/** A hook stuck in the top of the screen at x, a rope, and him holding the rope `length` px down. */
function rig(x, length, width) {
    const node = el('div', 'prank-rig');
    node.style.left = `${x}px`;
    const height = width * PHOTO_RATIO;
    const rope = el('span', 'prank-rope');
    rope.style.height = `${length - HOOK_DROP}px`;
    const pic = photo('is-rigged');
    pic.style.width = `${width}px`;
    pic.style.left = `${-HAND.x * width}px`;
    pic.style.top = `${length - HAND.y * height}px`;
    const hook = hookSvg();
    node.append(rope, pic, hook);

    return { node, rope, pic, hook, length, height, width };
}

/** The hook flies down, bites into the top of the screen with a clank and a few sparks. */
async function fireHook(r) {
    r.rope.style.transform = 'scaleY(0)';
    r.pic.style.opacity = '0';

    if (S.reduced) {
        clank();
        return;
    }

    await r.hook.animate([
        { transform: 'translate(-50%, -140%) rotate(-18deg)' },
        { transform: 'translate(-50%, 6%) rotate(4deg)', offset: 0.8 },
        { transform: 'translate(-50%, 0) rotate(0deg)' },
    ], { duration: 260, easing: 'cubic-bezier(.5,0,.9,.6)', fill: 'forwards' }).finished.catch(() => {});

    clank();
    screenShake(5, 220);
    for (let i = 0; i < 8; i++) {
        const spark = el('span', 'prank-spark');
        r.node.append(spark);
        const angle = (Math.PI / 8) + (Math.random() * Math.PI * 0.75);
        const reach = 30 + Math.random() * 45;
        spark.animate([
            { transform: 'translate(0, 0) scale(1)', opacity: 1 },
            { transform: `translate(${Math.cos(angle) * reach * (Math.random() < 0.5 ? -1 : 1)}px, ${Math.sin(angle) * reach}px) scale(.2)`, opacity: 0 },
        ], { duration: 320 + Math.random() * 200, easing: 'cubic-bezier(.2,.8,.4,1)' }).finished.then(() => spark.remove(), () => spark.remove());
    }
    await wait(140);
}

/** He zips down the rope from above the screen and bounces on its stretch. */
async function rappel(r, duration = 620) {
    zipSound(duration);
    r.pic.style.opacity = '1';
    const from = -(r.length + r.height + 40);
    const steps = [[from, 0], [22, 0.62], [-9, 0.78], [4, 0.9], [0, 1]];

    if (S.reduced) {
        r.rope.style.transform = 'none';
        return;
    }

    const options = { duration, easing: 'cubic-bezier(.3,.6,.4,1)', fill: 'forwards' };
    r.rope.animate(steps.map(([y, offset]) => ({
        transform: `scaleY(${Math.max(0, (r.length - HOOK_DROP + y) / (r.length - HOOK_DROP))})`, offset,
    })), options);
    await r.pic.animate(steps.map(([y, offset]) => ({ transform: `translateY(${y}px)`, offset })), options).finished.catch(() => {});
}

/** He is reeled back up and the hook pulls out of the ceiling. */
async function reelUp(r) {
    if (S.reduced) {
        await r.node.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 250, fill: 'forwards' }).finished.catch(() => {});
        return;
    }

    zipSound(380, true);
    const up = -(r.length + r.height + 60);
    const options = { duration: 420, easing: 'cubic-bezier(.6,0,.9,.5)', fill: 'forwards' };
    r.rope.animate([{ transform: 'scaleY(1)' }, { transform: 'scaleY(0)' }], options);
    await r.pic.animate([
        { transform: 'translateY(0)' },
        { transform: 'translateY(14px)', offset: 0.15 },
        { transform: `translateY(${up}px)` },
    ], options).finished.catch(() => {});
    await r.hook.animate([
        { transform: 'translate(-50%, 0)' },
        { transform: 'translate(-50%, -160%)' },
    ], { duration: 200, easing: 'ease-in', fill: 'forwards' }).finished.catch(() => {});
}

function sway(r, degrees = 2.5) {
    if (S.reduced) return;
    r.node.animate([
        { transform: 'rotate(0deg)' },
        { transform: `rotate(${degrees}deg)` },
        { transform: `rotate(${-degrees}deg)` },
        { transform: 'rotate(0deg)' },
    ], { duration: 1800, iterations: Infinity, easing: 'ease-in-out' });
}

/* Metal biting into metal: a noise tick and two ringing partials. */
function clank() {
    const context = S.context;
    if (!context || context.state !== 'running') return;
    const now = context.currentTime;

    const noise = context.createBuffer(1, context.sampleRate * 0.06, context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / data.length);
    const tick = context.createBufferSource();
    tick.buffer = noise;
    const high = context.createBiquadFilter();
    high.type = 'highpass';
    high.frequency.value = 2500;
    const tickGain = context.createGain();
    tickGain.gain.value = 0.5;
    tick.connect(high).connect(tickGain).connect(context.destination);
    tick.start(now);

    [1850, 2790, 4100].forEach((frequency, index) => {
        const tone = context.createOscillator();
        const gain = context.createGain();
        tone.type = 'sine';
        tone.frequency.value = frequency;
        gain.gain.setValueAtTime(0.16 / (index + 1), now);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
        tone.connect(gain).connect(context.destination);
        tone.start(now);
        tone.stop(now + 0.36);
    });
}

/* The rope running through the grapple: a band of noise sweeping down (or up when reeling in). */
function zipSound(duration, up = false) {
    const context = S.context;
    if (!context || context.state !== 'running') return;
    const now = context.currentTime;
    const seconds = duration / 1000;

    const noise = context.createBuffer(1, Math.ceil(context.sampleRate * seconds), context.sampleRate);
    const data = noise.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
    const source = context.createBufferSource();
    source.buffer = noise;
    const band = context.createBiquadFilter();
    band.type = 'bandpass';
    band.Q.value = 3;
    band.frequency.setValueAtTime(up ? 900 : 3200, now);
    band.frequency.exponentialRampToValueAtTime(up ? 3200 : 900, now + seconds);
    const gain = context.createGain();
    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.35, now + 0.05);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + seconds);
    source.connect(band).connect(gain).connect(context.destination);
    source.start(now);
}

/* ---------- the four scenes ---------- */

// 1: a grappling hook bites into the top of the screen and he rappels down beside the form.
async function sceneOne() {
    const root = stage();
    const box = card()?.getBoundingClientRect();
    const width = 170;
    const x = box ? Math.min(window.innerWidth - width * 0.5, box.right + 70) : window.innerWidth * 0.75;
    const top = box ? box.top + 10 : window.innerHeight * 0.3;
    const r = rig(x, top + HAND.y * width * PHOTO_RATIO, width);
    const say = bubble('hayo iseng ya tangan nya', 'is-free');
    say.style.left = `${x}px`;
    say.style.top = `${top + r.height - 10}px`;
    root.append(r.node, say);

    await fireHook(r);
    await rappel(r);
    fahh();
    shake(card());
    sway(r);
    say.classList.add('is-in');

    await wait(2800);
    say.classList.remove('is-in');
    await reelUp(r);
}

// 2: the hook bites above the button and he swings in on the rope like a pendulum, then points.
async function sceneTwo() {
    const root = stage();
    const button = document.getElementById('login-submit');
    const box = button?.getBoundingClientRect();
    const width = 150;
    const height = width * PHOTO_RATIO;
    const x = box ? Math.max(width * 0.6, box.left - 40) : window.innerWidth * 0.25;
    const top = box ? box.top + box.height / 2 - height * 0.55 : window.innerHeight * 0.6;
    const r = rig(x, top + HAND.y * height, width);
    const arrow = el('span', 'prank-arrow');
    const say = bubble('ayo coba pencet lagi', 'is-free');
    say.style.left = `${x}px`;
    say.style.top = `${top - 56}px`;
    if (box) {
        arrow.style.left = `${box.left + box.width / 2}px`;
        arrow.style.top = `${box.top - 46}px`;
    }
    root.append(r.node, arrow, say);

    await fireHook(r);
    r.rope.style.transform = 'none';
    r.pic.style.opacity = '1';
    zipSound(900);

    if (!S.reduced) {
        await r.node.animate([
            { transform: 'rotate(-80deg)' },
            { transform: 'rotate(20deg)', offset: 0.45 },
            { transform: 'rotate(-10deg)', offset: 0.68 },
            { transform: 'rotate(5deg)', offset: 0.84 },
            { transform: 'rotate(0deg)' },
        ], { duration: 1300, easing: 'cubic-bezier(.35,.1,.3,1)', fill: 'forwards' }).finished.catch(() => {});
    }

    fahh();
    shake(card(), 14);
    screenShake(6, 320);
    say.classList.add('is-in');
    arrow.classList.add('is-in');

    if (!S.reduced && button) {
        button.animate([
            { transform: 'scale(1) rotate(0)' },
            { transform: 'scale(1.06) rotate(-1.5deg)' },
            { transform: 'scale(1.06) rotate(1.5deg)' },
            { transform: 'scale(1) rotate(0)' },
        ], { duration: 520, iterations: 5 });
    }

    await wait(3000);
    say.classList.remove('is-in');
    arrow.classList.remove('is-in');
    await reelUp(r);
}

// 3: the screen goes dark, the hook bites dead centre and he drops hard, then calls out where they are.
async function sceneThree(scene) {
    // The video is next; start fetching it now.
    if (!S.warmVideo) {
        S.warmVideo = document.createElement('video');
        S.warmVideo.preload = 'auto';
        S.warmVideo.muted = true;
        S.warmVideo.src = VIDEO;
    }

    const root = stage();
    root.classList.add('is-dim');

    const width = Math.min(window.innerHeight * 0.4, window.innerWidth * 0.5, 300);
    const height = width * PHOTO_RATIO;
    const top = Math.max(60, window.innerHeight * 0.42 - height * 0.62);
    const r = rig(window.innerWidth / 2, top + HAND.y * height, width);

    const place = (scene.city || scene.country || 'mana nih').toLowerCase();
    const wrap = el('div', 'prank-callout');
    wrap.style.top = `${top + height * 0.84}px`;
    const rings = el('div', 'prank-rings');
    rings.style.top = `${top + height * 0.45}px`;
    for (let i = 0; i < 3; i++) rings.append(el('span'));
    const words = el('p', 'prank-words');
    ['woalah', 'orang', place].forEach((word, index) => {
        words.append(el('span', index === 2 ? 'is-place' : '', word));
    });
    const tag = el('p', 'prank-ip');
    const code = el('span', 'prank-ip-code', '');
    tag.append(el('span', 'prank-pin'), code);
    wrap.append(words, tag);
    root.append(rings, r.node, wrap);

    await wait(250);
    await fireHook(r);
    await rappel(r, 520);
    fahh();
    screenShake(16, 560);

    if (S.reduced) {
        words.querySelectorAll('span').forEach((span) => span.classList.add('is-in'));
        code.textContent = scene.ip || '';
        tag.classList.add('is-in');
    } else {
        sway(r, 1.5);
        rings.classList.add('is-on');

        const spans = [...words.querySelectorAll('span')];
        for (const [index, span] of spans.entries()) {
            await wait(index === 2 ? 380 : 260);
            span.classList.add('is-in');
            if (index === 2) screenShake(10, 360);
        }

        await wait(300);
        tag.classList.add('is-in');
        await decode(code, scene.ip || '???');
    }

    await wait(2600);
    wrap.classList.add('is-out');
    rings.remove();
    await reelUp(r);
    root.classList.remove('is-dim');
    await wait(S.reduced ? 1 : 300);
}

// Characters roll through random digits and settle one by one, like a hacker film.
function decode(node, text) {
    return new Promise((resolve) => {
        const pool = '0123456789abcdef.:';
        let frame = 0;
        const total = text.length * 3 + 8;
        const tick = () => {
            frame++;
            node.textContent = [...text].map((letter, index) => (
                frame > index * 3 + 8 || letter === '.' || letter === ':' ? letter : pool[Math.floor(Math.random() * pool.length)]
            )).join('');

            if (frame < total) {
                window.requestAnimationFrame(tick);
            } else {
                node.textContent = text;
                resolve();
            }
        };
        tick();
    });
}

// 4: the form gets sucked into a spinning vortex, an iris closes on the face and opens on the party.
async function sceneFour(scene) {
    const root = stage();
    root.classList.add('is-dim');
    const main = document.querySelector('main');

    fahh();

    if (!S.reduced) {
        const vortex = el('div', 'prank-vortex');
        vortex.append(el('span'));
        const pic = photo('is-center is-small');
        pic.style.transform = 'scale(0)';
        root.append(vortex, pic);

        screenShake(18, 700);
        vortex.animate([
            { transform: 'translate(-50%, -50%) scale(0)' },
            { transform: 'translate(-50%, -50%) scale(1)' },
        ], { duration: 1100, easing: 'cubic-bezier(.3,.1,.3,1)', fill: 'forwards' });

        await main?.animate([
            { transform: 'scale(1) rotate(0deg)', filter: 'blur(0)', opacity: 1 },
            { transform: 'scale(.92) rotate(-12deg)', offset: 0.2 },
            { transform: 'scale(0) rotate(900deg)', filter: 'blur(6px)', opacity: 0 },
        ], { duration: 1300, easing: 'cubic-bezier(.6,0,.4,1)', fill: 'forwards' }).finished.catch(() => {});

        await pic.animate([
            { transform: 'scale(0) rotate(-720deg)' },
            { transform: 'scale(1.6) rotate(10deg)', offset: 0.75 },
            { transform: 'scale(1.4) rotate(0deg)' },
        ], { duration: 900, easing: 'cubic-bezier(.2,.9,.3,1)', fill: 'forwards' }).finished.catch(() => {});

        const iris = el('div', 'prank-iris');
        root.append(iris);
        await iris.animate([
            { transform: 'translate(-50%, -50%) scale(1)' },
            { transform: 'translate(-50%, -50%) scale(0)' },
        ], { duration: 650, easing: 'cubic-bezier(.7,0,.9,.5)', fill: 'forwards' }).finished.catch(() => {});
        vortex.remove();
        pic.remove();
    } else {
        main?.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 300, fill: 'forwards' });
        await wait(300);
    }

    await party(scene.retry_after || 60, scene.ip, true);
}

/* ---------- the Kewer Kewer video ---------- */

async function party(seconds, ip, opening = false) {
    const root = stage();
    root.classList.add('is-dim');
    lockForm(seconds);

    const tv = el('div', 'prank-tv');
    const video = el('video');
    video.src = VIDEO;
    video.loop = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.setAttribute('playsinline', '');

    const bar = el('div', 'prank-tv-bar');
    const label = el('span', '', 'login dikunci');
    const clock = el('b');
    const who = el('span', 'prank-tv-ip', ip ? `IP ${ip} kecatet` : '');
    bar.append(label, clock, who);

    const close = el('button', 'prank-close', 'tutup');
    close.type = 'button';
    const unmute = el('button', 'prank-hint', 'klik buat nyalain suaranya');
    unmute.type = 'button';
    unmute.hidden = true;

    tv.append(video, bar, close, unmute);
    clock.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    const iris = root.querySelector('.prank-iris');
    if (iris) root.insertBefore(tv, iris); else root.append(tv);

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

    if (opening && iris && !S.reduced) {
        start();
        await wait(140);
        await iris.animate([
            { transform: 'translate(-50%, -50%) scale(0)' },
            { transform: 'translate(-50%, -50%) scale(1)' },
        ], { duration: 900, easing: 'cubic-bezier(.2,.8,.3,1)', fill: 'forwards' }).finished.catch(() => {});
        iris.remove();
    } else {
        iris?.remove();
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

    // Closing early only stops the video; the form stays locked until the time is up.
    close.addEventListener('click', (event) => {
        event.stopPropagation();
        finish();
    });

    const end = () => {
        window.clearInterval(timer);
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
    label.textContent = 'Dikunci';

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

/* ---------- entry points ---------- */

/**
 * Plays the scene the server picked for a wrong password: { stage: 1-4 | 'paused', ip, city, country, retry_after }.
 */
export function play(scene, { context = null } = {}) {
    adopt(context);
    preload();

    if (S.busy && scene.stage !== 4 && scene.stage !== 'paused') return S.busy;

    clearStage();

    const run = {
        1: sceneOne,
        2: sceneTwo,
        3: sceneThree,
        4: sceneFour,
        paused: (s) => party(s.retry_after || 60, s.ip, false),
    }[scene.stage];

    if (!run) return Promise.resolve();

    S.busy = Promise.resolve(run(scene)).catch(() => {}).finally(() => {
        S.busy = null;
        if (scene.stage !== 4 && scene.stage !== 'paused') clearStage();
    });

    return S.busy;
}

/** A page opened (or reloaded) while its address is still paused. */
export function resumePause(seconds, ip) {
    preload();
    party(seconds, ip, false);
}
