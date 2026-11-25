/**
 * The staff's "Edit pesanan" dialog on /staff/orders. The admin changes bread, flavor, toppings and quantity per
 * creation; every change is staged locally and sent to the server for a preview (new prices, stock, a friendly
 * list of what changed), then saved once with a reason. The server owns all prices and rules.
 *
 *   x-data="orderEditor({ catalog, previewUrl, saveUrl })"   the dialog root (URLs contain __ID__)
 *   $dispatch('order-edit', payload)                          opens it for one order (see OrderEditor::editorPayload)
 */
const MAX_QTY = 20;

export function registerOrderEdit(Alpine) {
    // A toast that survives the reload after saving.
    document.addEventListener('alpine:initialized', () => {
        try {
            const flash = sessionStorage.getItem('orders-flash');
            if (flash) {
                sessionStorage.removeItem('orders-flash');
                Alpine.store('toast')?.push(flash);
            }
        } catch {
            // No storage, no toast.
        }
    });

    Alpine.data('orderEditor', (config) => ({
        open: false,
        order: null,
        creations: [],
        reason: '',
        preview: null,
        loading: false,
        saving: false,
        problems: [],
        conflict: false,
        result: null,
        _timer: null,
        _seq: 0,

        get byId() {
            return Object.fromEntries(config.catalog.map((c) => [c.id, c]));
        },

        openFor(payload) {
            this.order = payload;
            this.creations = payload.creations.map((c) => this.stage(c));
            this.reason = '';
            this.preview = null;
            this.problems = [];
            this.conflict = false;
            this.result = null;
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.panel?.focus());
        },

        // One creation as the form edits it: the bread, the flavor and the toppings as ids.
        stage(creation) {
            const parts = creation.component_ids.map((id) => this.byId[id]).filter(Boolean);
            return {
                index: creation.index,
                quantity: creation.quantity,
                bread: parts.find((p) => p.category === 'bread')?.id ?? null,
                flavor: parts.find((p) => p.category === 'ice_cream')?.id ?? null,
                toppings: parts.filter((p) => p.category === 'topping').map((p) => p.id),
                original: creation.component_ids.slice(),
            };
        },

        close() {
            if (this.saving) return;
            const saved = this.result !== null;
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
            if (saved) window.location.reload();
        },

        options(category, creation) {
            return config.catalog.filter((c) => c.category === category).map((c) => {
                const original = creation.original.includes(c.id);
                const reason = original ? '' : !c.active ? 'tidak aktif' : c.stock <= 0 ? 'stok habis' : '';
                return { ...c, reason, disabled: reason !== '' };
            });
        },

        toppingOptions(creation) {
            return this.options('topping', creation);
        },

        isOn(creation, id) {
            return creation.toppings.includes(id);
        },

        toggleTopping(creation, option) {
            if (option.disabled && !this.isOn(creation, option.id)) return;
            creation.toppings = this.isOn(creation, option.id) ? creation.toppings.filter((id) => id !== option.id) : [...creation.toppings, option.id];
            this.changed();
        },

        setQuantity(creation, value) {
            creation.quantity = Math.min(MAX_QTY, Math.max(1, Number(value) || 1));
            this.changed();
        },

        ids(creation) {
            return [creation.bread, creation.flavor, ...creation.toppings].filter((id) => id !== null);
        },

        get items() {
            return this.creations.map((c) => ({ index: c.index, component_ids: this.ids(c), quantity: c.quantity }));
        },

        get dirty() {
            return this.creations.some((c) => {
                const now = this.ids(c).slice().sort((a, b) => a - b).join(',');
                const was = c.original.slice().sort((a, b) => a - b).join(',');
                return now !== was || c.quantity !== this.order.creations.find((o) => o.index === c.index)?.quantity;
            });
        },

        get canSave() {
            return this.dirty && !this.loading && !this.saving && this.preview !== null && this.problems.length === 0 && this.reason.trim().length >= 3 && !this.conflict;
        },

        // Any staged change asks the server for a fresh preview (debounced; a late answer for an old state is dropped).
        changed() {
            this.preview = null;
            this.problems = [];
            clearTimeout(this._timer);
            if (!this.dirty) return;
            this._timer = setTimeout(() => this.runPreview(), 350);
        },

        async runPreview() {
            const seq = ++this._seq;
            this.loading = true;
            try {
                const { data } = await window.axios.post(config.previewUrl.replace('__ID__', this.order.id), { items: this.items });
                if (seq === this._seq) this.preview = data;
            } catch (error) {
                if (seq === this._seq) this.showFailure(error);
            } finally {
                if (seq === this._seq) this.loading = false;
            }
        },

        async save() {
            if (!this.canSave) return;
            this.saving = true;
            this.problems = [];
            try {
                const { data } = await window.axios.put(config.saveUrl.replace('__ID__', this.order.id), {
                    items: this.items,
                    version: this.order.version,
                    reason: this.reason.trim(),
                });
                this.result = data;
                this.order = data.order;
                try {
                    sessionStorage.setItem('orders-flash', data.message);
                } catch {
                    // Fine: the dialog itself shows the message.
                }
                Alpine.store('toast')?.push(data.message);
            } catch (error) {
                this.showFailure(error);
            } finally {
                this.saving = false;
            }
        },

        showFailure(error) {
            const data = error.response?.data;
            this.conflict = error.response?.status === 409;
            const messages = data?.errors ? Object.values(data.errors).flat() : [data?.message || 'Terjadi kesalahan. Coba lagi.'];
            this.problems = this.conflict ? [data.message] : messages;
            this.preview = null;
        },

        money(value) {
            return 'Rp ' + Number(value).toLocaleString('id-ID');
        },

        signed(value) {
            const sign = value > 0 ? '+' : value < 0 ? '-' : '';
            return sign + this.money(Math.abs(value));
        },

        onKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                this.save();
            }
        },
    }));
}
