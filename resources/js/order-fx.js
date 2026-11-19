// Motion helpers for the order status page: confetti, the tracker, count-up totals,
// the review form and its thank-you popup. Registered once from app.js.

// Flat paper-confetti colours, taken from the ticket: pink, raspberry, butter, chocolate, green, teal.
const COLORS = ['#f48fab', '#d94f73', '#ffd166', '#3b1f16', '#1f7a4d', '#6ec3c0'];

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const rupiah = (n) => `Rp ${Math.round(n).toLocaleString('id-ID')}`;

/* ---------------- Confetti: one shared canvas, one rAF loop ---------------- */

let canvas = null;
let ctx = null;
let particles = [];
let running = false;
let dpr = 1;

function ensureCanvas() {
    if (canvas) return;
    canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:500;';
    document.body.appendChild(canvas);
    ctx = canvas.getContext('2d');
    sizeCanvas();
    window.addEventListener('resize', sizeCanvas);
}

function sizeCanvas() {
    if (!canvas) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(window.innerWidth * dpr);
    canvas.height = Math.round(window.innerHeight * dpr);
}

function tick() {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    particles = particles.filter((p) => p.life > 0 && p.y < window.innerHeight + 40);

    for (const p of particles) {
        p.vx *= p.drag;
        p.vy = p.vy * p.drag + p.gravity;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.spin;
        p.wobble += 0.12;
        p.life -= 1;

        const fade = Math.min(1, p.life / 40);
        ctx.save();
        ctx.globalAlpha = fade;
        ctx.translate(p.x * dpr, p.y * dpr);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        const w = p.size * dpr;
        const h = p.size * dpr * (p.shape === 'stripe' ? 0.32 : 0.6);

        if (p.shape === 'tri') {
            ctx.scale(1, Math.max(0.2, Math.abs(Math.cos(p.wobble))));
            ctx.beginPath();
            ctx.moveTo(0, -w * 0.55);
            ctx.lineTo(w * 0.5, w * 0.4);
            ctx.lineTo(-w * 0.5, w * 0.4);
            ctx.closePath();
            ctx.fill();
        } else {
            // Squash one axis with a sine so the piece appears to flip in the air.
            ctx.scale(1, Math.max(0.15, Math.abs(Math.cos(p.wobble))));
            ctx.fillRect(-w / 2, -h / 2, w, h);
        }
        ctx.restore();
    }

    if (particles.length) {
        requestAnimationFrame(tick);
    } else {
        running = false;
        canvas.remove();
        canvas = null;
        ctx = null;
        window.removeEventListener('resize', sizeCanvas);
    }
}

/**
 * Fire a burst. x/y are viewport fractions (0..1), angle is degrees (90 = straight up).
 */
export function confetti({ x = 0.5, y = 0.5, angle = 90, spread = 70, count = 80, power = 13, gravity = 0.3, life = 170 } = {}) {
    if (reducedMotion()) return;
    ensureCanvas();

    for (let i = 0; i < count; i++) {
        const theta = ((angle + (Math.random() - 0.5) * spread) * Math.PI) / 180;
        const speed = power * (0.45 + Math.random() * 0.75);
        particles.push({
            x: x * window.innerWidth,
            y: y * window.innerHeight,
            vx: Math.cos(theta) * speed,
            vy: -Math.sin(theta) * speed,
            drag: 0.985 - Math.random() * 0.02,
            gravity,
            size: 7 + Math.random() * 8,
            color: COLORS[(Math.random() * COLORS.length) | 0],
            shape: ['rect', 'stripe', 'tri'][(Math.random() * 3) | 0],
            rot: Math.random() * Math.PI * 2,
            spin: (Math.random() - 0.5) * 0.3,
            wobble: Math.random() * Math.PI * 2,
            life: life + Math.random() * 50,
        });
    }

    if (!running) {
        running = true;
        requestAnimationFrame(tick);
    }
}

/** Two cannons from the bottom corners plus a shower from the top: the big celebration. */
export function celebrate() {
    confetti({ x: 0.08, y: 1.02, angle: 62, spread: 50, count: 55, power: 22 });
    confetti({ x: 0.92, y: 1.02, angle: 118, spread: 50, count: 55, power: 22 });
    setTimeout(() => confetti({ x: 0.5, y: -0.05, angle: 270, spread: 140, count: 40, power: 4, gravity: 0.18, life: 220 }), 420);
}

/* ---------------- Alpine components ---------------- */

export function registerOrderFx(Alpine) {
    // Tracker hero: tilt the sandwich with the pointer, replay on tap, live waiting timer.
    Alpine.data('orderTracker', (config) => ({
        state: config.state,
        active: 0,
        total: config.total,
        waited: '',
        clock: [],
        _timer: null,
        _tick: null,

        init() {
            this.updateWaited();
            if (this.state === 'pending' || this.state === 'other') {
                this._timer = setInterval(() => this.updateWaited(), 20000);
                this.updateClock();
                this._tick = setInterval(() => this.updateClock(), 1000);
            }
            if (this.state === 'confirmed') {
                // The third stamp lands at ~2.3s: celebrate right then.
                setTimeout(() => {
                    const node = this.$root.querySelector('.os-step.is-ready .os-node');
                    const box = node?.getBoundingClientRect();
                    confetti({
                        x: box ? (box.left + box.width / 2) / window.innerWidth : 0.5,
                        y: box ? (box.top + box.height / 2) / window.innerHeight : 0.4,
                        angle: 90,
                        spread: 110,
                        count: 46,
                        power: 12,
                    });
                }, 2300);
            }
        },

        // A ticket-machine counter: [m, m, ':', s, s] (hours are prepended once an hour has passed).
        updateClock() {
            const total = Math.max(0, Math.floor((Date.now() - new Date(config.since).getTime()) / 1000));
            const pad = (n) => String(n).padStart(2, '0');
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const sec = total % 60;
            this.clock = [...(h > 0 ? [String(h), ':'] : []), ...pad(m), ':', ...pad(sec)];
        },

        destroy() {
            clearInterval(this._timer);
            clearInterval(this._tick);
        },

        updateWaited() {
            const minutes = Math.max(0, Math.floor((Date.now() - new Date(config.since).getTime()) / 60000));
            if (minutes < 1) this.waited = 'baru saja';
            else if (minutes < 60) this.waited = `${minutes} menit`;
            else this.waited = `${Math.floor(minutes / 60)} jam ${minutes % 60} menit`;
        },

        tilt(event) {
            if (reducedMotion() || event.pointerType === 'touch') return;
            const box = this.$refs.stage.getBoundingClientRect();
            const px = (event.clientX - box.left) / box.width - 0.5;
            const py = (event.clientY - box.top) / box.height - 0.5;
            this.$refs.stage.style.setProperty('--ry', `${(px * 14).toFixed(2)}deg`);
            this.$refs.stage.style.setProperty('--rx', `${(-py * 12).toFixed(2)}deg`);
        },

        untilt() {
            this.$refs.stage.style.setProperty('--ry', '0deg');
            this.$refs.stage.style.setProperty('--rx', '0deg');
        },

        pick(index) {
            this.active = index;
        },

        poke(event) {
            const stack = this.$refs.stage.querySelector('.os-sandwich:not([style*="display: none"]) .os-stack');
            if (stack) {
                stack.classList.remove('is-squash');
                void stack.offsetWidth; // restart the squash
                stack.classList.add('is-squash');
            }
            if (this.state !== 'cancelled') {
                confetti({
                    x: event.clientX / window.innerWidth,
                    y: event.clientY / window.innerHeight,
                    angle: 90,
                    spread: 100,
                    count: 26,
                    power: 9,
                });
            }
        },
    }));

    // The thermal receipt prints itself out of the slot the first time it scrolls into view.
    Alpine.data('printOnView', () => ({
        init() {
            if (reducedMotion() || !('IntersectionObserver' in window)) return;
            const el = this.$el;
            el.classList.add('is-armed');
            const observer = new IntersectionObserver(
                (entries) => {
                    if (!entries.some((entry) => entry.isIntersecting)) return;
                    observer.disconnect();
                    el.classList.add('is-printing');
                },
                { threshold: 0.18 },
            );
            observer.observe(el);
        },
    }));

    // A money amount that counts up the first time it scrolls into view.
    Alpine.data('countUp', (to) => ({
        shown: rupiah(to),
        init() {
            if (reducedMotion() || !('IntersectionObserver' in window)) return;
            const observer = new IntersectionObserver(
                (entries) => {
                    if (!entries.some((entry) => entry.isIntersecting)) return;
                    observer.disconnect();
                    const start = performance.now();
                    const duration = 1100;
                    const step = (now) => {
                        const t = Math.min(1, (now - start) / duration);
                        const eased = 1 - Math.pow(1 - t, 4);
                        this.shown = rupiah(to * eased);
                        if (t < 1) requestAnimationFrame(step);
                    };
                    this.shown = rupiah(0);
                    requestAnimationFrame(step);
                },
                { threshold: 0.6 },
            );
            observer.observe(this.$el);
        },
    }));

    // Review form: star picker with sparkles, and a background POST so the popup can play.
    Alpine.data('reviewForm', (config) => ({
        rating: config.rating,
        hover: 0,
        popped: 0,
        bump: false,
        sending: false,
        text: config.text ?? '',
        errors: {},
        labels: ['', 'Kurang Memuaskan', 'Cukup Enak', 'Enak & Manis', 'Sangat Enak!', 'Luar Biasa Sempurna!'],
        _t: null,

        get shown() {
            return this.hover || this.rating;
        },

        // Eight comic impact lines radiate from a freshly picked star.
        spark(n) {
            return `--a:${(n - 1) * 45}deg`;
        },

        setRating(i) {
            this.rating = i;
            this.popped = 0;
            this.bump = false;
            this.$nextTick(() => {
                this.popped = i;
                this.bump = true;
                clearTimeout(this._t);
                this._t = setTimeout(() => {
                    this.popped = 0;
                    this.bump = false;
                }, 700);
            });
        },

        async submit(event) {
            if (this.sending) return;
            this.sending = true;
            this.errors = {};
            window.dispatchEvent(new CustomEvent('review:sending'));

            const form = event.target;
            try {
                const [response] = await Promise.all([
                    fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                        credentials: 'same-origin',
                    }),
                    wait(1300), // let the sending animation play, even on a fast connection
                ]);
                const data = await response.json().catch(() => ({}));

                if (response.ok) {
                    window.dispatchEvent(
                        new CustomEvent('review:done', {
                            detail: { rating: data.rating ?? this.rating, text: data.review_text ?? '' },
                        }),
                    );
                    return;
                }

                if (response.status === 422) {
                    this.errors = Object.fromEntries(Object.entries(data.errors ?? {}).map(([key, list]) => [key, list[0]]));
                    window.dispatchEvent(new CustomEvent('review:close'));
                    this.sending = false;
                    return;
                }

                window.dispatchEvent(
                    new CustomEvent('review:failed', {
                        detail: { message: data.message ?? 'Ulasan belum bisa dikirim. Coba muat ulang halaman ya.' },
                    }),
                );
            } catch (error) {
                // Offline or blocked: fall back to the plain form post so nothing is lost.
                form.submit();
            }
        },
    }));

    // Thank-you popup. Opens on submit (sending), flips to done, and also opens on page load
    // when the server redirected back with a success flash (no-JS fallback path).
    Alpine.data('reviewPopup', (config) => ({
        open: config.open,
        phase: config.open ? 'done' : 'idle',
        rating: config.rating ?? 5,
        text: config.text ?? '',
        message: '',
        fresh: false,

        init() {
            if (this.open) {
                this.lock();
                this.$nextTick(() => setTimeout(() => this.celebrate(), 900));
            }
        },

        get stampWord() {
            return this.rating >= 4 ? 'Makasih!' : this.rating === 3 ? 'Noted!' : 'Siap!';
        },

        get headline() {
            return (
                {
                    5: 'Luar Biasa! Makasih Banget!',
                    4: 'Yeay, Makasih Ya!',
                    3: 'Makasih Masukannya!',
                    2: 'Makasih Udah Jujur!',
                    1: 'Makasih Udah Jujur!',
                }[this.rating] ?? 'Makasih Ya!'
            );
        },

        get subline() {
            return this.rating >= 4
                ? 'Ulasan manismu udah tersimpan dan bikin tim Brread! senyum lebar.'
                : 'Ulasanmu udah tersimpan. Kami bakal bikin rasanya lebih mantap lagi.';
        },

        onSending() {
            this.open = true;
            this.phase = 'sending';
            this.lock();
        },

        onDone(event) {
            this.rating = event.detail.rating;
            this.text = event.detail.text;
            this.fresh = true;
            this.phase = 'done';
            // Fire the confetti the instant the big stamp hits the card.
            this.$nextTick(() => setTimeout(() => this.celebrate(), 900));
        },

        onFailed(event) {
            this.message = event.detail.message;
            this.fresh = true;
            this.phase = 'error';
        },

        celebrate() {
            if (!this.open) return;
            celebrate();
            this.$refs.primary?.focus({ preventScroll: true });
        },

        lock() {
            document.documentElement.classList.add('overflow-hidden');
        },

        close() {
            if (this.phase === 'sending') return;
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
            // A fresh review changed the page underneath: reload so the saved review shows.
            if (this.fresh) window.location.reload();
        },

        dismiss() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },
    }));
}
