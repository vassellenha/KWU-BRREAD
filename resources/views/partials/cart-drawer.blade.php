<div x-data="cartDrawer()" x-cloak>
    {{-- Scrim --}}
    <div
        x-show="$store.cart.open"
        x-transition:enter="transition-opacity duration-300 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-200 ease-[var(--ease-in-out)]"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$store.cart.open = false"
        class="fixed inset-0 bg-on-surface/50 z-[60]"
    ></div>

    {{-- Panel --}}
    <aside
        x-show="$store.cart.open"
        x-transition:enter="transition-transform duration-300 ease-[var(--ease-out)]"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-200 ease-[var(--ease-in-out)]"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @keydown.escape.window="$store.cart.open = false"
        class="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-surface z-[61] shadow-[-8px_0_32px_rgba(113,59,44,0.15)] flex flex-col"
    >
        <div class="flex items-center justify-between p-space-md border-b border-outline-variant/30 shrink-0">
            <h2 class="font-title-lg text-title-lg text-on-surface flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">shopping_bag</span>
                My Sweet Cart
            </h2>
            <button type="button" @click="$store.cart.open = false" class="press-scale w-9 h-9 rounded-full bg-surface-container hover:bg-surface-container-high flex items-center justify-center text-on-surface-variant transition-colors duration-200">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-space-md">

            <template x-if="orderSuccess">
                <div
                    x-transition:enter="transition-[opacity,transform] duration-500 ease-[var(--ease-bounce)]"
                    x-transition:enter-start="opacity-0 scale-90"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="rounded-lg bg-primary-container shadow-[0_4px_16px_rgba(113,59,44,0.15)] p-space-lg text-center"
                >
                    <span class="material-symbols-outlined text-[32px] text-on-primary-container">celebration</span>
                    <p class="font-title-md text-title-md text-on-primary-container mt-1">Order placed!</p>
                    <p class="font-body-sm text-body-sm text-on-primary-container/80 mt-1">
                        Order number: <span class="font-mono font-bold" x-text="orderSuccess"></span>
                    </p>
                    <button type="button" @click="closeAndReset()" class="mt-space-md font-label-lg text-label-lg text-on-primary-container underline decoration-on-primary-container/50 underline-offset-2 hover:decoration-on-primary-container transition-colors duration-200">
                        Keep browsing
                    </button>
                </div>
            </template>

            <template x-if="!orderSuccess && $store.cart.items.length === 0">
                <div class="flex flex-col items-center justify-center text-center gap-space-sm py-space-xl">
                    <span class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[32px]">shopping_bag</span>
                    </span>
                    <p class="font-title-md text-title-md text-on-surface">Your cart is empty</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant max-w-[240px]">Head to Brread! Studio to build your first ice cream sandwich.</p>
                    <a
                        href="{{ route('build') }}"
                        @click="$store.cart.open = false"
                        class="press-scale mt-space-xs inline-flex items-center gap-space-xs rounded-full bg-primary-container text-on-primary-container px-space-lg py-space-sm font-label-lg text-label-lg shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 transition-[opacity,transform] duration-200"
                    >
                        <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                        Start Building
                    </a>
                </div>
            </template>

            <template x-if="!orderSuccess && $store.cart.items.length > 0">
                <div class="flex flex-col gap-space-sm">
                    <template x-for="item in $store.cart.items" :key="item.uid">
                        <div class="rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-space-sm flex gap-space-sm">
                            <div class="flex-1 min-w-0">
                                <p class="font-title-sm text-title-sm text-on-surface truncate" x-text="item.bread.name + ' · ' + item.flavor.name"></p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant truncate" x-text="item.toppings.length ? item.toppings.map((t) => t.name).join(', ') : 'No toppings'"></p>
                                <div class="flex items-center justify-between mt-space-xs">
                                    <div class="flex items-center gap-1.5 bg-surface-container rounded-full p-0.5">
                                        <button type="button" @click="$store.cart.setQuantity(item.uid, item.quantity - 1)" class="press-scale w-7 h-7 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">remove</span>
                                        </button>
                                        <span class="font-label-lg text-label-lg text-on-surface w-5 text-center" x-text="item.quantity"></span>
                                        <button type="button" @click="$store.cart.setQuantity(item.uid, item.quantity + 1)" class="press-scale w-7 h-7 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">add</span>
                                        </button>
                                    </div>
                                    <span class="font-label-lg text-label-lg text-primary" x-text="formatPrice($store.cart.lineTotal(item))"></span>
                                </div>
                            </div>
                            <button type="button" @click="$store.cart.remove(item.uid)" class="press-scale w-8 h-8 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors duration-200 shrink-0">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <template x-if="!orderSuccess && $store.cart.items.length > 0">
            <div class="p-space-md border-t border-outline-variant/30 flex flex-col gap-space-sm shrink-0">
                <div class="flex justify-between items-center">
                    <span class="font-title-md text-title-md text-on-surface">Total</span>
                    <span class="font-headline-sm text-headline-sm text-primary" x-text="formatPrice($store.cart.total)"></span>
                </div>
                <input
                    type="text"
                    x-model="customerName"
                    @input="customerName = customerName.replace(/[0-9]/g, '')"
                    placeholder="Your name"
                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                <input
                    type="tel"
                    inputmode="tel"
                    x-model="customerPhone"
                    @input="customerPhone = customerPhone.replace(/[^0-9+]/g, '')"
                    placeholder="+62 812 3456 7890"
                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                <p x-show="errorMessage" x-cloak class="font-body-sm text-body-sm text-error font-medium" x-text="errorMessage"></p>
                <button
                    type="button"
                    @click="checkout()"
                    :disabled="submitting"
                    class="w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-[opacity,transform] duration-200 hover:opacity-95 active:translate-y-[2px] disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <span x-show="!submitting" class="flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                        Checkout
                    </span>
                    <span x-show="submitting" x-cloak>Placing order...</span>
                </button>
            </div>
        </template>
    </aside>
</div>
