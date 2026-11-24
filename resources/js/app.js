import '@fontsource/bagel-fat-one/latin-400.css';
import '@fontsource/space-mono/latin-400.css';
import '@fontsource/space-mono/latin-700.css';
import Alpine from 'alpinejs';
import axios from 'axios';
import { registerOrderFx } from './order-fx.js';
import { registerStaffBatch } from './staff-batch.js';
import { registerGridNav } from './staff-grid.js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export const INGREDIENT_IMAGES = {
    'Plain White Bread': '/img/ingredients/bread-white.webp',
    'Whole Wheat Bread': '/img/ingredients/bread-wheat.webp',
    Vanilla: '/img/ingredients/icecream-vanilla.webp',
    Strawberry: '/img/ingredients/icecream-strawberry.webp',
    Chocolate: '/img/ingredients/icecream-chocolate.webp',
    Neapolitan: '/img/ingredients/icecream-neapolitan.webp',
    'Durian Musang King': '/img/ingredients/icecream-durian-musang-king.webp',
    'Es Teler': '/img/ingredients/icecream-es-teler.webp',
    Matcha: '/img/ingredients/icecream-matcha.webp',
    'Chocolate Sauce': '/img/ingredients/topping-chocolate-sauce.webp',
    'Rainbow Sprinkles': '/img/ingredients/topping-sprinkles.webp',
    'Marshmallow Mini': '/img/ingredients/topping-marshmallow-mini.webp',
    'Yupi Gummy Bear Mini': '/img/ingredients/topping-gummy-bears.webp',
};
window.INGREDIENT_IMAGES = INGREDIENT_IMAGES;

// Hand-drawn icons in resources/views/partials/ingredient-icons.blade.php (same list as App\Support\IngredientIcon).
// Toppings show their shape; the multi-part flavors show a drawn swatch instead of a plain colour dot.
export const INGREDIENT_ICONS = {
    'Chocolate Sauce': 'ing-chocolate-sauce',
    'Rainbow Sprinkles': 'ing-rainbow-sprinkles',
    'Marshmallow Mini': 'ing-marshmallow-mini',
    'Yupi Gummy Bear Mini': 'ing-yupi-gummy-bear-mini',
    Matcha: 'ing-matcha',
    Neapolitan: 'ing-neapolitan',
    'Durian Musang King': 'ing-durian-musang-king',
    'Es Teler': 'ing-es-teler',
};
export const ingredientIcon = (name) => (name ? (INGREDIENT_ICONS[name] ?? null) : null);
window.ingredientIcon = ingredientIcon;

// Items the admin adds in the Menu module have no photo of their own, so they borrow the
// generic bread or ice cream picture. Toppings without art simply show no picture.
const FALLBACK_IMAGES = {
    bread: '/img/ingredients/bread-white.webp',
    ice_cream: '/img/ingredients/icecream-vanilla.webp',
};
export const ingredientImage = (item) => (item ? (INGREDIENT_IMAGES[item.name] ?? FALLBACK_IMAGES[item.category] ?? null) : null);
window.ingredientImage = ingredientImage;

// The Studio's three.js scene. Kept at module level, never on the Alpine component:
// Alpine would wrap it in a reactive proxy, which three.js objects do not survive.
let studio3d = null;

const MAX_PER_CREATION = 20;
const MAX_CART_LINES = 20;

// Runs in the capture phase so x-model on the same input reads the cleaned value.
const INPUT_FILTERS = {
    name: (value) => value.replace(/[^\p{L}\s.'-]/gu, ''),
    phone: (value) => value.replace(/[^0-9+\s-]/g, '').replace(/(?!^)\+/g, ''),
    integer: (value) => value.replace(/\D/g, ''),
};

document.addEventListener(
    'input',
    (event) => {
        const filter = INPUT_FILTERS[event.target.dataset?.filter];
        if (!filter || event.isComposing) return;
        const cleaned = filter(event.target.value);
        if (cleaned !== event.target.value) event.target.value = cleaned;
    },
    true,
);

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Clones a small round thumbnail and flies it from sourceEl to targetEl along an arc,
// so an add-to-cart / ingredient-pick action reads as "this item went there" (spatial consistency).
function flyClone({ sourceEl, targetEl, imageSrc, size = 44, onLand }) {
    if (!sourceEl || !targetEl || prefersReducedMotion()) {
        onLand?.();
        return;
    }

    const sourceRect = sourceEl.getBoundingClientRect();
    const targetRect = targetEl.getBoundingClientRect();

    const clone = document.createElement(imageSrc ? 'img' : 'div');
    if (imageSrc) clone.src = imageSrc;
    Object.assign(clone.style, {
        position: 'fixed',
        zIndex: '9999',
        left: `${sourceRect.left + sourceRect.width / 2 - size / 2}px`,
        top: `${sourceRect.top + sourceRect.height / 2 - size / 2}px`,
        width: `${size}px`,
        height: `${size}px`,
        borderRadius: '9999px',
        objectFit: 'cover',
        pointerEvents: 'none',
        boxShadow: '0 8px 20px rgba(61, 27, 19, 0.3)',
        background: imageSrc ? 'transparent' : '#a62d42',
    });
    document.body.appendChild(clone);

    const dx = targetRect.left + targetRect.width / 2 - (sourceRect.left + sourceRect.width / 2);
    const dy = targetRect.top + targetRect.height / 2 - (sourceRect.top + sourceRect.height / 2);
    const liftY = dy - Math.max(80, Math.abs(dy) * 0.6);

    const anim = clone.animate(
        [
            { transform: 'translate(0, 0) scale(1) rotate(0deg)', opacity: 1, offset: 0 },
            { transform: `translate(${dx * 0.5}px, ${liftY}px) scale(0.85) rotate(10deg)`, opacity: 1, offset: 0.55 },
            { transform: `translate(${dx}px, ${dy}px) scale(0.25) rotate(20deg)`, opacity: 0.5, offset: 1 },
        ],
        { duration: 650, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)', fill: 'forwards' },
    );

    anim.onfinish = () => {
        clone.remove();
        onLand?.();
    };
}

// Discrete, user-triggered feedback (not a 100+/day action), so a playful overshoot is fair game.
function pop(el, className = 'cart-badge-pop') {
    if (!el || prefersReducedMotion()) return;
    el.classList.remove(className);
    void el.offsetWidth;
    el.classList.add(className);
}

window.__brreadFx = { flyClone, pop };

function formatPrice(n) {
    return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}

function isValidCartLine(line) {
    return (
        line &&
        typeof line.uid === 'string' &&
        line.bread?.id &&
        line.flavor?.id &&
        Array.isArray(line.toppings) &&
        Number.isInteger(line.quantity) &&
        line.quantity > 0
    );
}

function loadCart() {
    try {
        const raw = localStorage.getItem('brreadCart');
        const items = raw ? JSON.parse(raw) : [];
        return Array.isArray(items) ? items.filter(isValidCartLine) : [];
    } catch (e) {
        return [];
    }
}

function saveCart(items) {
    try {
        localStorage.setItem('brreadCart', JSON.stringify(items));
    } catch (e) {
        // Private browsing / full storage — cart still works for this tab, just won't persist.
    }
}

function creationKey({ bread, flavor, toppings }) {
    return [bread.id, flavor.id, ...toppings.map((t) => t.id).sort((a, b) => a - b)].join('|');
}

function creationComponents({ bread, flavor, toppings }) {
    return [bread, flavor, ...toppings];
}

// Live ingredients, prices, and stock from the same table MRP manages. Shared so every
// page sees the same numbers and can refresh them after a checkout changes stock.
Alpine.store('catalog', {
    components: { bread: [], ice_cream: [], topping: [] },
    // The one-click combos the admin manages in Staff > Menu: { name, bread, flavor, toppings: [names] }.
    signatures: [],
    loaded: false,
    failed: false,
    pendingLoad: null,

    load() {
        this.pendingLoad ??= axios
            .get('/api/components')
            .then((response) => {
                const { signatures = [], ...components } = response.data;
                this.components = components;
                this.signatures = signatures;
                this.loaded = true;
                this.failed = false;
                window.dispatchEvent(new CustomEvent('catalog-updated'));
            })
            .catch(() => {
                this.failed = true;
            })
            .finally(() => {
                this.pendingLoad = null;
            });

        return this.pendingLoad;
    },

    byId(id) {
        return [...this.components.bread, ...this.components.ice_cream, ...this.components.topping].find((c) => c.id === id);
    },

    creationFromPreset(preset) {
        if (!preset) return null;

        const bread = this.components.bread.find((c) => c.name === preset.bread);
        const flavor = this.components.ice_cream.find((c) => c.name === preset.flavor);
        const toppings = preset.toppings.map((name) => this.components.topping.find((c) => c.name === name));

        return bread && flavor && toppings.every(Boolean) ? { bread, flavor, toppings } : null;
    },

    presetNamed(name) {
        return this.signatures.find((preset) => preset.name === name);
    },
});

// One cart shared across every page (navbar badge, builder, checkout drawer) since there's
// no customer auth to key a server-side cart to.
Alpine.store('cart', {
    items: loadCart(),
    open: false,

    unitsReserved(componentId) {
        return this.items.reduce((sum, line) => (creationComponents(line).some((c) => c.id === componentId) ? sum + line.quantity : sum), 0);
    },

    // How many more of this creation fit, given live stock minus what the cart already holds.
    // Unknown stock (catalog not loaded yet) falls back to the per-creation limit; the server still checks.
    unitsAvailable(creation) {
        const catalog = Alpine.store('catalog');
        if (!catalog.loaded) return MAX_PER_CREATION;

        return creationComponents(creation).reduce((limit, component) => {
            const live = catalog.byId(component.id);
            const left = live ? live.stock_quantity - this.unitsReserved(component.id) : 0;
            return Math.min(limit, Math.max(0, left));
        }, MAX_PER_CREATION);
    },

    // The same combination adds to its existing line, so one creation never takes up two lines.
    add({ bread, flavor, toppings, quantity }) {
        const creation = { bread, flavor, toppings };
        const existing = this.items.find((line) => creationKey(line) === creationKey(creation));
        const room = Math.min(this.unitsAvailable(creation), MAX_PER_CREATION - (existing?.quantity ?? 0));

        if (room <= 0 || (!existing && this.items.length >= MAX_CART_LINES)) return 0;

        const added = Math.min(quantity, room);

        if (existing) {
            existing.quantity += added;
        } else {
            this.items.push({
                uid: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                bread: { id: bread.id, name: bread.name, price: bread.price },
                flavor: { id: flavor.id, name: flavor.name, price: flavor.price },
                toppings: toppings.map((t) => ({ id: t.id, name: t.name, price: t.price })),
                quantity: added,
            });
        }

        saveCart(this.items);
        return added;
    },

    remove(uid) {
        this.items = this.items.filter((i) => i.uid !== uid);
        saveCart(this.items);
    },

    canIncrease(line) {
        return line.quantity < MAX_PER_CREATION && this.unitsAvailable(line) > 0;
    },

    setQuantity(uid, quantity) {
        const item = this.items.find((i) => i.uid === uid);
        if (!item) return;
        if (quantity > item.quantity && !this.canIncrease(item)) return;
        item.quantity = Math.min(MAX_PER_CREATION, Math.max(1, quantity));
        saveCart(this.items);
    },

    clear() {
        this.items = [];
        saveCart(this.items);
    },

    // Prices can change while a cart sits in localStorage; the server charges the live price.
    syncPrices() {
        const catalog = Alpine.store('catalog');
        this.items.forEach((line) => {
            creationComponents(line).forEach((component) => {
                const live = catalog.byId(component.id);
                if (live) component.price = live.price;
            });
        });
        saveCart(this.items);
    },

    lineProblem(line) {
        const catalog = Alpine.store('catalog');
        if (!catalog.loaded) return null;

        for (const component of creationComponents(line)) {
            const live = catalog.byId(component.id);
            if (!live) return `${component.name} is no longer available.`;
            if (live.stock_quantity <= 0) return `${component.name} just sold out.`;
            if (live.stock_quantity < this.unitsReserved(component.id)) return `Only ${live.stock_quantity} ${component.name} left across your cart.`;
        }

        return null;
    },

    get hasProblem() {
        return this.items.some((line) => this.lineProblem(line) !== null);
    },

    lineTotal(item) {
        const unit =
            parseFloat(item.bread.price) +
            parseFloat(item.flavor.price) +
            item.toppings.reduce((sum, t) => sum + parseFloat(t.price), 0);
        return unit * item.quantity;
    },

    get count() {
        return this.items.reduce((sum, i) => sum + i.quantity, 0);
    },

    get total() {
        return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0);
    },

    toApiItems() {
        return this.items.map((i) => ({
            component_ids: [i.bread.id, i.flavor.id, ...i.toppings.map((t) => t.id)],
            quantity: i.quantity,
        }));
    },
});

window.addEventListener('storage', (event) => {
    if (event.key === 'brreadCart') Alpine.store('cart').items = loadCart();
});

// One in-page confirmation dialog for the whole app, replacing the browser's own confirm() box
// (which shows "localhost says" / the site address). Any form with data-confirm opens it first.
Alpine.store('dialog', {
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    tone: 'primary',
    resolver: null,
    lastFocus: null,

    ask(options = {}) {
        if (this.resolver) this.resolver(false);
        this.title = options.title || 'Are you sure?';
        this.message = options.message || '';
        this.confirmLabel = options.confirmLabel || 'Confirm';
        this.cancelLabel = options.cancelLabel || 'Cancel';
        this.tone = options.tone === 'danger' ? 'danger' : 'primary';
        this.lastFocus = document.activeElement;
        this.open = true;
        return new Promise((resolve) => {
            this.resolver = resolve;
        });
    },

    answer(result) {
        if (!this.open) return;
        this.open = false;
        const resolve = this.resolver;
        this.resolver = null;
        if (resolve) resolve(result);
        if (this.lastFocus && this.lastFocus.focus) this.lastFocus.focus();
    },
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;

    event.preventDefault();
    Alpine.store('dialog')
        .ask({
            title: form.dataset.confirmTitle,
            message: form.dataset.confirm,
            confirmLabel: form.dataset.confirmLabel,
            cancelLabel: form.dataset.cancelLabel,
            tone: form.dataset.confirmTone,
        })
        .then((ok) => {
            if (!ok) return;
            form.dataset.confirmed = '1';
            form.submit();
        });
});

// Staff and superadmin sign-in. The form posts through fetch so a failed attempt keeps the page
// (and the browser's permission to play sound) and a lockdown can glitch in without a reload.
// With scripts off, the form still posts normally and the server answers with a full page.
function primeAudio() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return null;
        const context = new AudioContextClass();
        context.resume?.();
        return context;
    } catch {
        return null;
    }
}

Alpine.data('staffLogin', (initialUsername, captchaBase, captchaStamp = Date.now()) => ({
    username: initialUsername,
    password: '',
    showPassword: false,
    error: '',
    busy: false,
    // Starts on the stamp the server already put in the image's src, so Alpine binding :src does not
    // load a second image: each load draws a new code, and the slower one used to win the session.
    captchaStamp,
    _nav: null,

    init() {
        this.setupKeyboard();

        // Fetch the scenes' photo and sounds once someone starts typing a password.
        const warm = () => import('./prank.js').then((prank) => prank.preload()).catch(() => {});
        this.$el.querySelector('#password')?.addEventListener('focus', warm, { once: true });

        // Three misses in and reloaded: the form comes back cursed for the last try.
        if ('prankCursed' in this.$el.dataset) {
            import('./prank.js').then((prank) => prank.curse());
        }

        const paused = Number(this.$el.dataset.prankSeconds || 0);
        if (paused > 0) {
            import('./prank.js').then((prank) => prank.resumePause(paused, this.$el.dataset.prankIp || null));
        } else {
            // The server has no pause (any more). The browser may still hold a longer one (a blue screen
            // stretches it), and that is carried on; anything stale is dropped.
            let kept = null;
            try {
                kept = JSON.parse(localStorage.getItem('brread-prank') || sessionStorage.getItem('brread-prank') || 'null');
            } catch {
                // Storage is blocked.
            }

            if (kept && kept.until > Date.now()) {
                import('./prank.js').then((prank) => prank.resumeHold());
            } else {
                try {
                    localStorage.removeItem('brread-prank');
                    sessionStorage.removeItem('brread-prank');
                } catch {
                    // Storage is blocked.
                }
            }
        }
    },

    destroy() {
        this._nav?.abort();
    },

    // ArrowUp/ArrowDown walk the boxes, ArrowLeft/ArrowRight walk the buttons, and a ring slides to wherever focus lands.
    // One delegated listener on the root (the back link sits outside the form); nothing here runs while the form is locked.
    setupKeyboard() {
        const root = this.$el;
        this._nav = new AbortController();
        const { signal } = this._nav;
        const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const find = (selector) => root.querySelector(selector);
        const usable = (node) => node && !node.disabled && node.getAttribute('aria-disabled') !== 'true'
            && node.getAttribute('tabindex') !== '-1' && node.getClientRects().length > 0 && !node.closest('[hidden]');

        // Locked by the lockdown, paused or taken over by a prank scene, or signing in: the arrows stay out of it.
        const blocked = () => this.busy
            || root.querySelector('fieldset:disabled')
            || document.body.classList.contains('is-locked')
            || document.body.classList.contains('is-glitching')
            || root.dataset.prankSeconds > 0
            || document.querySelector('.prank-tv, .prank-ok, .prank-doom, .prank-trace, #lockdown.is-open');

        const all = () => ['#username', '#password', '#login-eye', '#login-refresh', '#captcha', '#login-remember', '#login-submit', '#login-back']
            .map(find).filter(usable);
        const rows = () => ['#username', '#password', '#captcha', '#login-remember', '#login-submit', '#login-back'].map(find).filter(usable);
        const isText = (node) => node.matches('textarea, input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"])');

        root.addEventListener('keydown', (event) => {
            const key = event.key;
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key)) return;
            if (event.defaultPrevented || event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            const from = event.target;
            if (!(from instanceof Element) || blocked()) return;

            let next = null;
            if (key === 'ArrowDown' || key === 'ArrowUp') {
                const list = rows();
                // The eye belongs to the password row; the refresh button sits between password and captcha.
                let at = list.indexOf(from);
                if (at < 0) {
                    const password = list.indexOf(find('#password'));
                    if (from.id === 'login-eye') at = password;
                    else if (from.id === 'login-refresh') at = password + 0.5;
                    else return;
                }
                next = key === 'ArrowDown' ? list.find((_, i) => i > at) : [...list].reverse().find((_, i) => list.length - 1 - i < at);
            } else {
                if (isText(from)) return; // the caret keeps the left and right arrows
                const list = all();
                const at = list.indexOf(from);
                if (at < 0) return;
                next = list[at + (key === 'ArrowRight' ? 1 : -1)];
            }

            if (!next) return;
            event.preventDefault();
            next.focus();
        }, { signal });

        // The ring, and a small nudge on the box that gained focus.
        const ring = document.createElement('i');
        ring.className = 'login-ring';
        ring.setAttribute('aria-hidden', 'true');
        if (getComputedStyle(root).position === 'static') root.style.position = 'relative';
        root.append(ring);
        let travel = null;

        root.addEventListener('focusin', (event) => {
            const to = event.target;
            const from = event.relatedTarget;
            if (reduced() || !(to instanceof Element) || !(from instanceof Element) || !root.contains(from) || !root.contains(to) || to === ring) return;
            try {
                const base = root.getBoundingClientRect();
                const a = from.getBoundingClientRect();
                const b = to.getBoundingClientRect();
                if (!a.width || !b.width || !base.width) return; // cannot be measured: the normal focus ring does the job
                const frame = (r) => ({ transform: `translate(${r.left - base.left}px, ${r.top - base.top}px)`, width: `${r.width}px`, height: `${r.height}px` });

                travel?.cancel();
                ring.style.opacity = '1';
                const mine = ring.animate([frame(a), frame(b)], { duration: 220, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)', fill: 'forwards' });
                travel = mine;
                // Only the latest trip may hide the ring (an older one was cancelled by this one).
                mine.finished.then(() => {
                    if (travel !== mine) return;
                    mine.cancel();
                    ring.style.opacity = '0';
                }).catch(() => {});
                to.animate([{ transform: 'scale(1.025)' }, { transform: 'none' }], { duration: 220, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
            } catch {
                ring.style.opacity = '0';
            }
        }, { signal });
        signal.addEventListener('abort', () => ring.remove());
    },

    get captchaUrl() {
        return `${captchaBase}?v=${this.captchaStamp}`;
    },

    refreshCaptcha() {
        this.captchaStamp = Date.now();
        const field = this.$refs.captcha;
        if (field) field.value = '';
    },

    async submit(event) {
        if (this.busy) return;

        const form = event.target;
        const context = primeAudio();
        let navigating = false; // a successful sign-in keeps the button busy until the browser has moved on
        this.busy = true;
        this.error = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok && data.redirect) {
                // Winning the last try on the broken page gets the victory scene before the menu.
                if (document.body.classList.contains('is-broken')) {
                    const prank = await import('./prank.js');
                    await prank.success(data.redirect, { context, username: this.username });
                    return;
                }

                // A normal sign-in gets the quick version of the same scene. The browser is sent on by whichever comes
                // first: the scene ending or the 3s cap, never twice, and a prank.js that fails to load is a plain redirect.
                navigating = true;
                let sent = false;
                const go = () => {
                    if (sent) return;
                    sent = true;
                    context?.close?.();
                    window.location.href = data.redirect;
                };
                const cap = window.setTimeout(go, 3000);
                const warm = document.createElement('link');
                warm.rel = 'prefetch';
                warm.href = data.redirect;
                document.head.append(warm);
                try {
                    const prank = await import('./prank.js');
                    await prank.quickSuccess({ username: this.username });
                } catch {
                    // Plain redirect below.
                }
                window.clearTimeout(cap);
                go();
                return;
            }

            if (response.status === 429 && data.locked) {
                const lockdown = await import('./lockdown.js');
                lockdown.start({ seconds: data.retry_after, context, ip: data.ip, trace: data.trace, username: this.username });
                return;
            }

            // A wrong password (or a paused address) gets its terminal pop-up; the scene owns the sound.
            if (data.prank && (response.status === 422 || response.status === 423)) {
                const prank = await import('./prank.js');
                prank.play(data.prank, { context });
                if (response.status === 423) return;
            } else {
                context?.close?.();
            }

            if (response.status === 422) {
                const errors = data.errors || {};
                const first = Object.values(errors)[0];
                this.error = (Array.isArray(first) ? first[0] : first) || 'Check your details and try again.';
                // A wrong password is cleared; a wrong captcha keeps what was typed.
                if (!('captcha' in errors)) this.password = '';
                this.refreshCaptcha();
            } else if (response.status === 419) {
                this.error = 'This page expired. Reload it and try again.';
            } else {
                this.error = 'Something went wrong. Try again.';
            }
        } catch {
            context?.close?.();
            this.error = 'Could not reach the server. Check your connection and try again.';
        } finally {
            if (!navigating) this.busy = false;
        }
    },
}));

function readTrace(element) {
    try {
        return element.dataset.trace ? JSON.parse(element.dataset.trace) : null;
    } catch {
        return null;
    }
}

// A page that opens already locked (a blocked address reloading) shows the screen at once.
document.addEventListener('DOMContentLoaded', () => {
    const lockdown = document.getElementById('lockdown');

    const here = window.location.pathname + window.location.search;
    let saved = null;
    try {
        saved = JSON.parse(sessionStorage.getItem('brread-lock') || localStorage.getItem('brread-lock') || 'null');
    } catch {
        saved = null;
    }
    const resuming = saved && !saved.released && saved.until > Date.now() && saved.path === here;

    // The server let this page through unlocked (the lock ran out or was lifted), so any
    // lock remembered for it in the browser is stale.
    if (lockdown && lockdown.dataset.autostart !== '1' && lockdown.dataset.preview !== '1' && saved && saved.path === here) {
        try {
            localStorage.removeItem('brread-lock');
            sessionStorage.removeItem('brread-lock');
        } catch {
            // Storage is off.
        }
    }

    // A reloaded preview carries straight on where it was.
    if (lockdown && lockdown.dataset.preview === '1' && resuming && saved.preview) {
        document.getElementById('lockdown-preview-hint')?.remove();
        import('./lockdown.js').then((module) => module.start({
            seconds: Math.ceil((saved.until - Date.now()) / 1000),
            instant: true,
            preview: true,
            ip: lockdown.dataset.ip,
            trace: readTrace(lockdown),
        }));
        return;
    }

    // Superadmin preview: the first click plays the whole thing, sound included.
    if (lockdown && lockdown.dataset.preview === '1') {
        document.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const context = primeAudio();
            document.getElementById('lockdown-preview-hint')?.remove();
            import('./lockdown.js').then((module) => module.start({ seconds: 600, context, preview: true, ip: lockdown.dataset.ip, trace: readTrace(lockdown) }));
        }, { capture: true, once: true });
    }

    if (lockdown && lockdown.dataset.autostart === '1') {
        import('./lockdown.js').then((module) => module.start({
            seconds: Number(lockdown.dataset.seconds) || 60,
            instant: true,
            ip: lockdown.dataset.ip,
            trace: readTrace(lockdown),
            username: lockdown.dataset.name,
        }));
    }
});

Alpine.data('cartDrawer', () => ({
    customerName: '',
    customerPhone: '',
    submitting: false,
    confirming: false,
    errorMessage: '',
    orderSuccess: null,
    // The shop's WhatsApp link from Contact in the panel ("" until the superadmin sets a number).
    admins: [],
    waUrl: '',

    formatPrice,

    init() {
        try {
            this.admins = JSON.parse(this.$el.dataset.waAdmins || '[]');
        } catch {
            this.admins = [];
        }

        this.$watch('$store.cart.open', (isOpen) => {
            document.documentElement.classList.toggle('overflow-hidden', isOpen);

            if (isOpen) {
                Alpine.store('catalog').load().then(() => Alpine.store('cart').syncPrices());
                return;
            }

            // A finished order must not linger: reopening shows the cart, not the old receipt.
            this.errorMessage = '';
            this.confirming = false;
            if (this.orderSuccess) {
                this.orderSuccess = null;
                this.waUrl = '';
                this.customerName = '';
                this.customerPhone = '';
            }
        });
    },

    close() {
        Alpine.store('cart').open = false;
    },

    // The first WhatsApp message to the admin, ready to send: name, number, order id and total.
    buildWaUrl(admin, orderNumber, total, name, phone) {
        if (!admin?.url) return '';

        // The admin's name from Contact in the panel; a bare number (no name set) falls back to the shop.
        const adminName = /[A-Za-z]/.test(admin.name || '') ? admin.name : 'Brread!';
        const money = formatPrice(total);

        // Each admin has a message style set in the panel: "ceria" (playful, emoji) or "formal" (corporate).
        const text = admin.style === 'formal'
            ? [
                `Halo, ${adminName}.`,
                '',
                'Dengan hormat,',
                'Saya bermaksud mengonfirmasi pesanan dengan rincian berikut:',
                '',
                `Nama: ${name}`,
                `Nomor HP: ${phone}`,
                `Nomor Pesanan: #${orderNumber}`,
                `Total Pembayaran: ${money}`,
                '',
                'Mohon informasi mengenai metode dan tata cara pembayarannya.',
                'Atas perhatian dan bantuannya, saya ucapkan terima kasih.',
                '',
                'Hormat saya,',
                name,
            ].join('\n')
            : [
                `Haloo ${adminName}! 👋🍦`,
                'Aku mau konfirmasi pesanan nih 🥪✨',
                '',
                `👤 Nama: ${name}`,
                `📱 No. HP: ${phone}`,
                `🧾 No. Pesanan: #${orderNumber}`,
                `💰 Total: ${money}`,
                '',
                'Mohon info cara pembayarannya ya 🙏',
                'Makasih banyak! 💖',
            ].join('\n');

        return `${admin.url}?text=${encodeURIComponent(text)}`;
    },

    // Step before the order is sent: the customer checks the name and number they typed.
    review() {
        this.errorMessage = '';

        if (Alpine.store('cart').items.length === 0) {
            this.errorMessage = 'Your cart is empty. Add a creation first.';
            return;
        }
        if (!this.customerName.trim() || !this.customerPhone.trim()) {
            this.errorMessage = 'Please fill in your name and phone number.';
            return;
        }

        this.confirming = true;
    },

    // admin is the one the customer picked ({ name, url, style }), or null when no number is set.
    async checkout(admin = null) {
        const cart = Alpine.store('cart');
        this.errorMessage = '';
        this.confirming = false;

        if (cart.items.length === 0) {
            this.errorMessage = 'Your cart is empty — add a creation first.';
            return;
        }
        if (!this.customerName.trim() || !this.customerPhone.trim()) {
            this.errorMessage = 'Please fill in your name and phone number.';
            return;
        }

        this.submitting = true;
        const name = this.customerName.trim();
        const phone = this.customerPhone.trim();

        try {
            const res = await axios.post('/api/orders', {
                customer_name: this.customerName,
                customer_phone: this.customerPhone,
                items: cart.toApiItems(),
            });
            this.orderSuccess = res.data.order_number;
            this.waUrl = this.buildWaUrl(admin, res.data.order_number, res.data.total_price, name, phone);
            cart.clear();

            // The order is in; the customer now confirms it with the shop on WhatsApp. A short pause lets the
            // "Order placed" card show first, and the card keeps a link in case the redirect does not happen.
            if (this.waUrl) {
                setTimeout(() => {
                    window.location.href = this.waUrl;
                }, 700);
            }
        } catch (e) {
            const errors = e.response?.data?.errors ?? {};
            this.errorMessage =
                Object.values(errors)[0]?.[0] ??
                (e.response?.status === 503 ? e.response?.data?.message : null) ??
                (e.response?.status === 429 ? 'Too many orders in a row. Wait a minute and try again.' : null) ??
                'Something went wrong. Please try again.';
        } finally {
            this.submitting = false;
            // Either way stock just changed or proved stale, so every page's numbers need refreshing.
            Alpine.store('catalog').load();
        }
    },
}));

Alpine.data('brreadBuilder', () => ({
    loading: true,
    selectedBread: null,
    selectedFlavor: null,
    selectedToppings: [],
    creationQuantity: 1,
    justAdded: false,
    justAddedTimer: null,
    errorMessage: '',

    // Undo/redo: a linear stack of {bread, flavor, toppings} id-snapshots, with historyIndex
    // pointing at the current one. Every mutating action pushes a new entry (discarding any
    // redo branch first); restoreSnapshot() never pushes, so undo/redo never loops back on itself.
    history: [],
    historyIndex: -1,

    tabs: [
        { key: 'bread', label: 'Choose Base' },
        { key: 'ice_cream', label: 'Select Flavor' },
        { key: 'topping', label: 'Add Toppings' },
    ],

    // The one step the phone-width stepper shows. Desktop shows all four instead.
    mobileStep: 'bread',

    get presets() {
        return Alpine.store('catalog').signatures;
    },

    get components() {
        return Alpine.store('catalog').components;
    },

    async init() {
        this.$watch('guidedStep', (step) => {
            this.mobileStep = step;
        });

        // Selections hold their own copy of each component, so re-read them when stock changes.
        window.addEventListener('catalog-updated', () => {
            this.restoreSnapshot(this.snapshotState());
            this.clampQuantity();
        });

        await Alpine.store('catalog').load();

        if (Alpine.store('catalog').failed) {
            this.errorMessage = 'Failed to load ingredients. Please refresh the page.';
        }

        this.loading = false;
        this.pushHistory();
        this.mountStudio3d();
    },

    // 3D preview. three.js is a separate chunk fetched only here, so the other pages stay light.
    // If it cannot start (no WebGL, chunk failed to load) studio3dReady stays false and the
    // flat photo stack in the template keeps working.
    studio3dReady: false,
    studio3dTouched: false,

    get studio3dState() {
        return {
            bread: this.selectedBread?.name ?? null,
            flavor: this.selectedFlavor?.name ?? null,
            flavorColor: this.selectedFlavor?.hex_color ?? null,
            sauce: this.hasChocolateSauce,
            sprinkles: this.hasSprinkles,
            marshmallows: this.hasTopping('Marshmallow Mini'),
            gummies: this.hasTopping('Yupi Gummy Bear Mini'),
            exploded: this.explodedView,
        };
    },

    async mountStudio3d() {
        const host = this.$refs?.studio3d;
        if (!host || studio3d) return;

        try {
            const { createStudio3D, supportsWebGL } = await import('./studio3d.js');
            if (!supportsWebGL()) return;

            let flavorOverrides = {};
            try {
                flavorOverrides = JSON.parse(host.dataset.flavorOverrides || '{}');
            } catch {
                /* malformed list: use the generated textures */
            }
            studio3d = createStudio3D(host, {
                flavorOverrides,
                reducedMotion: prefersReducedMotion(),
                onInteract: () => (this.studio3dTouched = true),
            });
            await studio3d.setState(this.studio3dState);
            this.studio3dReady = true;

            Alpine.effect(() => {
                const state = this.studio3dState;
                studio3d?.setState(state).catch(() => {});
            });
        } catch (error) {
            console.warn('3D preview unavailable, using the flat preview instead.', error);
            studio3d?.dispose();
            studio3d = null;
            this.studio3dReady = false;
        }
    },

    studio3dZoom(factor) {
        studio3d?.zoomBy(factor);
    },

    studio3dResetView() {
        studio3d?.resetView();
    },

    snapshotState() {
        return {
            bread: this.selectedBread?.id ?? null,
            flavor: this.selectedFlavor?.id ?? null,
            toppings: this.selectedToppings.map((t) => t.id),
        };
    },

    pushHistory() {
        const snapshot = this.snapshotState();
        const current = this.history[this.historyIndex];

        // Re-picking what is already picked changes nothing, and an entry for it would make Undo look broken.
        if (current && JSON.stringify(current) === JSON.stringify(snapshot)) return;

        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(snapshot);
        this.historyIndex = this.history.length - 1;
    },

    restoreSnapshot(snap) {
        this.selectedBread = snap.bread ? (this.components.bread.find((c) => c.id === snap.bread) ?? null) : null;
        this.selectedFlavor = snap.flavor ? (this.components.ice_cream.find((c) => c.id === snap.flavor) ?? null) : null;
        this.selectedToppings = snap.toppings
            .map((id) => this.components.topping.find((c) => c.id === id))
            .filter(Boolean);
    },

    get canUndo() {
        return this.historyIndex > 0;
    },

    get canRedo() {
        return this.historyIndex < this.history.length - 1;
    },

    undo() {
        if (!this.canUndo) return;
        this.historyIndex--;
        this.restoreSnapshot(this.history[this.historyIndex]);
        this.clampQuantity();
    },

    redo() {
        if (!this.canRedo) return;
        this.historyIndex++;
        this.restoreSnapshot(this.history[this.historyIndex]);
        this.clampQuantity();
    },

    get flavorLocked() {
        return !this.selectedBread;
    },

    get toppingLocked() {
        return !this.selectedBread || !this.selectedFlavor;
    },

    isPresetUnavailable(preset) {
        const creation = Alpine.store('catalog').creationFromPreset(preset);
        return !creation || Alpine.store('cart').unitsAvailable(creation) <= 0;
    },

    applyPreset(preset, event) {
        if (this.isPresetUnavailable(preset)) return;

        const { bread, flavor, toppings } = Alpine.store('catalog').creationFromPreset(preset);

        const btn = event?.currentTarget;
        const target = this.$refs?.stackPreview;
        if (btn && target) {
            window.__brreadFx?.flyClone({
                sourceEl: btn,
                targetEl: target,
                imageSrc: ingredientImage(flavor),
                onLand: () => window.__brreadFx?.pop(target, 'pop-gentle'),
            });
        }

        this.selectedBread = bread;
        this.selectedFlavor = flavor;
        this.selectedToppings = toppings;
        this.errorMessage = '';
        this.clampQuantity();
        this.pushHistory();
    },

    presetSwatch(preset) {
        return this.components.ice_cream.find((c) => c.name === preset.flavor)?.hex_color ?? '#e8e2de';
    },

    isActivePreset(preset) {
        return (
            this.selectedBread?.name === preset.bread &&
            this.selectedFlavor?.name === preset.flavor &&
            this.selectedToppings.length === preset.toppings.length &&
            preset.toppings.every((name) => this.selectedToppings.some((t) => t.name === name))
        );
    },

    explodedView: false,
    toggleExplodedView() {
        this.explodedView = !this.explodedView;
    },

    resetStage() {
        this.selectedBread = null;
        this.selectedFlavor = null;
        this.selectedToppings = [];
        this.creationQuantity = 1;
        this.explodedView = false;
        this.errorMessage = '';
        this.pushHistory();
    },

    selectComponent(component, event) {
        // Removing an already-selected topping (stock stays put) is always allowed;
        // only picking a NEW out-of-stock item is blocked.
        const alreadyChosen = component.category === 'topping' && this.selectedToppings.some((t) => t.id === component.id);
        if (component.stock_quantity <= 0 && !alreadyChosen) return;

        // Sequential gating: a flavor needs a bread first, a topping needs both.
        // The buttons are already :disabled for this, but guard here too in case
        // something else ever calls selectComponent() directly.
        if (component.category === 'ice_cream' && this.flavorLocked) return;
        if (component.category === 'topping' && this.toppingLocked) return;

        const btn = event?.currentTarget;
        const target = this.$refs?.stackPreview;
        if (btn && target) {
            window.__brreadFx?.flyClone({
                sourceEl: btn,
                targetEl: target,
                imageSrc: ingredientImage(component),
                onLand: () => window.__brreadFx?.pop(target, 'pop-gentle'),
            });
        }

        if (component.category === 'bread') {
            this.selectedBread = component;
        } else if (component.category === 'ice_cream') {
            this.selectedFlavor = component;
        } else {
            const index = this.selectedToppings.findIndex((t) => t.id === component.id);
            if (index > -1) {
                this.selectedToppings.splice(index, 1);
            } else {
                this.selectedToppings.push(component);
            }
        }

        this.errorMessage = '';
        this.clampQuantity();
        this.pushHistory();
    },

    get breadImage() {
        return ingredientImage(this.selectedBread);
    },

    get flavorImage() {
        return ingredientImage(this.selectedFlavor);
    },

    toppingImage(name) {
        return INGREDIENT_IMAGES[name] ?? null;
    },

    iconFor(name) {
        return ingredientIcon(name);
    },

    // Per-icon class, so each topping gets its own pick animation (see .ing-icon-- in app.css).
    iconClass(name) {
        const id = ingredientIcon(name);
        return id ? `ing-icon--${id.slice(4)}` : '';
    },

    hasTopping(name) {
        return this.selectedToppings.some((t) => t.name === name);
    },

    get hasSprinkles() {
        return this.hasTopping('Rainbow Sprinkles');
    },

    get hasChocolateSauce() {
        return this.selectedToppings.some((t) => t.name === 'Chocolate Sauce');
    },

    isStepDone(tabKey) {
        if (tabKey === 'bread') return !!this.selectedBread;
        if (tabKey === 'ice_cream') return !!this.selectedFlavor;
        if (tabKey === 'cart') return false;
        // Toppings are optional, so this step is satisfied as soon as it can be reached.
        return !this.toppingLocked;
    },

    isStepLocked(tabKey) {
        if (tabKey === 'ice_cream') return this.flavorLocked;
        if (tabKey === 'topping' || tabKey === 'cart') return this.toppingLocked;
        return false;
    },

    // The step the customer should act on next: it follows their progress, not their clicks.
    get currentStep() {
        if (!this.selectedBread) return 'bread';
        if (!this.selectedFlavor) return 'ice_cream';
        return 'cart';
    },

    goToStep(tabKey) {
        if (this.isStepLocked(tabKey)) return;
        const isPhoneWidth = window.matchMedia('(max-width: 767px)').matches;
        document.getElementById(`step-${tabKey}`)?.scrollIntoView({
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            // A card can be taller than the space left under the sticky stepper on a phone.
            block: isPhoneWidth ? 'start' : 'center',
        });
    },

    get steps() {
        return [...this.tabs, { key: 'cart', label: 'Add to Cart' }];
    },

    // Unlike currentStep this stops at Toppings, so a phone walks through all four steps in order.
    get guidedStep() {
        if (!this.selectedBread) return 'bread';
        if (!this.selectedFlavor) return 'ice_cream';
        return 'topping';
    },

    get mobileStepIndex() {
        return this.steps.findIndex((step) => step.key === this.mobileStep);
    },

    get canGoToPreviousStep() {
        return this.mobileStepIndex > 0;
    },

    get canGoToNextStep() {
        const next = this.steps[this.mobileStepIndex + 1];
        return !!next && !this.isStepLocked(next.key);
    },

    moveMobileStep(offset) {
        const target = this.steps[this.mobileStepIndex + offset];
        if (!target || this.isStepLocked(target.key)) return;
        this.mobileStep = target.key;
        this.goToStep(target.key);
    },

    isSelected(component) {
        if (component.category === 'bread') {
            return this.selectedBread?.id === component.id;
        }
        if (component.category === 'ice_cream') {
            return this.selectedFlavor?.id === component.id;
        }
        return this.selectedToppings.some((t) => t.id === component.id);
    },

    get unitPrice() {
        let total = 0;
        if (this.selectedBread) total += parseFloat(this.selectedBread.price);
        if (this.selectedFlavor) total += parseFloat(this.selectedFlavor.price);
        this.selectedToppings.forEach((t) => (total += parseFloat(t.price)));
        return total;
    },

    get totalPrice() {
        return this.unitPrice * this.creationQuantity;
    },

    formatPrice,

    get currentCreation() {
        if (!this.selectedBread || !this.selectedFlavor) return null;
        return { bread: this.selectedBread, flavor: this.selectedFlavor, toppings: this.selectedToppings };
    },

    get maxQuantity() {
        const creation = this.currentCreation;
        return creation ? Alpine.store('cart').unitsAvailable(creation) : MAX_PER_CREATION;
    },

    get canAddToCart() {
        return !!this.currentCreation && this.maxQuantity > 0;
    },

    // Explains a disabled Add to Cart once the customer has made a full selection.
    get stockNotice() {
        const creation = this.currentCreation;
        if (!creation) return '';
        if (this.maxQuantity <= 0) return 'Your cart already holds the last of these ingredients. Change your picks or check out first.';
        if (this.maxQuantity < MAX_PER_CREATION && this.creationQuantity >= this.maxQuantity) return `Only ${this.maxQuantity} more can be made with today's stock.`;
        return '';
    },

    clampQuantity() {
        this.creationQuantity = Math.max(1, Math.min(this.creationQuantity, this.maxQuantity));
    },

    incrementQty() {
        this.creationQuantity = Math.min(Math.max(1, this.maxQuantity), this.creationQuantity + 1);
    },

    decrementQty() {
        this.creationQuantity = Math.max(1, this.creationQuantity - 1);
    },

    addToCart(event) {
        this.errorMessage = '';

        if (!this.canAddToCart) {
            this.errorMessage = 'Please pick an in-stock base and flavor first.';
            return;
        }

        const added = Alpine.store('cart').add({
            bread: this.selectedBread,
            flavor: this.selectedFlavor,
            toppings: this.selectedToppings,
            quantity: this.creationQuantity,
        });

        if (added === 0) {
            this.errorMessage = 'Your cart is full. Check out before adding more.';
            return;
        }

        const btn = event?.currentTarget;
        const target = document.getElementById('nav-cart-badge');
        if (btn && target) {
            window.__brreadFx?.flyClone({
                sourceEl: btn,
                targetEl: target,
                imageSrc: this.flavorImage,
                onLand: () => window.__brreadFx?.pop(target),
            });
        }

        this.justAdded = true;
        clearTimeout(this.justAddedTimer);
        this.justAddedTimer = setTimeout(() => (this.justAdded = false), 2400);

        // Reset the stage for the next creation — the cart itself keeps what was just added.
        this.selectedBread = null;
        this.selectedFlavor = null;
        this.selectedToppings = [];
        this.creationQuantity = 1;
        this.explodedView = false;
        this.pushHistory();
    },
}));

// The quick-add cards on the home page sell the same signature combos as the Studio.
// Home hero: the same 3D sandwich the Studio uses, with a chip per flavor to flip through.
// Kept outside Alpine state for the same reason as studio3d above. The photo stays as the
// fallback whenever the scene cannot start (no WebGL, data saver on, chunk failed to load).
let hero3d = null;

// The chips come from the server (App\Support\HomeMenu): one per active flavor, dressed like a signature combo.
Alpine.data('heroSandwich', (combos = []) => ({
    combos,
    active: combos[0]?.key ?? '',
    ready: false,
    touched: false,
    exploded: false,

    get activeCombo() {
        return this.combos.find((combo) => combo.key === this.active) ?? { summary: '', state: {} };
    },

    init() {
        const start = () => this.mount();
        if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 1500 });
        else setTimeout(start, 300);
    },

    stateFor(key) {
        return { ...this.combos.find((combo) => combo.key === key).state, exploded: this.exploded };
    },

    async mount() {
        const host = this.$refs?.stage;
        if (!host || hero3d || !this.combos.length || navigator.connection?.saveData) return;

        try {
            const { createStudio3D, supportsWebGL } = await import('./studio3d.js');
            if (!supportsWebGL()) return;

            hero3d = createStudio3D(host, {
                reducedMotion: prefersReducedMotion(),
                onInteract: () => (this.touched = true),
            });
            await hero3d.setState(this.stateFor(this.active));
            this.ready = true;
        } catch (error) {
            console.warn('Hero 3D preview unavailable, keeping the photo.', error);
            hero3d?.dispose();
            hero3d = null;
            this.ready = false;
        }
    },

    pick(key) {
        this.active = key;
        hero3d?.setState(this.stateFor(key)).catch(() => {});
    },

    toggleExploded() {
        this.exploded = !this.exploded;
        hero3d?.setState(this.stateFor(this.active)).catch(() => {});
    },
}));

Alpine.data('signatureMenu', (products) => ({
    activeFilter: 'all',
    toastVisible: false,
    toastMessage: '',
    toastFailed: false,
    toastTimer: null,
    products,

    init() {
        Alpine.store('catalog').load();
        const start = () => this.drawThumbs();
        if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 2500 });
        else setTimeout(start, 600);
    },

    // A card with no real photo gets a still of its own sandwich (right bread, flavor and toppings), drawn
    // with the same 3D scene the Studio uses. One hidden scene is reused for every card, then thrown away.
    async drawThumbs() {
        const pending = this.products.filter((product) => !product.photo && product.state && !product.thumb);
        if (!pending.length || navigator.connection?.saveData) return;

        let scene = null;
        const host = document.createElement('div');
        host.setAttribute('aria-hidden', 'true');
        host.style.cssText = 'position:fixed;left:-9999px;top:0;width:640px;height:440px;pointer-events:none';
        document.body.appendChild(host);

        try {
            const { createStudio3D, supportsWebGL } = await import('./studio3d.js');
            if (!supportsWebGL()) return;

            scene = createStudio3D(host, { reducedMotion: true, autoRotate: false, interactive: false });
            for (const product of pending) {
                await scene.setState({ ...product.state, exploded: false });
                scene.resetView();
                scene.zoomBy(1.1);
                product.thumb = scene.snapshot();
            }
        } catch (error) {
            console.warn('Combo thumbnails unavailable, keeping the flavor pictures.', error);
        } finally {
            scene?.dispose();
            host.remove();
        }
    },

    get filteredProducts() {
        if (this.activeFilter === 'all') return this.products;
        return this.products.filter((p) => p.category.includes(this.activeFilter));
    },

    creationFor(product) {
        return Alpine.store('catalog').creationFromPreset(Alpine.store('catalog').presetNamed(product.name));
    },

    isSoldOut(product) {
        const catalog = Alpine.store('catalog');
        if (!catalog.loaded) return false;
        const creation = this.creationFor(product);
        return !creation || Alpine.store('cart').unitsAvailable(creation) <= 0;
    },

    priceOf(product) {
        const creation = this.creationFor(product);
        if (!creation) return product.price;
        return creationComponents(creation).reduce((sum, component) => sum + parseFloat(component.price), 0);
    },

    showToast(message, failed = false) {
        this.toastMessage = message;
        this.toastFailed = failed;
        this.toastVisible = true;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toastVisible = false), 2400);
    },

    async addToCart(event, product) {
        const btn = event.currentTarget;
        const img = btn.closest('.product-card')?.querySelector('img');

        await Alpine.store('catalog').load();

        const creation = this.creationFor(product);
        const added = creation ? Alpine.store('cart').add({ ...creation, quantity: 1 }) : 0;

        if (added === 0) {
            this.showToast(Alpine.store('catalog').failed ? 'Could not reach the kitchen. Try again.' : 'Not enough stock for another one.', true);
            return;
        }

        const badge = document.getElementById('nav-cart-badge');
        window.__brreadFx.flyClone({
            sourceEl: btn,
            targetEl: badge,
            imageSrc: img?.src,
            onLand: () => {
                window.__brreadFx.pop(badge);
                this.showToast('Added to cart');
            },
        });
    },

    formatPrice,
}));

window.Alpine = Alpine;
registerOrderFx(Alpine);
registerStaffBatch(Alpine);
registerGridNav(Alpine);

// The pile of polaroids on the Contact page. Drag the top one off and let go (or tap it, or press the right arrow)
// and it sails off, then tucks in at the back of the pile; the left arrow brings the last one back on top.
Alpine.data('polaroidPile', (count) => ({
    order: Array.from({ length: count }, (_, i) => i),
    tilts: [6, -5, 3, -7, 4, -3],
    hold: null, // { i, x0, y0, dx, dy, t, vx }
    flying: -1,
    flyDir: 1,
    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

    depth(i) {
        return this.order.indexOf(i);
    },

    cardClass(i) {
        return {
            'is-top': this.depth(i) === 0,
            'is-held': this.hold?.i === i,
            'is-flying': this.flying === i,
        };
    },

    cardStyle(i) {
        const d = this.depth(i);
        const tilt = this.tilts[i % this.tilts.length];
        let x = d * 7;
        let y = d * -5;
        let rot = tilt * (d === 0 ? 1 : 0.8);
        let scale = 1 - Math.min(d, 4) * 0.025;
        if (this.hold?.i === i) {
            x += this.hold.dx;
            y += this.hold.dy;
            rot += this.hold.dx * 0.07;
            scale = 1.04;
        } else if (this.flying === i) {
            x = this.flyDir * 420;
            y = -60;
            rot = tilt + this.flyDir * 28;
        }
        return `z-index:${count - d + (this.flying === i ? count : 0)};transform:translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) rotate(${rot.toFixed(2)}deg) scale(${scale.toFixed(3)});opacity:${d > 4 ? 0 : 1}`;
    },

    grab(event, i) {
        if (this.depth(i) !== 0 || this.flying !== -1 || event.button > 0) return;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        this.hold = { i, x0: event.clientX, y0: event.clientY, dx: 0, dy: 0, t: performance.now(), vx: 0, id: event.pointerId };
    },

    drag(event) {
        if (!this.hold || event.pointerId !== this.hold.id) return;
        const now = performance.now();
        const dx = event.clientX - this.hold.x0;
        const dy = event.clientY - this.hold.y0;
        this.hold.vx = (dx - this.hold.dx) / Math.max(8, now - this.hold.t);
        this.hold.t = now;
        this.hold.dx = dx;
        this.hold.dy = dy;
    },

    drop(event) {
        if (!this.hold || event.pointerId !== this.hold.id) return;
        const { dx, dy, vx } = this.hold;
        this.hold = null;
        const moved = Math.hypot(dx, dy);
        // a tap, a long drag or a quick flick sends it off; a short nudge springs back
        if (moved < 6 || Math.abs(dx) > 90 || Math.abs(vx) > 0.6) this.next(moved < 6 ? 1 : Math.sign(dx || vx) || 1);
    },

    next(dir = 1) {
        if (this.flying !== -1 || count < 2) return;
        this.flyDir = dir;
        this.flying = this.order[0];
        setTimeout(() => {
            this.order.push(this.order.shift());
            this.flying = -1;
        }, this.reduced ? 0 : 260);
    },

    previous() {
        if (this.flying !== -1 || count < 2) return;
        this.order.unshift(this.order.pop());
    },
}));

Alpine.start();

// Entrance reveals: fade + rise once an element scrolls into view, staggered within each group.
// Tooltips: any element with data-tip="…" gets one. A single floating node is reused, placed
// above the target (below when there is no room) and kept inside the viewport. Shown on mouse
// hover after a short delay and on keyboard focus; touch never triggers it, the label or
// aria-label already covers that case.
function initTooltips() {
    const tip = document.createElement('div');
    tip.className = 'brread-tip';
    tip.setAttribute('role', 'tooltip');
    tip.id = 'brread-tip';
    document.body.appendChild(tip);

    let target = null;
    let timer = null;

    const place = () => {
        if (!target) return;
        const rect = target.getBoundingClientRect();
        const gap = 8;
        const margin = 8;
        const width = tip.offsetWidth;
        const height = tip.offsetHeight;
        const centre = rect.left + rect.width / 2;
        const left = Math.min(Math.max(centre - width / 2, margin), window.innerWidth - width - margin);
        const above = rect.top - height - gap >= margin;
        const top = above ? rect.top - height - gap : rect.bottom + gap;

        tip.style.left = `${Math.round(left)}px`;
        tip.style.top = `${Math.round(top)}px`;
        tip.style.setProperty('--arrow-x', `${Math.round(Math.min(Math.max(centre - left, 12), width - 12))}px`);
        tip.dataset.side = above ? 'top' : 'bottom';
    };

    const hide = () => {
        clearTimeout(timer);
        if (target) target.removeAttribute('aria-describedby');
        target = null;
        delete tip.dataset.show;
    };

    const show = (el, delay) => {
        const text = el.getAttribute('data-tip');
        if (!text || el === target) return;
        hide();
        target = el;
        timer = setTimeout(() => {
            if (target !== el || !el.isConnected) return;
            tip.textContent = el.getAttribute('data-tip');
            el.setAttribute('aria-describedby', tip.id);
            place();
            tip.dataset.show = 'true';
        }, delay);
    };

    const tipOwner = (node) => (node instanceof Element ? node.closest('[data-tip]') : null);

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') return;
        const el = tipOwner(event.target);
        if (el) show(el, 350);
    });
    document.addEventListener('pointerout', (event) => {
        if (!target || event.pointerType !== 'mouse') return;
        if (!target.contains(event.relatedTarget)) hide();
    });
    document.addEventListener('focusin', (event) => {
        const el = tipOwner(event.target);
        if (el && event.target.matches(':focus-visible')) show(el, 100);
    });
    document.addEventListener('focusout', hide);
    document.addEventListener('pointerdown', hide);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && hide());
    window.addEventListener('scroll', hide, { passive: true, capture: true });
    window.addEventListener('resize', hide);
}

initTooltips();

// Custom scrollbar for mouse users: a little ice cream sandwich rides down the right edge and
// leaves a melted strawberry trail behind it. Touch devices and reduced-data setups keep the native scrollbar; keyboard
// and wheel scrolling are untouched because the page itself still scrolls normally.
function initScoopbar() {
    if (!document.getElementById('site-footer')) return;
    // Mouse: always there, drag anywhere on the rail. Touch: shows while scrolling, drag the sandwich.
    const touch = !window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    const bar = document.createElement('div');
    bar.className = touch ? 'scoopbar scoopbar--touch' : 'scoopbar';
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML = `
        <div class="scoopbar-melt"></div>
        <div class="scoopbar-thumb" ${touch ? '' : 'data-tip="Drag me to scroll"'}>
            <svg viewBox="0 0 40 56" width="40" height="56" fill="none">
                <rect x="6" y="15" width="28" height="26" rx="5" fill="#f58fa6"/>
                <path d="M6 33v3a5 5 0 0 0 5 5h3v4a3 3 0 0 0 6 0v-4h9a5 5 0 0 0 5-5v-3z" fill="#f58fa6"/>
                <g stroke-width="2.4" stroke-linecap="round">
                    <path d="M11 23l3 2" stroke="#fff3c4"/><path d="M19 21l3-1" stroke="#9cc9cf"/><path d="M27 24l2 2" stroke="#fff"/>
                    <path d="M13 31l2-2" stroke="#fff"/><path d="M23 30l3 1" stroke="#f3c969"/>
                </g>
                <rect x="2" y="2" width="36" height="15" rx="7" fill="#f6e6cb" stroke="#d39a52" stroke-width="3"/>
                <rect x="2" y="37" width="36" height="15" rx="7" fill="#f6e6cb" stroke="#d39a52" stroke-width="3"/>
                <path d="M9 8h9" stroke="#fff" stroke-opacity=".8" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
        </div>`;
    document.body.appendChild(bar);
    document.documentElement.classList.add('has-scoopbar');

    const thumb = bar.querySelector('.scoopbar-thumb');
    const melt = bar.querySelector('.scoopbar-melt');
    const THUMB = touch ? 42 : 56;
    let awake = null;
    const wake = () => {
        if (!touch) return;
        bar.classList.add('is-awake');
        clearTimeout(awake);
        awake = setTimeout(() => bar.classList.remove('is-awake'), 1600);
    };
    let max = 0;
    let travel = 0;
    let queued = false;
    let grabOffset = null;
    let idle = null;

    const scrollToY = (y) => {
        if (window.__lenis) window.__lenis.scrollTo(y, { immediate: true, force: true });
        else window.scrollTo(0, y);
    };

    const render = () => {
        queued = false;
        max = document.documentElement.scrollHeight - window.innerHeight;
        bar.classList.toggle('is-hidden', max <= 8);
        travel = bar.clientHeight - THUMB;
        const y = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) * travel : 0;
        thumb.style.transform = `translate3d(0, ${y.toFixed(1)}px, 0)`;
        melt.style.height = `${(y + 12).toFixed(1)}px`;
    };
    const queue = () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(render);
        }
    };

    window.addEventListener(
        'scroll',
        () => {
            queue();
            wake();
            bar.classList.add('is-moving');
            clearTimeout(idle);
            idle = setTimeout(() => bar.classList.remove('is-moving'), 180);
        },
        { passive: true },
    );
    window.addEventListener('resize', queue);
    new ResizeObserver(queue).observe(document.body);

    const dragTo = (clientY) => {
        const top = bar.getBoundingClientRect().top;
        const y = Math.min(travel, Math.max(0, clientY - top - grabOffset));
        scrollToY(travel > 0 ? (y / travel) * max : 0);
    };
    bar.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        const rect = thumb.getBoundingClientRect();
        const onThumb = event.clientY >= rect.top && event.clientY <= rect.bottom;
        grabOffset = onThumb ? event.clientY - rect.top : THUMB / 2;
        bar.setPointerCapture(event.pointerId);
        bar.classList.add('is-dragging');
        dragTo(event.clientY);
    });
    bar.addEventListener('pointermove', (event) => grabOffset !== null && dragTo(event.clientY));
    const release = () => {
        grabOffset = null;
        bar.classList.remove('is-dragging');
        wake();
    };
    bar.addEventListener('pointerup', release);
    bar.addEventListener('pointercancel', release);

    render();
    wake();
}

initScoopbar();

// Looping decoration (sprinkle wallpaper, breathing drips, falling droplets) only animates
// while its section is near the viewport.
function initOffscreenPausing() {
    const targets = document.querySelectorAll('.doodle, .drip');
    if (!targets.length || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver(
        (entries) => entries.forEach((entry) => entry.target.classList.toggle('is-off', !entry.isIntersecting)),
        { rootMargin: '200px 0px' },
    );
    targets.forEach((el) => observer.observe(el));
}

initOffscreenPausing();

// About page: Lenis smooth scroll and the scroll-driven chapters, loaded only there.
const aboutRoot = document.querySelector('[data-about]');
if (aboutRoot) {
    import('./about.js')
        .then(({ initAbout }) => {
            const lenis = initAbout(aboutRoot, { reducedMotion: prefersReducedMotion() });
            // The cart drawer scrolls on its own; hold the page still while it is open.
            if (!lenis) return;
            window.__lenis = lenis; // the custom scrollbar drags through Lenis when it is running
            Alpine.effect(() => (Alpine.store('cart').open ? lenis.stop() : lenis.start()));
        })
        .catch((error) => console.warn('About page effects unavailable.', error));
}

// The entry intro is driven entirely by CSS (it leaves even if this script never runs);
// this only clears the node out of the DOM afterwards. The quick variant (html.quick-intro) gets a 1.6s hard cap.
const intro = document.getElementById('brread-intro');
if (intro) {
    const done = () => intro.remove();
    const root = document.documentElement;
    if (root.classList.contains('no-intro')) done();
    else {
        intro.addEventListener('animationend', (event) => event.animationName === 'intro-out' && done());
        setTimeout(done, root.classList.contains('quick-intro') ? 1600 : 4000);
    }
}

// Back/forward cache: a page restored from it must never show a stale loader or carry a stale flag.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.getElementById('brread-intro')?.remove();
    try { sessionStorage.removeItem('brreadHomeNav'); } catch (e) { /* storage unavailable */ }
});

// Logo or Home link in the public navbar (desktop and mobile menu): flag the next page load so Home plays the short
// intro. The browser still navigates normally; plain left clicks only, and never when already on Home.
document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('.site-nav a[href]') : null;
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
    let url;
    try { url = new URL(link.href, location.href); } catch (e) { return; }
    if (url.origin !== location.origin || url.pathname !== '/' || location.pathname === '/') return;
    try {
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) sessionStorage.setItem('brreadHomeNav', String(Date.now()));
    } catch (e) { /* storage unavailable: no loader, navigation still works */ }
});

// Watches for elements added after the initial paint too (Alpine x-for lists populated by an
// async axios call render their [data-reveal] nodes well after DOMContentLoaded).
function initReveals() {
    const supportsIO = 'IntersectionObserver' in window;
    const seen = new WeakSet();

    const observer = supportsIO
        ? new IntersectionObserver(
              (entries) => {
                  entries.forEach((entry) => {
                      if (entry.isIntersecting) {
                          entry.target.classList.add('is-revealed');
                          observer.unobserve(entry.target);
                      }
                  });
              },
              { threshold: 0.15, rootMargin: '0px 0px -60px 0px' },
          )
        : null;

    const stageGroup = (group) => {
        group.querySelectorAll('[data-reveal]').forEach((el, i) => el.style.setProperty('--reveal-delay', `${i * 80}ms`));
    };

    const watch = (el) => {
        if (seen.has(el)) return;
        seen.add(el);
        if (!supportsIO) {
            el.classList.add('is-revealed');
            return;
        }
        observer.observe(el);
    };

    const scan = (root) => {
        root.querySelectorAll?.('[data-reveal-group]').forEach(stageGroup);
        root.querySelectorAll?.('[data-reveal]').forEach(watch);
        if (root.matches?.('[data-reveal]')) watch(root);
    };

    scan(document);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) scan(node);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveals);
} else {
    initReveals();
}
