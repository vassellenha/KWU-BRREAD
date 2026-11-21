/**
 * Arrow-key navigation for the staff panel, grown out of the keyboard handling on the staff sign-in
 * (staffLogin in app.js). One directive, three modes:
 *
 *   x-grid-nav                  a table of editable cells: rows are [data-grid-row], cells are [data-cell]
 *   x-grid-nav.list             a list you scan: each [data-grid-row] takes focus itself (roving tabindex)
 *   x-grid-nav.menu             a set of links (roving tabindex); add .horizontal for a row of links
 *
 * Grid keys: Up/Down same column in the row above/below (also on number fields, where Shift+Up/Down changes
 * the value), Left/Right neighbour cell (inside a text field only once the caret sits at the edge), Enter and
 * Shift+Enter down and up, Home/End first/last cell of the row, PageUp/PageDown ten rows, Ctrl+Home/End the
 * first/last cell. Alt+Down opens a select. Tab and Shift+Tab are left alone.
 */

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const PAGE = 10;
const TEXT_TYPES = ['text', 'search', 'tel', 'email', 'url', 'password'];

const usable = (node) => node
    && !node.disabled
    && node.getAttribute('aria-disabled') !== 'true'
    && !node.closest('[hidden]')
    && node.getClientRects().length > 0;

function reveal(node) {
    node.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' });
}

function focusNode(node, { select = true } = {}) {
    node.focus({ preventScroll: true });
    reveal(node);
    if (select && node.matches?.('input') && TEXT_TYPES.includes(node.type)) node.select();
}

const isNumeric = (node) => node.matches?.('input[data-stepper], input[type="number"]');

/** Changes a number field by whole steps, the way its +/- buttons do. */
function stepValue(input, direction) {
    const step = Number(input.dataset.stepper) || Number(input.step) || 1;
    const min = input.dataset.min !== undefined ? Number(input.dataset.min) : 0;
    const max = input.dataset.max !== undefined ? Number(input.dataset.max) : Number.MAX_SAFE_INTEGER;
    const current = Number(String(input.value).replace(/\D/g, '')) || 0;
    const next = Math.min(max, Math.max(min, current + direction * step));
    input.value = String(next);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

export function registerGridNav(Alpine) {
    Alpine.directive('grid-nav', (el, { modifiers }, { cleanup }) => {
        const ac = new AbortController();
        const { signal } = ac;
        cleanup(() => ac.abort());

        if (modifiers.includes('menu')) {
            roving(el, signal, modifiers.includes('horizontal') ? 'horizontal' : 'vertical');
        } else if (modifiers.includes('list')) {
            list(el, signal);
        } else {
            grid(el, signal);
        }
    });
}

// ---- table of editable cells -------------------------------------------------------------------------------

function grid(el, signal) {
    const rows = () => [...el.querySelectorAll('[data-grid-row]')].filter((row) => row.getClientRects().length > 0 && !row.closest('[hidden]'));
    const cellsOf = (row) => [...row.querySelectorAll('[data-cell]')].filter(usable);

    // + and - buttons beside a number field.
    el.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-step-button]');
        if (!button || !el.contains(button)) return;
        const input = button.closest('[data-stepper-wrap]')?.querySelector('input');
        if (!input || input.disabled) return;
        event.preventDefault();
        stepValue(input, Number(button.dataset.stepButton));
        input.focus({ preventScroll: true });
    }, { signal });

    el.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.isComposing) return;
        const cell = event.target.closest?.('[data-cell]');
        if (!cell || !el.contains(cell)) return;
        const row = cell.closest('[data-grid-row]');
        if (!row) return;

        const key = event.key;
        const ctrl = event.ctrlKey || event.metaKey;
        const isSelect = cell.tagName === 'SELECT';
        const isTextual = cell.matches('textarea') || (cell.matches('input') && TEXT_TYPES.includes(cell.type));

        if (isSelect && event.altKey && key === 'ArrowDown') {
            event.preventDefault();
            cell.showPicker?.();
            return;
        }
        if (event.altKey) return;

        // Shift+Up/Down changes a number by one step.
        if (event.shiftKey && !ctrl && (key === 'ArrowUp' || key === 'ArrowDown') && isNumeric(cell)) {
            event.preventDefault();
            stepValue(cell, key === 'ArrowUp' ? 1 : -1);
            return;
        }

        const all = rows();
        const at = all.indexOf(row);
        if (at < 0) return;
        const cells = cellsOf(row);
        const col = cells.indexOf(cell);
        const same = (target) => {
            if (!target) return null;
            const list = cellsOf(target);
            return list.find((candidate) => candidate.dataset.cell === cell.dataset.cell) || list[Math.min(col, list.length - 1)] || null;
        };
        const caretAtStart = () => !isTextual || (cell.selectionStart === 0 && cell.selectionEnd === 0);
        const caretAtEnd = () => !isTextual || (cell.selectionStart === cell.value.length && cell.selectionEnd === cell.value.length);

        let target;
        switch (key) {
            case 'ArrowDown':
                if (event.shiftKey || ctrl) return;
                target = same(all[at + 1]) || cell;
                break;
            case 'ArrowUp':
                if (event.shiftKey || ctrl) return;
                target = same(all[at - 1]) || cell;
                break;
            case 'Enter':
                if (event.ctrlKey || event.metaKey || !cell.matches('input:not([type="checkbox"]):not([type="color"])')) return;
                target = same(all[at + (event.shiftKey ? -1 : 1)]) || cell;
                break;
            case 'ArrowLeft':
                if (event.shiftKey || ctrl || !caretAtStart() || cell.matches('input[type="number"], input[type="time"]')) return;
                target = cells[col - 1] || cell;
                break;
            case 'ArrowRight':
                if (event.shiftKey || ctrl || !caretAtEnd() || cell.matches('input[type="number"], input[type="time"]')) return;
                target = cells[col + 1] || cell;
                break;
            case 'Home':
                if (event.shiftKey) return;
                if (ctrl) target = cellsOf(all[0])[0];
                else if (caretAtStart()) target = cells[0];
                else return;
                break;
            case 'End':
                if (event.shiftKey) return;
                if (ctrl) {
                    const last = cellsOf(all[all.length - 1]);
                    target = last[last.length - 1];
                } else if (caretAtEnd()) target = cells[cells.length - 1];
                else return;
                break;
            case 'PageDown':
                if (isSelect || event.shiftKey || ctrl) return;
                target = same(all[Math.min(all.length - 1, at + PAGE)]);
                break;
            case 'PageUp':
                if (isSelect || event.shiftKey || ctrl) return;
                target = same(all[Math.max(0, at - PAGE)]);
                break;
            default:
                return;
        }

        event.preventDefault();
        if (target && target !== cell) focusNode(target);
    }, { signal });
}

// ---- list of rows you scan (Orders) ------------------------------------------------------------------------

function list(el, signal) {
    const rows = () => [...el.querySelectorAll('[data-grid-row]')].filter((row) => row.getClientRects().length > 0);

    const setStop = (active) => {
        rows().forEach((row) => row.setAttribute('tabindex', row === active ? '0' : '-1'));
    };
    setStop(rows()[0]);

    el.addEventListener('focusin', (event) => {
        const row = event.target.closest?.('[data-grid-row]');
        if (row && el.contains(row)) setStop(row);
    }, { signal });

    el.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const row = event.target.closest?.('[data-grid-row]');
        if (!row || !el.contains(row)) return;

        // Inside a row (a button, a link): Escape goes back to the row. Nothing else is taken from the controls.
        if (event.target !== row) {
            if (event.key === 'Escape') {
                event.preventDefault();
                focusNode(row, { select: false });
            }
            return;
        }

        const all = rows();
        const at = all.indexOf(row);
        let target = null;
        switch (event.key) {
            case 'ArrowDown': target = all[at + 1] || row; break;
            case 'ArrowUp': target = all[at - 1] || row; break;
            case 'PageDown': target = all[Math.min(all.length - 1, at + PAGE)]; break;
            case 'PageUp': target = all[Math.max(0, at - PAGE)]; break;
            case 'Home': target = all[0]; break;
            case 'End': target = all[all.length - 1]; break;
            case 'Enter': {
                // Opens the row's detail. A row with nothing to open hands focus to its first control.
                event.preventDefault();
                if (row.dataset.open) window.open(row.dataset.open, '_blank', 'noopener');
                else focusNode([...row.querySelectorAll('a[href], button, input, select')].find(usable) || row, { select: false });
                return;
            }
            default: return;
        }

        event.preventDefault();
        if (target) focusNode(target, { select: false });
    }, { signal });
}

// ---- links with one tab stop (side menu) -------------------------------------------------------------------

function roving(el, signal, orientation) {
    const items = () => [...el.querySelectorAll('a[href]')].filter((node) => node.getClientRects().length > 0);
    const setStop = (active) => items().forEach((node) => node.setAttribute('tabindex', node === active ? '0' : '-1'));
    const start = () => items().find((node) => node.getAttribute('aria-current') === 'page') || items()[0];
    setStop(start());

    el.addEventListener('focusin', (event) => {
        const link = event.target.closest?.('a[href]');
        if (link && el.contains(link)) setStop(link);
    }, { signal });

    el.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const link = event.target.closest?.('a[href]');
        if (!link || !el.contains(link)) return;

        const all = items();
        const at = all.indexOf(link);
        const next = orientation === 'horizontal' ? 'ArrowRight' : 'ArrowDown';
        const prev = orientation === 'horizontal' ? 'ArrowLeft' : 'ArrowUp';
        let target = null;

        if (event.key === next) target = all[(at + 1) % all.length];
        else if (event.key === prev) target = all[(at - 1 + all.length) % all.length];
        else if (event.key === 'Home') target = all[0];
        else if (event.key === 'End') target = all[all.length - 1];
        else return;

        event.preventDefault();
        focusNode(target, { select: false });
    }, { signal });
}
