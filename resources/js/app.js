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

window.__brreadFx = { flyClone, bounceBadge: pop, pop };

Alpine.data('brreadBuilder', () => ({
    loading: true,
    submitting: false,
    activeTab: 'bread',
    components: { bread: [], ice_cream: [], topping: [] },
    selectedBread: null,
    selectedFlavor: null,
    selectedToppings: [],
    customerName: '',
    customerPhone: '',
    orderSuccess: null,
    errorMessage: '',

    tabs: [
        { key: 'bread', label: 'Choose Base' },
        { key: 'ice_cream', label: 'Select Flavor' },
        { key: 'topping', label: 'Add Toppings' },
    ],

    async init() {
        try {
            const res = await axios.get('/api/components');
            this.components = res.data;

            this.selectedBread =
                this.components.bread.find((c) => c.name === 'Plain White Bread') ?? this.components.bread[0] ?? null;
            this.selectedFlavor =
                this.components.ice_cream.find((c) => c.name === 'Strawberry') ?? this.components.ice_cream[0] ?? null;
        } catch (e) {
            this.errorMessage = 'Failed to load ingredients. Please refresh the page.';
        } finally {
            this.loading = false;
        }
    },

    presets: [
        { name: 'Classic Vanilla Slab', bread: 'Plain White Bread', flavor: 'Vanilla', toppings: [] },
        { name: 'Strawberry Sprinkle Stack', bread: 'Plain White Bread', flavor: 'Strawberry', toppings: ['Rainbow Sprinkles'] },
        { name: 'Wholegrain Choco Crunch', bread: 'Whole Wheat Bread', flavor: 'Chocolate', toppings: [] },
        { name: 'Wheat Vanilla Drizzle', bread: 'Whole Wheat Bread', flavor: 'Vanilla', toppings: ['Chocolate Sauce'] },
    ],

    applyPreset(preset, event) {
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
        this.explodedView = false;
    },

    selectComponent(component, event) {
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
        return this.selectedToppings.length > 0;
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

    get totalPrice() {
        let total = 0;
        if (this.selectedBread) total += parseFloat(this.selectedBread.price);
        if (this.selectedFlavor) total += parseFloat(this.selectedFlavor.price);
        this.selectedToppings.forEach((t) => (total += parseFloat(t.price)));
        return total;
    },

    formatPrice(n) {
        return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
    },

    get canCheckout() {
        return this.selectedBread && this.selectedFlavor;
    },

    async submitOrder() {
        this.errorMessage = '';

        if (!this.canCheckout) {
            this.errorMessage = 'Please select a base and a flavor first.';
            return;
        }
        if (!this.customerName || !this.customerPhone) {
            this.errorMessage = 'Please fill in your name and phone number.';
            return;
        }

        this.submitting = true;

        const componentIds = [
            this.selectedBread.id,
            this.selectedFlavor.id,
            ...this.selectedToppings.map((t) => t.id),
        ];

        try {
            const res = await axios.post('/api/orders', {
                customer_name: this.customerName,
                customer_phone: this.customerPhone,
                component_ids: componentIds,
            });
            this.orderSuccess = res.data.order_number;
        } catch (e) {
            this.errorMessage =
                e.response?.data?.errors?.component_ids?.[0] ??
                e.response?.data?.message ??
                'Something went wrong. Please try again.';
        } finally {
            this.submitting = false;
        }
    },

    resetOrder() {
        this.orderSuccess = null;
        this.selectedBread =
            this.components.bread.find((c) => c.name === 'Plain White Bread') ?? this.components.bread[0] ?? null;
        this.selectedFlavor =
            this.components.ice_cream.find((c) => c.name === 'Strawberry') ?? this.components.ice_cream[0] ?? null;
        this.selectedToppings = [];
        this.customerName = '';
        this.customerPhone = '';
        this.activeTab = 'bread';
        this.explodedView = false;
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
