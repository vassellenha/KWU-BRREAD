// Background music for the customer pages: window.brreadBgm.toggle(), setMuted(bool), isMuted(), isAvailable().
// Two <audio> elements stream the file through Web Audio (no decoding step, so a new page starts within a moment),
// and no loop attribute: each cycle hands over to the second element with an equal-power crossfade. The position is
// derived from a stored start time, so the music carries on across page loads instead of restarting.
// Never on a staff, superadmin, trap or sign-in page: no context, no fetch, no button, no ducking.
const BLOCKED = /^\/(staff|superadmin|admin|administrator|wp-admin|login|dashboard|panel|cpanel|phpmyadmin|backend)(\/|$)/i;
const allowed = !BLOCKED.test(location.pathname) && document.body?.hasAttribute('data-customer-site');

if (allowed) {
    const SRC = '/audio/brread-bgm.mp3';
    const MUTE_KEY = 'brread-bgm-muted';
    const POS_KEY = 'brread-bgm-pos';
    const HINT_KEY = 'brread-bgm-hinted';
    const OK_KEY = 'brread-bgm-ok';
    const ALIVE_KEY = 'brread-bgm-alive';
    const TARGET = 0.3; // times the element volume below: about 3% overall
    const ELEMENT_VOLUME = 0.1;
    const FADE_IN = 0.8;
    const RESUME_FADE = 0.25;
    const FADE_OUT = 0.4;
    const CROSSFADE = 2.5;
    const DUCK_LEVEL = 0.6;
    const DUCK_ON = new Set(['register', 'marimba', 'chime']);
    // Measured once on the shipped file (-50 dB): it starts on the first sample and its last 0.8 s is silence.
    const TRIM_START = 0.03;
    const DEV = Number(localStorage.getItem('brreadBgmDevLength')); // dev only: a short loop to watch the crossfade
    const TRIM_LENGTH = DEV > 3 * CROSSFADE ? DEV : 118.9;
    const PERIOD = TRIM_LENGTH - CROSSFADE;

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
    let primed = null; // the first voice, already downloading
    let voices = [];
    let playing = false;
    let startedOnce = false;
    let timer = null;
    let aliveTimer = null;
    let nextAt = 0;
    let pending = null;

    const fadeCurve = (rising) => Float32Array.from({ length: 48 }, (_, i) => Math.sin((rising ? i / 47 : 1 - i / 47) * Math.PI / 2));
    const introShowing = () => Boolean(document.getElementById('brread-intro')) && !document.documentElement.classList.contains('no-intro');
    const markAlive = () => store.set(ALIVE_KEY, String(Date.now()));

    // ---- button -------------------------------------------------------------------------------------------------

    function syncButtons() {
        const on = available === true && !muted;
        document.querySelectorAll('[data-bgm-toggle]').forEach((button) => {
            button.hidden = available !== true;
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.setAttribute('aria-label', on ? 'Matikan musik latar' : 'Nyalakan musik latar');
            button.title = on ? 'Matikan musik latar' : 'Nyalakan musik latar';
            button.classList.toggle('is-playing', on && playing);
        });
    }

    function setAvailable(value) {
        available = value;
        store.set(OK_KEY, value ? '1' : '0');
        syncButtons();
    }

    function hint() {
        if (store.get(HINT_KEY)) return;
        store.set(HINT_KEY, '1');
        document.querySelectorAll('[data-bgm-toggle]').forEach((button) => {
            button.classList.add('bgm-hint');
            setTimeout(() => button.classList.remove('bgm-hint'), 4000);
        });
    }

    // ---- audio graph --------------------------------------------------------------------------------------------

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

    // One <audio> element, buffering from the moment it is made.
    function makeVoice() {
        const element = new Audio();
        element.preload = 'auto';
        element.volume = ELEMENT_VOLUME; // applies even if a browser routes the element around the audio graph
        element.src = SRC;
        element.addEventListener('error', () => {
            if (available !== false && !startedOnce) setAvailable(false);
        });
        return { element, gain: null };
    }

    const whenReady = (element) => new Promise((resolve, reject) => {
        if (element.readyState >= 3) return resolve();
        element.addEventListener('canplay', resolve, { once: true });
        element.addEventListener('error', () => reject(new Error('audio')), { once: true });
    });

    async function connect(voice) {
        await whenReady(voice.element);
        if (!voice.gain) {
            voice.gain = ctx.createGain();
            voice.gain.gain.value = 0;
            ctx.createMediaElementSource(voice.element).connect(voice.gain).connect(level);
        }
    }

    // Starts the voice at `offset` seconds into the loop, fading in; lines the next hand-over up.
    async function startVoice(voice, offset, fadeIn) {
        await connect(voice);
        voice.element.currentTime = TRIM_START + offset + 0.06; // the small lead covers the seek
        await voice.element.play();

        const now = ctx.currentTime;
        voice.gain.gain.cancelScheduledValues(now);
        voice.gain.gain.setValueAtTime(0, now);
        if (fadeIn > 0) voice.gain.gain.setValueCurveAtTime(fadeCurve(true), now, fadeIn);
        else if (fadeIn === 0) voice.gain.gain.setValueAtTime(1, now);
        // null: the caller schedules the fade itself

        voices.push(voice);
        nextAt = now + (PERIOD - offset);
        pending = null;
    }

    function retire(voice, after) {
        setTimeout(() => {
            try { voice.element.pause(); } catch { /* gone */ }
            try { voice.gain?.disconnect(); } catch { /* gone */ }
            voices = voices.filter((v) => v !== voice);
        }, after * 1000 + 150);
    }

    // The next voice is made (and starts buffering) well ahead, then started right at the hand-over.
    function tick() {
        if (!playing || !ctx) return;
        const left = nextAt - ctx.currentTime;

        if (left < 6 && !pending) {
            pending = makeVoice();
            connect(pending).catch(() => {});
        }
        if (left <= 0.05 && pending) {
            const incoming = pending;
            const outgoing = voices[voices.length - 1];
            pending = 'starting';
            startVoice(incoming, -0.06, null).then(() => {
                // Equal-power crossfade: the old voice fades out while the new one fades in.
                const now = ctx.currentTime;
                incoming.gain.gain.cancelScheduledValues(now);
                incoming.gain.gain.setValueAtTime(0, now);
                incoming.gain.gain.setValueCurveAtTime(fadeCurve(true), now, CROSSFADE);
                if (outgoing?.gain) {
                    outgoing.gain.gain.cancelScheduledValues(now);
                    outgoing.gain.gain.setValueAtTime(outgoing.gain.gain.value, now);
                    outgoing.gain.gain.setValueCurveAtTime(fadeCurve(false), now, CROSSFADE);
                    retire(outgoing, CROSSFADE);
                }
            }).catch(() => { pending = null; });
        }
    }

    function position() {
        let saved = null;
        try { saved = JSON.parse(store.get(POS_KEY)); } catch { /* none yet */ }
        const length = Math.round(TRIM_LENGTH * 1000);
        if (!saved || saved.length !== length || !Number.isFinite(saved.start)) {
            saved = { start: Date.now(), length };
            store.set(POS_KEY, JSON.stringify(saved));
        }
        return (((Date.now() - saved.start) / 1000) % PERIOD + PERIOD) % PERIOD;
    }

    function rampLevel(to, seconds) {
        const now = ctx.currentTime;
        level.gain.cancelScheduledValues(now);
        level.gain.setValueAtTime(level.gain.value, now);
        level.gain.linearRampToValueAtTime(to, now + seconds);
    }

    async function begin() {
        if (playing || muted || available === false || document.hidden || !context()) return;
        if (introShowing()) return; // the poll or the next gesture starts it
        playing = true; // claimed up front: two quick gestures must not start two copies
        try {
            if (ctx.state === 'suspended') await ctx.resume();
            if (muted || document.hidden) throw new Error('stopped');
            const voice = primed ?? makeVoice();
            primed = null;
            // Music was playing on the previous page a moment ago: pick up quickly instead of fading in from silence.
            const fade = Date.now() - Number(store.get(ALIVE_KEY) || 0) < 5000 ? RESUME_FADE : FADE_IN;
            await startVoice(voice, position(), fade);
            rampLevel(TARGET, fade);
            startedOnce = true;
            setAvailable(true);
            timer = setInterval(tick, 200);
            markAlive();
            aliveTimer = setInterval(markAlive, 2000);
            syncButtons();
        } catch {
            // Not allowed to play yet (no gesture) or stopped meanwhile: the next gesture tries again.
            playing = false;
            voices.forEach(({ element }) => element.pause());
            voices = [];
            syncButtons();
        }
    }

    function end() {
        if (!playing) return;
        playing = false;
        clearInterval(timer);
        clearInterval(aliveTimer);
        store.set(ALIVE_KEY, '0');
        syncButtons();
        if (!ctx) return;
        rampLevel(0, FADE_OUT);
        const stopping = voices;
        voices = [];
        pending = null;
        setTimeout(() => stopping.forEach(({ element }) => { try { element.pause(); } catch { /* gone */ } }), FADE_OUT * 1000 + 60);
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

    const sfx = window.brreadSfx;
    if (sfx) {
        const previous = sfx.onPlay;
        sfx.onPlay = (name, opts) => {
            previous?.(name, opts);
            if (DUCK_ON.has(name)) duckFor(name === 'marimba' ? 0.9 : 0.6);
        };
    }

    // ---- start, gestures, visibility ----------------------------------------------------------------------------

    // Starts by itself when the browser already allows audio (an earlier page had a gesture), so moving between
    // pages carries the music on. Otherwise the first gesture starts it.
    function autoStart() {
        if (playing || muted || available !== true) return;
        if (introShowing()) {
            setTimeout(autoStart, 100);
            return;
        }
        begin();
    }

    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => {
        window.addEventListener(type, () => {
            if (!muted && available === true && !playing) begin();
        }, { passive: true, capture: true });
    });

    // A hidden tab stops the music; coming back restarts it at the right place in the loop.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (!playing) return;
            end();
            store.set(ALIVE_KEY, '0');
            resumeOnShow = true;
        } else if (resumeOnShow) {
            resumeOnShow = false;
            if (!muted) begin();
        }
    });
    let resumeOnShow = false;

    window.addEventListener('pagehide', () => {
        if (playing) markAlive();
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== MUTE_KEY) return;
        muted = event.newValue === '1';
        if (muted) end();
        syncButtons();
    });

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-bgm-toggle]') : null;
        if (!button) return;
        store.set(HINT_KEY, '1');
        button.classList.remove('bgm-hint');
        window.brreadSfx?.play?.('tick');
        toggle();
    });

    async function checkFile() {
        try {
            const response = await fetch(SRC, { method: 'HEAD', cache: 'no-cache' });
            setAvailable(response.ok && (response.headers.get('content-type') || '').startsWith('audio'));
            if (available) hint();
        } catch {
            setAvailable(false);
        }
    }

    // This module is imported after the page has loaded, so it may be too late for DOMContentLoaded.
    const init = () => {
        if (store.get(OK_KEY) === '1') setAvailable(true); // the button shows at once on every page after the first
        if (muted) {
            if (available !== true) checkFile();
            return;
        }
        if (available !== true) checkFile();
        else hint();
        if (context()) primed = makeVoice(); // starts downloading now, before any gesture
        autoStart();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    document.addEventListener('alpine:initialized', syncButtons);

    window.brreadBgm = { toggle, setMuted, isMuted: () => muted, isAvailable: () => available === true };
}
