import Alpine from 'alpinejs';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export const INGREDIENT_IMAGES = {
    'Plain White Bread': '/img/ingredients/bread-white.png',
    'Whole Wheat Bread': '/img/ingredients/bread-wheat.png',
    Vanilla: '/img/ingredients/icecream-vanilla.png',
    Strawberry: '/img/ingredients/icecream-strawberry.png',
    Chocolate: '/img/ingredients/icecream-chocolate.png',
    'Chocolate Sauce': '/img/ingredients/topping-chocolate-sauce.png',
    'Rainbow Sprinkles': '/img/ingredients/topping-sprinkles.png',
};
window.INGREDIENT_IMAGES = INGREDIENT_IMAGES;

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Count-up for rare/informational numbers (dashboard stats) — never for data a user
// reads precisely in real time, and instant under reduced motion.
function animateCounter(el, target, { duration = 900, formatter = (n) => Math.round(n).toLocaleString('id-ID') } = {}) {
    if (!el) return;
    if (prefersReducedMotion()) {
        el.textContent = formatter(target);
        return;
    }
    const start = performance.now();
    const easeOut = (t) => 1 - (1 - t) ** 3;
    function tick(now) {
        const progress = Math.min(1, (now - start) / duration);
        el.textContent = formatter(target * easeOut(progress));
        if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
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

window.__brreadFx = { flyClone, bounceBadge: pop, pop, animateCounter };

function formatPrice(n) {
    return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}

function loadCart() {
    try {
        const raw = localStorage.getItem('brreadCart');
        const items = raw ? JSON.parse(raw) : [];
        return Array.isArray(items) ? items : [];
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

// One cart shared across every page (navbar badge, builder, checkout drawer) since there's
// no customer auth to key a server-side cart to.
Alpine.store('cart', {
    items: loadCart(),
    open: false,

    add({ bread, flavor, toppings, quantity }) {
        this.items.push({
            uid: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            bread: { id: bread.id, name: bread.name, price: bread.price },
            flavor: { id: flavor.id, name: flavor.name, price: flavor.price },
            toppings: toppings.map((t) => ({ id: t.id, name: t.name, price: t.price })),
            quantity,
        });
        saveCart(this.items);
    },

    remove(uid) {
        this.items = this.items.filter((i) => i.uid !== uid);
        saveCart(this.items);
    },

    setQuantity(uid, quantity) {
        const item = this.items.find((i) => i.uid === uid);
        if (item) item.quantity = Math.min(20, Math.max(1, quantity));
        saveCart(this.items);
    },

    clear() {
        this.items = [];
        saveCart(this.items);
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

Alpine.data('cartDrawer', () => ({
    customerName: '',
    customerPhone: '',
    submitting: false,
    errorMessage: '',
    orderSuccess: null,

    formatPrice,

    async checkout() {
        this.errorMessage = '';

        if (Alpine.store('cart').items.length === 0) {
            this.errorMessage = 'Your cart is empty — add a creation first.';
            return;
        }
        if (!this.customerName || !this.customerPhone) {
            this.errorMessage = 'Please fill in your name and phone number.';
            return;
        }

        this.submitting = true;

        try {
            const res = await axios.post('/api/orders', {
                customer_name: this.customerName,
                customer_phone: this.customerPhone,
                items: Alpine.store('cart').toApiItems(),
            });
            this.orderSuccess = res.data.order_number;
            Alpine.store('cart').clear();
        } catch (e) {
            const errors = e.response?.data?.errors ?? {};
            this.errorMessage =
                errors.customer_name?.[0] ??
                errors.customer_phone?.[0] ??
                errors.items?.[0] ??
                e.response?.data?.message ??
                'Something went wrong. Please try again.';
        } finally {
            this.submitting = false;
        }
    },

    closeAndReset() {
        Alpine.store('cart').open = false;
        this.orderSuccess = null;
        this.customerName = '';
        this.customerPhone = '';
        this.errorMessage = '';
    },
}));

Alpine.data('brreadBuilder', () => ({
    loading: true,
    activeTab: 'bread',
    components: { bread: [], ice_cream: [], topping: [] },
    selectedBread: null,
    selectedFlavor: null,
    selectedToppings: [],
    creationQuantity: 1,
    justAdded: false,
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

    async init() {
        try {
            const res = await axios.get('/api/components');
            this.components = res.data;
        } catch (e) {
            this.errorMessage = 'Failed to load ingredients. Please refresh the page.';
        } finally {
            this.loading = false;
            this.pushHistory();
        }
    },

    snapshotState() {
        return {
            bread: this.selectedBread?.id ?? null,
            flavor: this.selectedFlavor?.id ?? null,
            toppings: this.selectedToppings.map((t) => t.id),
        };
    },

    pushHistory() {
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(this.snapshotState());
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
    },

    redo() {
        if (!this.canRedo) return;
        this.historyIndex++;
        this.restoreSnapshot(this.history[this.historyIndex]);
    },

    get flavorLocked() {
        return !this.selectedBread;
    },

    get toppingLocked() {
        return !this.selectedBread || !this.selectedFlavor;
    },

    presets: [
        { name: 'Classic Vanilla Slab', bread: 'Plain White Bread', flavor: 'Vanilla', toppings: [] },
        { name: 'Strawberry Sprinkle Stack', bread: 'Plain White Bread', flavor: 'Strawberry', toppings: ['Rainbow Sprinkles'] },
        { name: 'Wholegrain Choco Crunch', bread: 'Whole Wheat Bread', flavor: 'Chocolate', toppings: [] },
        { name: 'Wheat Vanilla Drizzle', bread: 'Whole Wheat Bread', flavor: 'Vanilla', toppings: ['Chocolate Sauce'] },
    ],

    isPresetUnavailable(preset) {
        const bread = this.components.bread.find((c) => c.name === preset.bread);
        const flavor = this.components.ice_cream.find((c) => c.name === preset.flavor);
        return !bread || bread.stock_quantity <= 0 || !flavor || flavor.stock_quantity <= 0;
    },

    applyPreset(preset, event) {
        if (this.isPresetUnavailable(preset)) return;

        const bread = this.components.bread.find((c) => c.name === preset.bread);
        const flavor = this.components.ice_cream.find((c) => c.name === preset.flavor);
        const toppings = this.components.topping.filter((c) => preset.toppings.includes(c.name));

        const btn = event?.currentTarget;
        const target = this.$refs?.stackPreview;
        if (btn && target) {
            window.__brreadFx?.flyClone({
                sourceEl: btn,
                targetEl: target,
                imageSrc: INGREDIENT_IMAGES[flavor?.name],
                onLand: () => window.__brreadFx?.pop(target, 'pop-gentle'),
            });
        }

        if (bread) this.selectedBread = bread;
        if (flavor) this.selectedFlavor = flavor;
        this.selectedToppings = toppings;
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
        this.activeTab = 'bread';
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
                imageSrc: INGREDIENT_IMAGES[component.name],
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

        this.pushHistory();
    },

    get breadImage() {
        return this.selectedBread ? INGREDIENT_IMAGES[this.selectedBread.name] : null;
    },

    get flavorImage() {
        return this.selectedFlavor ? INGREDIENT_IMAGES[this.selectedFlavor.name] : null;
    },

    toppingImage(name) {
        return INGREDIENT_IMAGES[name] ?? null;
    },

    get hasSprinkles() {
        return this.selectedToppings.some((t) => t.name === 'Rainbow Sprinkles');
    },

    get hasChocolateSauce() {
        return this.selectedToppings.some((t) => t.name === 'Chocolate Sauce');
    },

    isStepDone(tabKey) {
        if (tabKey === 'bread') return !!this.selectedBread;
        if (tabKey === 'ice_cream') return !!this.selectedFlavor;
        // Toppings are optional, so this step is "done" once it's reachable — otherwise it
        // would still show as pending even after Step 4 (Add to Cart) shows done, which reads
        // as the stepper skipping ahead of itself.
        return !this.toppingLocked;
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

    get canAddToCart() {
        return (
            this.selectedBread && this.selectedBread.stock_quantity > 0 &&
            this.selectedFlavor && this.selectedFlavor.stock_quantity > 0 &&
            this.selectedToppings.every((t) => t.stock_quantity > 0)
        );
    },

    incrementQty() {
        this.creationQuantity = Math.min(20, this.creationQuantity + 1);
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

        Alpine.store('cart').add({
            bread: this.selectedBread,
            flavor: this.selectedFlavor,
            toppings: this.selectedToppings,
            quantity: this.creationQuantity,
        });

        const btn = event?.currentTarget;
        const target = document.getElementById('nav-cart-icon');
        if (btn && target) {
            window.__brreadFx?.flyClone({
                sourceEl: btn,
                targetEl: target,
                imageSrc: this.flavorImage,
                onLand: () => window.__brreadFx?.pop(target, 'cart-badge-pop'),
            });
        }

        this.justAdded = true;
        setTimeout(() => (this.justAdded = false), 1800);

        // Reset the stage for the next creation — the cart itself keeps what was just added.
        this.selectedBread = null;
        this.selectedFlavor = null;
        this.selectedToppings = [];
        this.creationQuantity = 1;
        this.activeTab = 'bread';
        this.explodedView = false;
        this.pushHistory();
    },
}));

window.Alpine = Alpine;
Alpine.start();

// Entrance reveals: fade + rise once an element scrolls into view, staggered within each group.
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
