// About page: Lenis smooth scrolling plus a small scroll engine. Every effect on the page is a
// function of scroll position, computed once per frame and handed to CSS as a custom property
// (--p on pinned chapters) or applied directly (word reveal, parallax, marquee, 3D camera).
//
// Without this script (or with reduced motion) the page is a normal, fully readable document:
// the pinned chapters only become tall and sticky once the root gets the `about-live` class.
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));

function splitWords(el) {
    const words = el.textContent.trim().split(/\s+/);
    el.textContent = '';
    return words.map((word) => {
        const span = document.createElement('span');
        span.className = 'about-word';
        span.textContent = word;
        el.append(span, ' ');
        return span;
    });
}

function countUp(el) {
    const target = parseFloat(el.dataset.count);
    const decimals = (el.dataset.count.split('.')[1] || '').length;
    const started = performance.now();
    const step = (now) => {
        const t = clamp((now - started) / 1100);
        const eased = 1 - Math.pow(1 - t, 4);
        el.textContent = (target * eased).toFixed(decimals);
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

// How far the chocolate slab has melted into matcha (0 to 1), eased, while the slab is pulled apart.
function matchaMixAt(p) {
    const t = Math.min(1, Math.max(0, (p - 0.26) / 0.3));
    return t * t * (3 - 2 * t);
}

// The pinned 3D chapter: which sandwich to show at each point of the scroll.
function sandwichStateAt(p) {
    return {
        bread: 'Plain White Bread',
        flavor: 'Chocolate',
        flavorColor: '#6B4226',
        sauce: false,
        sprinkles: p >= 0.62,
        exploded: p >= 0.22 && p < 0.86,
    };
}

export function initAbout(root, { reducedMotion = false } = {}) {
    const wordGroups = [...root.querySelectorAll('[data-words]')].map((el) => ({
        pin: el.closest('[data-pin]'),
        words: splitWords(el),
    }));

    if (reducedMotion) return null;

    root.classList.add('about-live');

    const lenis = new Lenis({ lerp: 0.085, wheelMultiplier: 1, anchors: true });

    const pins = [...root.querySelectorAll('[data-pin]')].map((el) => ({
        el,
        sticky: el.querySelector('.about-sticky'),
        top: 0,
        steps: [...el.querySelectorAll('[data-step]')].map((step) => ({
            el: step,
            from: parseFloat(step.dataset.from),
            to: parseFloat(step.dataset.to),
        })),
        p: -1,
    }));
    const views = [...root.querySelectorAll('[data-view]')];
    const parallax = [...root.querySelectorAll('[data-parallax]')].map((el) => ({ el, speed: parseFloat(el.dataset.parallax) }));
    const marquees = [...root.querySelectorAll('[data-marquee]')].map((el) => ({ el, speed: parseFloat(el.dataset.marquee) || 0.35 }));
    const bar = root.querySelector('[data-about-bar]');
    const rings = [...root.querySelectorAll('.about-stamp-ring')];

    // Count-up numbers and one-shot reveals start when they scroll into view.
    const seen = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                seen.unobserve(entry.target);
                if (entry.target.dataset.count) countUp(entry.target);
                entry.target.classList.add('is-in');
            });
        },
        { threshold: 0.4 },
    );
    root.querySelectorAll('[data-count], [data-in]').forEach((el) => seen.observe(el));

    // 3D sandwich, loaded only when its chapter is close.
    const stage = root.querySelector('[data-about-stage]');
    const stagePin = stage ? pins.find((pin) => pin.el.contains(stage)) : null;
    let studio = null;
    let studioKey = '';
    if (stage) {
        // Built during idle time right after load, while the visitor is still on the opening
        // screen: compiling shaders mid-scroll would cost a visible hitch at this chapter.
        const build = async () => {
            try {
                const { createStudio3D, supportsWebGL } = await import('./studio3d.js');
                if (!supportsWebGL()) return;
                const api = createStudio3D(stage, { autoRotate: false, interactive: false });
                await api.setState(sandwichStateAt(0));
                api.renderStill();
                studio = api;
                stage.closest('[data-about-stage-wrap]')?.classList.add('is-ready');
            } catch (error) {
                console.warn('About 3D sandwich unavailable, keeping the photo.', error);
            }
        };
        if ('requestIdleCallback' in window) requestIdleCallback(build, { timeout: 2500 });
        else setTimeout(build, 1200);
    }

    // Where each sticky panel parks (below the fixed header); re-read when the layout changes.
    const measure = () => pins.forEach((pin) => (pin.top = pin.sticky ? parseFloat(getComputedStyle(pin.sticky).top) || 0 : 0));
    measure();
    window.addEventListener('resize', measure);

    const update = () => {
        const viewport = window.innerHeight;

        pins.forEach((pin) => {
            const rect = pin.el.getBoundingClientRect();
            const travel = rect.height - (pin.sticky?.offsetHeight ?? viewport);
            const p = travel > 0 ? clamp((pin.top - rect.top) / travel) : 0;
            if (Math.abs(p - pin.p) < 0.0005) return;
            pin.p = p;
            pin.el.style.setProperty('--p', p.toFixed(4));
            pin.steps.forEach((step) => step.el.classList.toggle('is-active', p >= step.from && p < step.to));
        });

        wordGroups.forEach(({ pin, words }) => {
            const p = pins.find((entry) => entry.el === pin)?.p ?? 1;
            const lit = p * (words.length + 4);
            words.forEach((word, i) => {
                word.style.opacity = (0.14 + 0.86 * clamp(lit - i)).toFixed(2);
            });
        });

        parallax.forEach(({ el, speed }) => {
            const rect = el.parentElement.getBoundingClientRect();
            const offset = (rect.top + rect.height / 2 - viewport / 2) * speed;
            el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0) rotate(var(--tilt, 0deg))`;
        });

        // Strips whose content is rendered twice: slide by scroll and wrap at the halfway point.
        // A negative speed runs the strip the other way.
        marquees.forEach(({ el, speed }) => {
            const half = el.scrollWidth / 2;
            if (half <= 0) return;
            const x = (((window.scrollY * speed) % half) + half) % half;
            el.style.transform = `translate3d(${(-x).toFixed(1)}px, 0, 0)`;
        });

        // --v: 0 when an element's top reaches the bottom of the viewport, 1 when its bottom leaves the top.
        views.forEach((el) => {
            const rect = el.getBoundingClientRect();
            if (rect.bottom < -200 || rect.top > viewport + 200) return;
            el.style.setProperty('--v', clamp((viewport - rect.top) / (viewport + rect.height)).toFixed(4));
        });

        // Set on the three rings themselves: a custom property on the page root would force a
        // style recalculation of every element under it on each frame.
        const spin = `rotate(${(window.scrollY * 0.12).toFixed(1)}deg)`;
        rings.forEach((ring) => (ring.style.transform = spin));

        if (bar) {
            const max = document.documentElement.scrollHeight - viewport;
            bar.style.transform = `scaleX(${max > 0 ? clamp(window.scrollY / max).toFixed(4) : 0})`;
        }

        if (studio && stagePin) {
            const p = stagePin.p;
            studio.setView(-28 + p * 340, 66 - Math.sin(p * Math.PI) * 10);
            const next = sandwichStateAt(p);
            const key = `${next.sprinkles}${next.exploded}`;
            studio.setFlavorMix('Matcha', matchaMixAt(p));
            if (key !== studioKey) {
                studioKey = key;
                studio.setState(next).catch(() => {});
            }
        }
    };

    // Layout is only read when something moved: an idle page costs one comparison per frame.
    let lastY = -1;
    let lastHeight = -1;
    let lastWidth = -1;
    const frame = (time) => {
        lenis.raf(time);
        const height = document.documentElement.scrollHeight;
        if (window.scrollY !== lastY || height !== lastHeight || window.innerWidth !== lastWidth || (studio && !studioKey)) {
            lastY = window.scrollY;
            lastHeight = height;
            lastWidth = window.innerWidth;
            update();
        }
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);

    return lenis;
}
