// The "system locked" screen: the sign-in form glitches with a synthesized static burst, cuts
// to black on a low hit, and the locked screen takes over with a countdown. Everything loads
// only when a lockdown is triggered, so normal sign-ins never download it.

const MUTE_KEY = 'brread-lockdown-muted';
const MUSIC_VOLUME = 0.6;

let started = false;
let muted = readMuted();
let music = null;

function readMuted() {
    try {
        return localStorage.getItem(MUTE_KEY) === '1';
    } catch {
        return false;
    }
}

function writeMuted(value) {
    try {
        localStorage.setItem(MUTE_KEY, value ? '1' : '0');
    } catch {
        // Private mode can refuse storage; the choice then lasts until the page closes.
    }
}

function closeContext(context) {
    try {
        context?.close?.().catch(() => {});
    } catch {
        // Already closed.
    }
}

/**
 * About a second of digital static, a falling zap and a low thump, all built from noise and
 * oscillators so there is no audio file to download or license. Level is capped by a compressor.
 */
function glitchSound(context) {
    const start = context.currentTime;
    const out = context.createGain();
    out.gain.value = 0.5;
    const compressor = context.createDynamicsCompressor();
    out.connect(compressor);
    compressor.connect(context.destination);

    // Static: holding each random sample for a few frames gives a bit-crushed crackle.
    const length = Math.floor(context.sampleRate * 1.1);
    const buffer = context.createBuffer(1, length, context.sampleRate);
    const data = buffer.getChannelData(0);
    let held = 0;
    for (let i = 0; i < length; i++) {
        if (i % 18 === 0) held = Math.random() * 2 - 1;
        data[i] = held;
    }
    const noise = context.createBufferSource();
    noise.buffer = buffer;
    const band = context.createBiquadFilter();
    band.type = 'bandpass';
    band.Q.value = 0.9;
    band.frequency.setValueAtTime(500, start);
    band.frequency.exponentialRampToValueAtTime(5000, start + 0.9);
    const stutter = context.createGain();
    stutter.gain.value = 0;
    for (let t = 0; t < 0.95; t += 0.045) {
        stutter.gain.setValueAtTime(Math.random() > 0.4 ? 0.55 : 0, start + t);
    }
    noise.connect(band);
    band.connect(stutter);
    stutter.connect(out);
    noise.start(start);
    noise.stop(start + 1);

    // Zap: a sawtooth that falls from a whine to a growl.
    const zap = context.createOscillator();
    zap.type = 'sawtooth';
    zap.frequency.setValueAtTime(1400, start);
    zap.frequency.exponentialRampToValueAtTime(70, start + 0.7);
    const zapGain = context.createGain();
    zapGain.gain.setValueAtTime(0.18, start);
    zapGain.gain.exponentialRampToValueAtTime(0.001, start + 0.75);
    zap.connect(zapGain);
    zapGain.connect(out);
    zap.start(start);
    zap.stop(start + 0.8);

    // Hit: a deep thump just before the locked screen appears.
    const hit = start + 0.95;
    const thump = context.createOscillator();
    thump.type = 'sine';
    thump.frequency.setValueAtTime(70, hit);
    thump.frequency.exponentialRampToValueAtTime(32, hit + 0.6);
    const thumpGain = context.createGain();
    thumpGain.gain.setValueAtTime(0.9, hit);
    thumpGain.gain.exponentialRampToValueAtTime(0.001, hit + 0.8);
    thump.connect(thumpGain);
    thumpGain.connect(out);
    thump.start(hit);
    thump.stop(hit + 0.85);

    setTimeout(() => closeContext(context), 2500);
}

function fade(audio, target, ms) {
    const from = audio.volume;
    const began = performance.now();
    const step = (now) => {
        const progress = Math.min(1, (now - began) / ms);
        audio.volume = Math.max(0, Math.min(1, from + (target - from) * progress));
        if (progress < 1) {
            requestAnimationFrame(step);
        } else if (target === 0) {
            audio.pause();
        }
    };
    requestAnimationFrame(step);
}

function playMusic() {
    if (!music || muted) return;
    music.volume = 0;
    music.play().then(() => fade(music, MUSIC_VOLUME, 2000)).catch(() => {
        // The browser refused sound without a click; the sound button turns it on.
    });
}

function wireSoundButton(root) {
    const button = root.querySelector('#lockdown-sound');
    if (!button) return;

    const paint = () => {
        button.setAttribute('aria-pressed', muted ? 'true' : 'false');
        button.setAttribute('aria-label', muted ? 'Unmute sound' : 'Mute sound');
        button.querySelector('[data-icon-on]').hidden = muted;
        button.querySelector('[data-icon-off]').hidden = !muted;
    };

    button.addEventListener('click', () => {
        muted = !muted;
        writeMuted(muted);
        paint();
        if (muted) {
            if (music) fade(music, 0, 300);
        } else {
            playMusic();
        }
    });
    paint();
}

function countdown(seconds) {
    const button = document.getElementById('login-submit');
    if (!button) return;
    const label = button.querySelector('[data-login-label]');
    const timer = button.querySelector('[data-login-timer]');
    const end = Date.now() + seconds * 1000;
    label.textContent = 'SISTEM TERKUNCI';

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
    const banner = document.getElementById('lockdown-banner');
    if (banner) banner.hidden = false;
}

/** Red system log lines that type themselves down both sides of the screen. */
function typeLogs(root, trace, still) {
    const stamp = () => new Date().toTimeString().slice(0, 8);
    const t = trace || {};
    const place = [t.city, t.country].filter(Boolean).join(', ') || 'resolving...';
    const left = [
        `[${stamp()}] auth.guard = LOCKED`,
        'misan = ERR_LOGIN_OVERFLOW',
        'misan = SOCKET_FORCE_CLOSE',
        '',
        `src_ip   = ${t.ip || 'unknown'}`,
        `geo      = ${place}`,
        `isp      = ${t.isp || 'unknown'}`,
        'misan = MALICIOUS_ACTIVITY_DETECTED',
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
    ];

    const write = (element, lines, delay) => {
        if (!element) return;
        if (still) {
            element.textContent = lines.join('\n');
            return;
        }
        let line = 0;
        let char = 0;
        const step = () => {
            if (line >= lines.length) return;
            const text = lines[line];
            char += 2;
            const done = lines.slice(0, line).join('\n');
            element.textContent = (done ? `${done}\n` : '') + text.slice(0, char);
            if (char >= text.length) {
                line += 1;
                char = 0;
                setTimeout(step, 140);
            } else {
                setTimeout(step, 22);
            }
        };
        setTimeout(step, delay);
    };

    write(root.querySelector('.lockdown-log--left'), left, 200);
    write(root.querySelector('.lockdown-log--right'), right, 900);

    // The toast's second line: what we can tell about the caller, in one glance.
    const line = root.querySelector('[data-trace-line]');
    if (line) {
        line.textContent = [t.ip, [t.city, t.country].filter(Boolean).join(', '), t.isp, [t.browser, t.os].filter(Boolean).join(' / ')]
            .filter(Boolean)
            .join('  ·  ');
    }
}

/**
 * Torn strips of pixel noise that hug the edges of the sign-in card and flicker around the
 * page, redrawn about eight times a second (well under any flashing threshold).
 */
function noise(root, still) {
    const canvas = root.querySelector('.lockdown-noise');
    const card = document.getElementById('login-card');
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
            const value = Math.floor(shade * 255);
            context.fillStyle = Math.random() < 0.08 ? `rgba(220, 30, 40, 0.9)` : `rgba(${value}, ${value}, ${value}, 0.85)`;
            context.fillRect(x + i, y, 2, height);
        }
    };

    const draw = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        const box = card.getBoundingClientRect();

        // Torn lines sticking out of the card's left and right edges.
        for (let i = 0; i < 14; i++) {
            const y = box.top + Math.random() * box.height;
            const width = 20 + Math.random() * 90;
            const fromLeft = Math.random() < 0.5;
            const x = fromLeft ? box.left - width * Math.random() : box.right - width * (1 - Math.random());
            strip(x, y, width, 1 + Math.floor(Math.random() * 3));
        }

        // A few loose blocks elsewhere on the page.
        for (let i = 0; i < 4; i++) {
            if (Math.random() < 0.5) continue;
            const width = 30 + Math.random() * 140;
            const height = 4 + Math.random() * 26;
            const x = Math.random() * (canvas.width - width);
            const y = Math.random() * (canvas.height - height);
            for (let row = 0; row < height; row += 2) strip(x + (Math.random() * 8 - 4), y + row, width, 2);
        }
    };

    if (still) {
        draw();
        return;
    }
    setInterval(draw, 125);
}

/**
 * @param {{ seconds?: number, instant?: boolean, context?: AudioContext | null }} options
 *   instant skips the glitch (a page that opens already locked); context is an AudioContext
 *   created inside the click that sent the sign-in, which is what lets browsers play the sound.
 */
export function start({ seconds = 60, instant = false, context = null, ip = '', trace = null } = {}) {
    const root = document.getElementById('lockdown');
    if (!root || started) {
        closeContext(context);
        return;
    }
    started = true;

    music = document.getElementById('lockdown-music');
    wireSoundButton(root);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const reveal = () => {
        document.body.classList.remove('is-glitching');
        document.body.classList.add('is-locked');
        root.hidden = false;
        lockForm();
        typeLogs(root, { ...(trace || {}), ip: (trace && trace.ip) || ip || root.dataset.ip }, reduced);
        noise(root, reduced);
        playMusic();
        countdown(seconds);
        document.getElementById('lockdown-banner')?.focus?.();
    };

    if (instant || reduced) {
        closeContext(context);
        reveal();
        return;
    }

    document.body.classList.add('is-glitching');
    if (context && !muted) {
        glitchSound(context);
    } else {
        closeContext(context);
    }
    setTimeout(reveal, 1000);
}
