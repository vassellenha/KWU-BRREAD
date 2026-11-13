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

// The Studio's three.js scene. Kept at module level, never on the Alpine component:
// Alpine would wrap it in a reactive proxy, which three.js objects do not survive.
let studio3d = null;

const MAX_PER_CREATION = 20;
const MAX_CART_LINES = 20;

const SIGNATURE_PRESETS = [
    { name: 'Classic Vanilla Slab', bread: 'Plain White Bread', flavor: 'Vanilla', toppings: [] },
    { name: 'Strawberry Sprinkle Stack', bread: 'Plain White Bread', flavor: 'Strawberry', toppings: ['Rainbow Sprinkles'] },
    { name: 'Wholegrain Choco Crunch', bread: 'Whole Wheat Bread', flavor: 'Chocolate', toppings: [] },
    { name: 'Wheat Vanilla Drizzle', bread: 'Whole Wheat Bread', flavor: 'Vanilla', toppings: ['Chocolate Sauce'] },
];

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
    loaded: false,
    failed: false,
    pendingLoad: null,

    load() {
        this.pendingLoad ??= axios
            .get('/api/components')
            .then((response) => {
                this.components = response.data;
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
        const bread = this.components.bread.find((c) => c.name === preset.bread);
        const flavor = this.components.ice_cream.find((c) => c.name === preset.flavor);
        const toppings = preset.toppings.map((name) => this.components.topping.find((c) => c.name === name));

        return bread && flavor && toppings.every(Boolean) ? { bread, flavor, toppings } : null;
    },

    presetNamed(name) {
        return SIGNATURE_PRESETS.find((preset) => preset.name === name);
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

Alpine.data('cartDrawer', () => ({
    customerName: '',
    customerPhone: '',
    submitting: false,
    errorMessage: '',
    orderSuccess: null,

    formatPrice,

    init() {
        this.$watch('$store.cart.open', (isOpen) => {
            document.documentElement.classList.toggle('overflow-hidden', isOpen);

            if (isOpen) {
                Alpine.store('catalog').load().then(() => Alpine.store('cart').syncPrices());
                return;
            }

            // A finished order must not linger: reopening shows the cart, not the old receipt.
            this.errorMessage = '';
            if (this.orderSuccess) {
                this.orderSuccess = null;
                this.customerName = '';
                this.customerPhone = '';
            }
        });
    },

    close() {
        Alpine.store('cart').open = false;
    },

    async checkout() {
        const cart = Alpine.store('cart');
        this.errorMessage = '';

        if (cart.items.length === 0) {
            this.errorMessage = 'Your cart is empty — add a creation first.';
            return;
        }
        if (!this.customerName.trim() || !this.customerPhone.trim()) {
            this.errorMessage = 'Please fill in your name and phone number.';
            return;
        }

        this.submitting = true;

        try {
            const res = await axios.post('/api/orders', {
                customer_name: this.customerName,
                customer_phone: this.customerPhone,
                items: cart.toApiItems(),
            });
            this.orderSuccess = res.data.order_number;
            cart.clear();
        } catch (e) {
            const errors = e.response?.data?.errors ?? {};
            this.errorMessage =
                Object.values(errors)[0]?.[0] ??
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

    presets: SIGNATURE_PRESETS,

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
            exploded: this.explodedView,
        };
    },

    async mountStudio3d() {
        const host = this.$refs?.studio3d;
        if (!host || studio3d) return;

        try {
            const { createStudio3D, supportsWebGL } = await import('./studio3d.js');
            if (!supportsWebGL()) return;

            studio3d = createStudio3D(host, {
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
                imageSrc: INGREDIENT_IMAGES[flavor.name],
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

        this.errorMessage = '';
        this.clampQuantity();
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
Alpine.data('signatureMenu', (products) => ({
    activeFilter: 'all',
    toastVisible: false,
    toastMessage: '',
    toastFailed: false,
    toastTimer: null,
    products,

    init() {
        Alpine.store('catalog').load();
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
                this.showToast('Added to Sweet Cart!');
            },
        });
    },

    formatPrice,
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
