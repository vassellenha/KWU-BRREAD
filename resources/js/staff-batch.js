/**
 * "Save all" for the staff panel: the Stock, Menu and HR pages let you change many rows first and send them
 * in one request. The markup stays plain Blade; the page only marks what is editable:
 *
 *   x-data="batchForm({ url, shared })"        the root, around the table and the sticky bar
 *   data-batch-row="12" data-version="..."     one row (a <tr>); only changed rows are sent
 *   data-batch-field="price"                   an input, select or checkbox inside the row
 *   data-batch-trigger="quantity"              optional: the row only counts as changed when this field is filled
 *   data-batch-revert                          a button that puts one row back
 *   data-batch-cell="stock"                    text the server refreshes after a save
 *   data-show / data-hide / data-dim-unless    show, hide or dim parts of a row from a server flag
 *   data-stat="low_stock"                      page totals the server refreshes after a save
 *
 * The server answers 422 with errors keyed rows.<id>.<field>; a "version" error means somebody else changed the row.
 */

const LABELS = {
    saved: 'Saved',
    problems: 'Nothing was saved. Fix the marked fields and try again.',
    conflict: 'Nothing was saved. Someone else changed a row you edited. Reload to see their version.',
    offline: 'Could not reach the server. Nothing was changed, try again.',
    expired: 'Your session ran out. Reload the page and sign in again.',
    tooMany: 'Too many saves in a short time. Wait a moment and try again.',
    failed: 'Something went wrong on the server. Nothing was changed.',
    was: 'was',
    empty: 'empty',
    on: 'on the menu',
    off: 'hidden',
};

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const plural = (count, one, many) => `${count} ${count === 1 ? one : many}`;

const readValue = (control) => (control.type === 'checkbox' ? (control.checked ? '1' : '0') : String(control.value ?? '').trim());

function writeValue(control, value) {
    if (control.type === 'checkbox') control.checked = value === '1';
    else control.value = value;
}

/** What the person sees as "before" for a field. */
function describe(control, value) {
    if (control.type === 'checkbox') return value === '1' ? control.dataset.on || LABELS.on : control.dataset.off || LABELS.off;
    if (control.tagName === 'SELECT') return control.querySelector(`option[value="${CSS.escape(value)}"]`)?.textContent.trim() ?? value;
    return value === '' ? LABELS.empty : value;
}

export function registerStaffBatch(Alpine) {
    // One toast list for the client side messages ("12 changes saved"). The server-rendered flash stays as it is.
    Alpine.store('toast', {
        items: [],
        push(message, tone = 'ok') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone });
            window.setTimeout(() => {
                this.items = this.items.filter((item) => item.id !== id);
            }, tone === 'error' ? 7000 : 3600);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    Alpine.data('batchForm', (config = {}) => ({
        count: 0,
        root: null,
        conflicts: 0,
        problems: 0,
        saving: false,
        announce: '',
        hintOpen: false,
        guardOpen: false,
        leaving: false,
        _ac: null,
        _proceed: null,
        _bypassSubmit: false,

        init() {
            const root = this.$el;
            this.root = root;
            this._ac = new AbortController();
            const { signal } = this._ac;

            root.querySelectorAll('[data-batch-field]').forEach((control) => {
                control.dataset.original = readValue(control);
            });

            // One short tip per browser session.
            try {
                this.hintOpen = sessionStorage.getItem('staffBatchHint') !== '1';
            } catch (error) {
                this.hintOpen = true;
            }

            const onEdit = (event) => {
                const control = event.target.closest?.('[data-batch-field]');
                if (!control || !root.contains(control)) return;
                this.clearError(control);
                this.refreshRow(control.closest('[data-batch-row]'));
                this.recount();
            };
            root.addEventListener('input', onEdit, { signal });
            root.addEventListener('change', onEdit, { signal });

            root.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || event.defaultPrevented) return;
                const control = event.target.closest?.('[data-batch-field]');
                if (!control || readValue(control) === control.dataset.original) return;
                event.preventDefault();
                this.revertField(control);
            }, { signal });

            window.addEventListener('keydown', (event) => {
                if (!(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== 's') return;
                event.preventDefault();
                if (!this.guardOpen) this.save();
            }, { signal });

            window.addEventListener('beforeunload', (event) => {
                if (this.count === 0 || this.leaving) return;
                event.preventDefault();
                event.returnValue = '';
            }, { signal });

            // Leaving through an in-app link or a form (log out, delete) with changes waiting asks first.
            document.addEventListener('click', (event) => {
                if (this.count === 0 || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                const link = event.target.closest?.('a[href]');
                if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
                const url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin) return;
                if (url.pathname === window.location.pathname && url.search === window.location.search) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.askBeforeLeaving(() => window.location.assign(link.href));
            }, { signal, capture: true });

            document.addEventListener('submit', (event) => {
                if (this._bypassSubmit) {
                    this._bypassSubmit = false;
                    return;
                }
                const form = event.target;
                if (this.count === 0 || !(form instanceof HTMLFormElement)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.askBeforeLeaving(() => {
                    this._bypassSubmit = true;
                    form.requestSubmit();
                });
            }, { signal, capture: true });
        },

        destroy() {
            this._ac?.abort();
        },

        // ---- tracking changes ------------------------------------------------------------------------------

        rows() {
            return [...this.root.querySelectorAll('[data-batch-row]')];
        },

        fields(row) {
            return [...row.querySelectorAll('[data-batch-field]')];
        },

        isDirty(row) {
            const trigger = row.dataset.batchTrigger;
            if (trigger) {
                const control = row.querySelector(`[data-batch-field="${trigger}"]`);
                return !!control && readValue(control) !== control.dataset.original;
            }
            return this.fields(row).some((control) => !control.disabled && readValue(control) !== control.dataset.original);
        },

        refreshRow(row) {
            if (!row) return;
            const dirty = this.isDirty(row);
            row.classList.toggle('is-dirty', dirty);
            row.dataset.dirty = dirty ? '1' : '0';

            const hinted = !row.dataset.batchTrigger;
            this.fields(row).forEach((control) => {
                const changed = readValue(control) !== control.dataset.original;
                const note = this.note(control, 'prev', changed && hinted && !control.disabled);
                if (note) note.textContent = `${LABELS.was}: ${describe(control, control.dataset.original)}`;
                control.classList.toggle('is-changed', changed && hinted && !control.disabled);
                if (changed && hinted) control.title = `${LABELS.was}: ${describe(control, control.dataset.original)}`;
                else control.removeAttribute('title');
            });
        },

        recount() {
            this.count = this.rows().filter((row) => row.dataset.dirty === '1').length;
            if (this.count === 0) this.conflicts = 0;
        },

        /** The small text line under a field: "was: 8000" or an error. Created on demand, removed when not needed. */
        note(control, kind, wanted, text = '') {
            const anchor = control.closest('label') || control;
            let note = anchor.parentElement.querySelector(`[data-batch-note="${kind}"][data-for="${control.dataset.batchField}"]`);
            if (!wanted) {
                note?.remove();
                return null;
            }
            if (!note) {
                note = document.createElement('small');
                note.dataset.batchNote = kind;
                note.dataset.for = control.dataset.batchField;
                note.className = `batch-note batch-note--${kind}`;
                if (kind === 'error') {
                    note.id = `batch-error-${Math.random().toString(36).slice(2, 9)}`;
                    note.setAttribute('role', 'alert');
                }
                anchor.after(note);
            }
            if (text) note.textContent = text;
            return note;
        },

        clearError(control) {
            const note = this.note(control, 'error', false);
            control.removeAttribute('aria-invalid');
            if (control.dataset.describedBefore !== undefined) {
                if (control.dataset.describedBefore) control.setAttribute('aria-describedby', control.dataset.describedBefore);
                else control.removeAttribute('aria-describedby');
                delete control.dataset.describedBefore;
            }
            return note;
        },

        clearAllErrors() {
            const root = this.root;
            root.querySelectorAll('[data-batch-field][aria-invalid]').forEach((control) => this.clearError(control));
            root.querySelectorAll('[data-batch-note="conflict"]').forEach((note) => note.remove());
            root.querySelectorAll('[data-batch-row].has-conflict').forEach((row) => row.classList.remove('has-conflict'));
            this.problems = 0;
            this.conflicts = 0;
        },

        revertField(control) {
            writeValue(control, control.dataset.original);
            control.dispatchEvent(new Event('input', { bubbles: true }));
            control.dispatchEvent(new Event('change', { bubbles: true }));
        },

        revertRow(row) {
            this.fields(row).forEach((control) => this.revertField(control));
            row.classList.remove('has-conflict');
            row.querySelectorAll('[data-batch-note="conflict"]').forEach((note) => note.remove());
            this.refreshRow(row);
            this.recount();
            this.announce = 'Row put back as it was.';
        },

        discardAll() {
            this.rows().filter((row) => row.dataset.dirty === '1').forEach((row) => this.revertRow(row));
            this.clearAllErrors();
            this.announce = 'All changes discarded.';
        },

        dismissHint() {
            this.hintOpen = false;
            try {
                sessionStorage.setItem('staffBatchHint', '1');
            } catch (error) {
                // the tip simply shows again next page
            }
        },

        reloadPage() {
            this.leaving = true;
            window.location.reload();
        },

        // ---- saving ----------------------------------------------------------------------------------------

        payload() {
            const rows = {};
            this.rows().filter((row) => row.dataset.dirty === '1').forEach((row) => {
                const data = { version: row.dataset.version };
                this.fields(row).forEach((control) => {
                    if (control.disabled) return;
                    data[control.dataset.batchField] = control.type === 'checkbox' ? control.checked : readValue(control);
                });
                rows[row.dataset.batchRow] = data;
            });

            const body = { rows };
            if (config.shared) {
                const shared = this.root.querySelector(config.shared);
                if (shared) body[shared.dataset.batchShared] = readValue(shared);
            }
            return body;
        },

        async save() {
            if (this.saving || this.count === 0) return false;
            this.saving = true;
            this.clearAllErrors();
            this.announce = 'Saving.';

            try {
                const { data } = await window.axios.post(config.url, this.payload());
                this.applySaved(data);
                this.recount();
                Alpine.store('toast').push(data.message || `${plural(data.saved, 'change', 'changes')} saved.`);
                this.announce = data.message || 'Saved.';
                return true;
            } catch (error) {
                this.showFailure(error);
                return false;
            } finally {
                this.saving = false;
            }
        },

        applySaved(data) {
            const root = this.root;
            Object.entries(data.rows || {}).forEach(([id, saved]) => {
                const row = root.querySelector(`[data-batch-row="${id}"]`);
                if (!row) return;
                row.dataset.version = saved.version;
                this.fields(row).forEach((control) => {
                    const value = saved.fields?.[control.dataset.batchField];
                    if (value !== undefined) writeValue(control, String(value));
                    control.dataset.original = readValue(control);
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                });
                Object.entries(saved.cells || {}).forEach(([name, text]) => {
                    row.querySelectorAll(`[data-batch-cell="${name}"]`).forEach((cell) => {
                        cell.textContent = text;
                    });
                });
                Object.entries(saved.flags || {}).forEach(([flag, on]) => {
                    row.querySelectorAll(`[data-show="${flag}"]`).forEach((node) => node.toggleAttribute('hidden', !on));
                    row.querySelectorAll(`[data-hide="${flag}"]`).forEach((node) => node.toggleAttribute('hidden', !!on));
                    row.querySelectorAll(`[data-dim-unless="${flag}"]`).forEach((node) => node.classList.toggle('opacity-50', !on));
                    if (row.dataset.flagClasses) {
                        const map = JSON.parse(row.dataset.flagClasses);
                        if (map[flag]) row.classList.toggle(map[flag], !!on);
                    }
                });
                this.refreshRow(row);
            });
            Object.entries(data.stats || {}).forEach(([name, text]) => {
                root.querySelectorAll(`[data-stat="${name}"]`).forEach((node) => {
                    node.textContent = text;
                });
            });
        },

        showFailure(error) {
            const status = error.response?.status;
            if (status !== 422) {
                const text = !error.response ? LABELS.offline : status === 419 ? LABELS.expired : status === 429 ? LABELS.tooMany : LABELS.failed;
                Alpine.store('toast').push(text, 'error');
                this.announce = text;
                return;
            }

            const root = this.root;
            const failed = [];
            const conflictRows = new Set();

            Object.entries(error.response.data.errors || {}).forEach(([key, messages]) => {
                const match = /^rows\.(\d+)\.(.+)$/.exec(key);
                const row = match ? root.querySelector(`[data-batch-row="${match[1]}"]`) : null;
                if (!row) return;

                if (match[2] === 'version') {
                    conflictRows.add(row);
                    row.classList.add('has-conflict');
                    const holder = row.querySelector('[data-batch-title]') || row.firstElementChild || row;
                    const note = document.createElement('small');
                    note.dataset.batchNote = 'conflict';
                    note.className = 'batch-note batch-note--error';
                    note.setAttribute('role', 'alert');
                    note.textContent = messages[0];
                    holder.append(note);
                    failed.push(holder);
                    return;
                }

                const control = row.querySelector(`[data-batch-field="${match[2]}"]`);
                if (!control) return;
                const note = this.note(control, 'error', true, messages[0]);
                control.dataset.describedBefore = control.getAttribute('aria-describedby') || '';
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', [control.dataset.describedBefore, note.id].filter(Boolean).join(' '));
                failed.push(control);
            });

            this.conflicts = conflictRows.size;
            this.problems = failed.length;
            const text = conflictRows.size > 0 ? LABELS.conflict : LABELS.problems;
            Alpine.store('toast').push(text, 'error');
            this.announce = text;

            const first = failed.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0];
            if (first) {
                first.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
                if (first.matches('input, select, textarea')) first.focus({ preventScroll: true });
            }
        },

        // ---- leaving the page with changes waiting ----------------------------------------------------------

        askBeforeLeaving(proceed) {
            this._proceed = proceed;
            this.guardOpen = true;
            this.$nextTick(() => this.$refs.guardStay?.focus());
        },

        guardStay() {
            this.guardOpen = false;
            this._proceed = null;
        },

        guardDiscard() {
            const proceed = this._proceed;
            this.guardOpen = false;
            this._proceed = null;
            this.leaving = true;
            this.count = 0;
            proceed?.();
        },

        async guardSave() {
            const proceed = this._proceed;
            const ok = await this.save();
            if (!ok) {
                this.guardOpen = false;
                this._proceed = null;
                return;
            }
            this.guardOpen = false;
            this._proceed = null;
            this.leaving = true;
            proceed?.();
        },

        guardKeydown(event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.guardStay();
                return;
            }
            if (event.key !== 'Tab') return;
            const buttons = [...event.currentTarget.querySelectorAll('button:not([disabled])')];
            const first = buttons[0];
            const last = buttons[buttons.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }));
}
