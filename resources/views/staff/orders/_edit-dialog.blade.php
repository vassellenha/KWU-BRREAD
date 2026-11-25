{{-- "Edit pesanan": staged locally, previewed by the server, saved once with a reason (see resources/js/order-edit.js). --}}
@php
    $editorConfig = [
        'catalog' => $editCatalog,
        'previewUrl' => route('staff.orders.edit.preview', '__ID__'),
        'saveUrl' => route('staff.orders.edit.save', '__ID__'),
    ];
@endphp
<div
    x-data="orderEditor({{ \Illuminate\Support\Js::from($editorConfig) }})"
    @order-edit.window="openFor($event.detail)"
    x-show="open"
    x-cloak
    @keydown="onKeydown($event)"
    class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-on-surface/50 p-space-sm sm:p-space-lg"
    role="dialog"
    aria-modal="true"
    aria-labelledby="order-edit-title"
>
    <div
        x-ref="panel"
        tabindex="-1"
        @click.outside="close()"
        class="relative my-auto w-full max-w-3xl rounded-2xl bg-surface-container-lowest shadow-xl border border-outline-variant/30 outline-none"
    >
        <template x-if="order">
            <div>
                <header class="flex items-start justify-between gap-space-md border-b border-outline-variant/30 px-space-lg py-space-md">
                    <div class="min-w-0">
                        <h2 id="order-edit-title" class="font-headline-sm text-headline-sm text-on-surface">Edit pesanan <span class="font-mono" x-text="'#' + order.number"></span></h2>
                        <p class="font-body-sm text-body-sm text-on-surface-variant truncate" x-text="order.customer"></p>
                    </div>
                    <button type="button" @click="close()" aria-label="Tutup" class="press-scale shrink-0 flex h-9 w-9 items-center justify-center rounded-full hover:bg-surface-container">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </header>

                {{-- Saved: the result, with the WhatsApp message ready to send. Nothing is sent from here. --}}
                <template x-if="result">
                    <div class="px-space-lg py-space-lg space-y-space-md">
                        <p class="flex items-center gap-space-xs font-body-md text-body-md font-medium text-on-surface" role="status">
                            <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                            <span x-text="result.message"></span>
                        </p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Kabari pembeli lewat WhatsApp biar tahu apa yang berubah. Tombol ini hanya membuka chat dengan pesan siap kirim.</p>
                        <div class="flex flex-wrap gap-space-sm">
                            <template x-if="result.whatsapp_url">
                                <a :href="result.whatsapp_url" target="_blank" rel="noopener noreferrer" class="press-scale inline-flex items-center gap-1.5 rounded-full bg-green-700 px-space-md py-2 font-label-md text-label-md font-semibold text-white hover:bg-green-800">
                                    <span class="material-symbols-outlined text-[18px]">forum</span>
                                    Kirim info via WhatsApp
                                </a>
                            </template>
                            <button type="button" @click="close()" class="press-scale rounded-full bg-surface-container px-space-md py-2 font-label-md text-label-md font-semibold hover:bg-surface-container-high">Selesai</button>
                        </div>
                    </div>
                </template>

                <template x-if="!result">
                    <div>
                        <template x-if="!order.can_edit">
                            <p class="m-space-lg rounded-xl bg-error-container/20 p-space-md font-body-sm text-body-sm" role="alert">Pesanan ini sudah disiapkan, siap diambil, atau dibatalkan, jadi isinya tidak bisa diubah.</p>
                        </template>

                        <div class="space-y-space-md px-space-lg py-space-md" x-show="order.can_edit">
                            <template x-for="(creation, ci) in creations" :key="creation.index">
                                <fieldset class="rounded-xl border border-outline-variant/40 p-space-md space-y-space-sm">
                                    <legend class="px-1 font-label-md text-label-md font-semibold text-on-surface" x-text="creations.length > 1 ? 'Kreasi #' + (ci + 1) : 'Kreasi'"></legend>

                                    <div class="grid gap-space-sm sm:grid-cols-2">
                                        <label class="block font-label-md text-label-md text-on-surface-variant">
                                            Roti
                                            <select x-model.number="creation.bread" @change="changed()" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface">
                                                <template x-for="o in options('bread', creation)" :key="o.id">
                                                    <option :value="o.id" :disabled="o.disabled" :selected="o.id === creation.bread" x-text="o.name + (o.reason ? ' (' + o.reason + ')' : '')"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <label class="block font-label-md text-label-md text-on-surface-variant">
                                            Rasa es krim
                                            <select x-model.number="creation.flavor" @change="changed()" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface">
                                                <template x-for="o in options('ice_cream', creation)" :key="o.id">
                                                    <option :value="o.id" :disabled="o.disabled" :selected="o.id === creation.flavor" x-text="o.name + (o.reason ? ' (' + o.reason + ')' : '')"></option>
                                                </template>
                                            </select>
                                        </label>
                                    </div>

                                    <div>
                                        <p class="font-label-md text-label-md text-on-surface-variant">Topping</p>
                                        <ul class="mt-1 flex flex-wrap gap-1.5">
                                            <template x-for="o in toppingOptions(creation)" :key="o.id">
                                                <li>
                                                    <button
                                                        type="button"
                                                        @click="toggleTopping(creation, o)"
                                                        :aria-pressed="isOn(creation, o.id)"
                                                        :disabled="o.disabled && !isOn(creation, o.id)"
                                                        :title="o.reason ? o.name + ' (' + o.reason + ')' : o.name"
                                                        class="press-scale inline-flex items-center gap-1 rounded-full border px-2.5 py-1 font-label-md text-label-md transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                                        :class="isOn(creation, o.id) ? 'border-primary bg-primary-container/30 text-on-surface font-semibold' : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant'"
                                                    >
                                                        <svg x-show="o.icon" class="ing-icon shrink-0 text-[16px]" aria-hidden="true" focusable="false"><use :href="'#' + o.icon"></use></svg>
                                                        <span x-text="o.name"></span>
                                                        <span x-show="o.reason" class="text-[11px]" x-text="'(' + o.reason + ')'"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div class="flex items-center gap-space-xs">
                                        <span class="font-label-md text-label-md text-on-surface-variant">Jumlah</span>
                                        <button type="button" @click="setQuantity(creation, creation.quantity - 1)" :disabled="creation.quantity <= 1" aria-label="Kurangi jumlah" class="press-scale flex h-8 w-8 items-center justify-center rounded-full bg-surface-container hover:bg-surface-container-high disabled:opacity-40">
                                            <span class="material-symbols-outlined text-[18px]">remove</span>
                                        </button>
                                        <input type="number" min="1" max="20" :value="creation.quantity" @change="setQuantity(creation, $event.target.value)" aria-label="Jumlah" class="w-14 rounded-lg border border-outline-variant bg-surface-container-lowest py-1 text-center font-body-sm text-body-sm tabular-nums">
                                        <button type="button" @click="setQuantity(creation, creation.quantity + 1)" :disabled="creation.quantity >= 20" aria-label="Tambah jumlah" class="press-scale flex h-8 w-8 items-center justify-center rounded-full bg-surface-container hover:bg-surface-container-high disabled:opacity-40">
                                            <span class="material-symbols-outlined text-[18px]">add</span>
                                        </button>
                                    </div>
                                </fieldset>
                            </template>

                            {{-- Problems from the server (stock, inactive items, a conflicting edit). --}}
                            <div x-show="problems.length" x-cloak role="alert" class="rounded-xl bg-error-container/20 p-space-md font-body-sm text-body-sm text-on-surface space-y-1">
                                <template x-for="problem in problems" :key="problem"><p class="flex gap-1.5"><span class="material-symbols-outlined shrink-0 text-error text-[18px]">error</span><span x-text="problem"></span></p></template>
                                <button type="button" x-show="conflict" @click="window.location.reload()" class="press-scale mt-1 rounded-full bg-surface-container px-space-md py-1 font-label-md text-label-md font-semibold">Muat ulang halaman</button>
                            </div>

                            {{-- Diff preview: what the customer will see changed, and what it does to the total and the stock. --}}
                            <section x-show="dirty && (preview || loading)" x-cloak class="rounded-xl bg-surface-container/50 p-space-md space-y-space-xs" aria-live="polite">
                                <h3 class="font-label-md text-label-md font-semibold text-on-surface">Pratinjau perubahan</h3>
                                <p x-show="loading && !preview" class="font-body-sm text-body-sm text-on-surface-variant">Menghitung...</p>
                                <template x-if="preview">
                                    <div class="space-y-space-xs font-body-sm text-body-sm">
                                        <ul class="space-y-0.5">
                                            <template x-for="line in preview.changes" :key="line"><li class="flex gap-1.5"><span class="material-symbols-outlined shrink-0 text-primary text-[16px]">check</span><span x-text="line"></span></li></template>
                                        </ul>
                                        <template x-for="(was, i) in preview.before" :key="i">
                                            <p x-show="was !== preview.after[i]" class="text-on-surface-variant"><span x-text="was"></span> <span aria-label="menjadi">-&gt;</span> <span class="text-on-surface" x-text="preview.after[i]"></span></p>
                                        </template>
                                        <p class="font-semibold">
                                            Total <s class="opacity-60" x-text="money(preview.total_before)"></s> <span aria-label="menjadi">-&gt;</span> <span x-text="money(preview.total_after)"></span>
                                            <span x-show="preview.difference !== 0" class="ml-1" :class="preview.difference > 0 ? 'text-error' : 'text-green-700'" x-text="'(' + signed(preview.difference) + ')'"></span>
                                        </p>
                                        <p x-show="preview.needs_payment_adjustment" class="rounded-lg bg-amber-50 px-space-sm py-1 text-amber-900">Pesanan ini sudah dibayar dan totalnya berubah: sesuaikan pembayaran dengan pembeli lewat WhatsApp.</p>
                                        <ul x-show="preview.stock.length" class="text-on-surface-variant">
                                            <template x-for="s in preview.stock" :key="s.name"><li x-text="'Stok ' + s.name + ': ' + (s.delta > 0 ? '-' : '+') + Math.abs(s.delta) + ' (sisa sekarang ' + s.left + ')'"></li></template>
                                        </ul>
                                    </div>
                                </template>
                            </section>

                            <label class="block font-label-md text-label-md text-on-surface-variant">
                                Alasan perubahan (wajib)
                                <textarea x-model="reason" rows="2" maxlength="300" placeholder="Contoh: Rasa Neapolitan kosong, diganti Matcha via WA" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface"></textarea>
                            </label>
                        </div>

                        {{-- History of earlier edits of this order. --}}
                        <section x-show="order.history.length" class="border-t border-outline-variant/30 px-space-lg py-space-md space-y-space-xs">
                            <h3 class="flex items-center gap-1 font-label-md text-label-md font-semibold text-on-surface"><span class="material-symbols-outlined text-[18px]">history</span> Riwayat perubahan</h3>
                            <ol class="space-y-space-xs">
                                <template x-for="(h, hi) in order.history" :key="hi">
                                    <li class="rounded-lg bg-surface-container/40 p-space-sm font-body-sm text-body-sm">
                                        <p class="text-on-surface-variant"><span x-text="h.at"></span> oleh <span x-text="h.by"></span></p>
                                        <ul class="mt-0.5"><template x-for="c in h.changes" :key="c"><li x-text="c"></li></template></ul>
                                        <p class="italic" x-text="'Alasan: ' + h.reason"></p>
                                        <p class="text-on-surface-variant" x-show="h.total_before !== h.total_after" x-text="'Total ' + money(h.total_before) + ' -> ' + money(h.total_after)"></p>
                                        <a x-show="h.whatsapp_url" :href="h.whatsapp_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-green-700 hover:underline"><span class="material-symbols-outlined text-[16px]">forum</span> Kirim info via WhatsApp</a>
                                    </li>
                                </template>
                            </ol>
                        </section>

                        <footer class="flex flex-wrap items-center justify-end gap-space-sm border-t border-outline-variant/30 px-space-lg py-space-md">
                            <span class="mr-auto font-body-sm text-body-sm text-on-surface-variant hidden sm:inline">Ctrl+S simpan, Esc batal</span>
                            <button type="button" @click="close()" class="press-scale rounded-full bg-surface-container px-space-md py-2 font-label-md text-label-md font-semibold hover:bg-surface-container-high">Batalkan</button>
                            <button type="button" @click="save()" :disabled="!canSave" class="press-scale inline-flex items-center gap-1.5 rounded-full bg-primary px-space-md py-2 font-label-md text-label-md font-semibold text-on-primary disabled:cursor-not-allowed disabled:opacity-40">
                                <span class="material-symbols-outlined text-[18px]">check</span>
                                <span x-text="saving ? 'Menyimpan...' : 'Simpan perubahan'"></span>
                            </button>
                        </footer>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
