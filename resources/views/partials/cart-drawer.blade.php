@php($shopWhatsapp = \App\Support\ContactInfo::get()['whatsapp_links'])
<div x-data="cartDrawer()" data-wa-url="{{ $shopWhatsapp[0]['url'] ?? '' }}" data-wa-admins='@json(array_map(fn ($l) => ['name' => $l['name'], 'url' => $l['url']], $shopWhatsapp), JSON_HEX_APOS)' @keydown.escape.window="confirming ? (confirming = false) : close()" x-cloak class="font-plus-jakarta">
    {{-- Scrim --}}
    <div
        x-show="$store.cart.open"
        x-transition:enter="transition-opacity duration-200 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150 ease-[var(--ease-out)]"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 bg-on-surface/50 z-[60]"
    ></div>

    {{-- Panel --}}
    <aside
        x-show="$store.cart.open"
        x-transition:enter="transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-200 ease-[var(--ease-out)]"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-drawer-title"
        class="fixed top-0 right-0 h-dvh w-full sm:w-[420px] bg-surface z-[61] shadow-[-8px_0_32px_rgba(113,59,44,0.15)] flex flex-col"
    >
        <div class="flex items-center justify-between p-space-md border-b border-outline-variant/30 shrink-0">
            <h2 id="cart-drawer-title" class="font-title-md text-title-md text-on-surface flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">shopping_bag</span>
                My Sweet Cart
            </h2>
            <button type="button" @click="close()" aria-label="Close cart" class="press-scale w-9 h-9 rounded-full bg-surface-container hover:bg-surface-container-high flex items-center justify-center text-on-surface-variant transition-colors duration-200">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto p-space-md">

            <div
                x-show="orderSuccess"
                role="status"
                class="rounded-lg bg-primary-container shadow-[0_4px_16px_rgba(113,59,44,0.15)] p-space-lg text-center"
            >
                <span class="material-symbols-outlined text-[32px] text-on-primary-container">celebration</span>
                <p class="font-title-md text-title-md text-on-primary-container mt-1">Order placed!</p>
                <p class="font-body-sm text-body-sm text-on-primary-container/90 mt-1">
                    Order number: <span class="font-mono font-bold" x-text="orderSuccess"></span>
                </p>
                <template x-if="waUrl">
                    <div class="mt-space-sm">
                        <p class="font-body-sm text-body-sm text-on-primary-container/90">Opening WhatsApp so you can confirm with the shop. Just press send.</p>
                        <a :href="waUrl" class="press-scale mt-space-xs inline-flex items-center gap-space-xs rounded-full bg-on-primary-container px-space-lg py-space-xs font-label-lg text-label-lg text-primary-container">
                            <span class="material-symbols-outlined text-[18px]">forum</span>
                            Open WhatsApp
                        </a>
                    </div>
                </template>
                <p x-show="!waUrl" class="font-body-sm text-body-sm text-on-primary-container/90 mt-1">The shop will message you on WhatsApp to confirm and arrange payment.</p>
                <button type="button" @click="close()" class="mt-space-md font-label-lg text-label-lg text-on-primary-container underline decoration-on-primary-container/50 underline-offset-2 hover:decoration-on-primary-container transition-colors duration-200">
                    Keep browsing
                </button>
            </div>

            <div x-show="!orderSuccess && $store.cart.items.length === 0" class="flex flex-col items-center justify-center text-center gap-space-sm py-space-xl">
                <span class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-[32px]">shopping_bag</span>
                </span>
                <p class="font-title-md text-title-md text-on-surface">Your cart is empty</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant max-w-[240px]">Head to Brread! Studio to build your first ice cream sandwich.</p>
                <a
                    href="{{ route('build') }}"
                    @click="close()"
                    class="press-scale mt-space-xs inline-flex items-center gap-space-xs rounded-full bg-primary-container text-on-primary-container px-space-lg py-space-sm font-label-lg text-label-lg shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 transition-[opacity,transform] duration-200"
                >
                    <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                    Start Building
                </a>
            </div>

            <div x-show="!orderSuccess && $store.cart.items.length > 0" class="flex flex-col gap-space-sm">
                <template x-for="item in $store.cart.items" :key="item.uid">
                    <div class="rounded border bg-surface-container-lowest p-space-sm" :class="$store.cart.lineProblem(item) ? 'border-error/60' : 'border-outline-variant/30'">
                        <div class="flex gap-space-sm">
                            <div class="flex-1 min-w-0">
                                <p class="flex items-center gap-1.5 font-label-lg text-label-lg text-on-surface min-w-0" :title="item.bread.name + ' · ' + item.flavor.name">
                                    <span x-show="window.ingredientIcon(item.flavor.name)" class="relative w-4 h-4 rounded-full overflow-hidden flex-none"><svg class="ing-icon ing-swatch absolute inset-0" aria-hidden="true" focusable="false"><use :href="'#' + window.ingredientIcon(item.flavor.name)"></use></svg></span>
                                    <span class="truncate" x-text="item.bread.name + ' · ' + item.flavor.name"></span>
                                </p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant truncate" :title="item.toppings.map((t) => t.name).join(', ')">
                                    <span x-show="!item.toppings.length">No toppings</span>
                                    {{-- Each topping with its icon, comma separated like before. --}}
                                    <template x-for="(topping, index) in item.toppings" :key="topping.id">
                                        <span><span x-show="index">, </span><svg x-show="window.ingredientIcon(topping.name)" class="ing-icon text-[15px] mr-0.5" aria-hidden="true" focusable="false"><use :href="'#' + window.ingredientIcon(topping.name)"></use></svg><span x-text="topping.name"></span></span>
                                    </template>
                                </p>
                            </div>
                            <button type="button" @click="$store.cart.remove(item.uid)" aria-label="Remove from cart" class="press-scale w-8 h-8 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors duration-200 shrink-0">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between mt-space-xs">
                            <div class="flex items-center gap-1.5 bg-surface-container rounded-full p-0.5">
                                <button type="button" @click="$store.cart.setQuantity(item.uid, item.quantity - 1)" :disabled="item.quantity <= 1" aria-label="Decrease quantity" class="press-scale w-7 h-7 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <span class="material-symbols-outlined text-[16px]">remove</span>
                                </button>
                                <span class="font-label-lg text-label-lg text-on-surface w-5 text-center tabular-nums" x-text="item.quantity"></span>
                                <button type="button" @click="$store.cart.setQuantity(item.uid, item.quantity + 1)" :disabled="!$store.cart.canIncrease(item)" aria-label="Increase quantity" class="press-scale w-7 h-7 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <span class="material-symbols-outlined text-[16px]">add</span>
                                </button>
                            </div>
                            <span class="font-label-lg text-label-lg text-primary tabular-nums" x-text="formatPrice($store.cart.lineTotal(item))"></span>
                        </div>
                        <p x-show="$store.cart.lineProblem(item)" class="mt-space-xs font-body-sm text-body-sm text-error" x-text="$store.cart.lineProblem(item)"></p>
                    </div>
                </template>
            </div>
        </div>

        <form
            x-show="!orderSuccess && $store.cart.items.length > 0"
            @submit.prevent="review()"
            class="p-space-md border-t border-outline-variant/30 flex flex-col gap-space-sm shrink-0"
        >
            <div class="flex justify-between items-center">
                <span class="font-title-md text-title-md text-on-surface">Total</span>
                <span class="font-headline-sm text-headline-sm text-primary tabular-nums" x-text="formatPrice($store.cart.total)"></span>
            </div>
            <div>
                <label for="cart-name" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                <input
                    id="cart-name"
                    type="text"
                    x-model="customerName"
                    data-filter="name"
                    autocomplete="name"
                    maxlength="255"
                    placeholder="Your name"
                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                >
            </div>
            <div>
                <label for="cart-phone" class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone Number</label>
                <input
                    id="cart-phone"
                    type="tel"
                    inputmode="tel"
                    x-model="customerPhone"
                    data-filter="phone"
                    autocomplete="tel"
                    maxlength="30"
                    placeholder="+62 812 3456 7890"
                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                >
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                We use your name and number only to prepare and deliver this order.
                <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="underline hover:text-primary">Privacy</a>
            </p>
            @php($shopNow = \App\Support\ShopSettings::get())
            @unless ($shopNow['orders_open'])
                {{-- Orders paused from the panel (Website): the cart stays, checkout waits. --}}
                <p role="status" class="flex items-start gap-space-xs rounded-2xl border-2 border-on-surface bg-[#ffd166] px-space-md py-space-sm font-body-sm text-body-sm font-semibold text-on-surface">
                    <span class="material-symbols-outlined text-[20px] shrink-0">storefront</span>
                    <span>{{ $shopNow['orders_paused_message'] }}</span>
                </p>
            @endunless
            <p x-show="errorMessage" role="alert" class="font-body-sm text-body-sm text-error font-medium" x-text="errorMessage"></p>
            <button
                type="submit"
                @disabled(! $shopNow['orders_open'])
                :disabled="{{ $shopNow['orders_open'] ? 'false' : 'true' }} || submitting || $store.cart.hasProblem"
                class="w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-[opacity,transform] duration-200 hover:opacity-95 active:translate-y-[2px] disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <span x-show="!submitting" class="flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                    Checkout
                </span>
                <span x-show="submitting">Placing order...</span>
            </button>
        </form>
    </aside>

    {{-- Check name and phone before the order goes out --}}
    <div
        x-show="confirming"
        x-cloak
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="order-confirm-title"
        class="fixed inset-0 z-[70] flex items-center justify-center p-space-md"
    >
        <div
            x-show="confirming"
            x-transition.opacity.duration.150ms
            @click="confirming = false"
            class="absolute inset-0 bg-on-surface/50"
        ></div>
        <div
            x-show="confirming"
            x-transition:enter="transition duration-200 ease-[var(--ease-out)]"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative w-full max-w-sm rounded-3xl bg-surface-container-lowest p-space-lg shadow-[0_16px_48px_rgba(113,59,44,0.25)] flex flex-col gap-space-md"
        >
            <div class="flex flex-col items-center text-center gap-space-xs">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-container text-on-primary-container">
                    <span class="material-symbols-outlined text-[26px]">verified_user</span>
                </span>
                <h2 id="order-confirm-title" class="font-headline-sm text-headline-sm text-on-surface">Is this correct?</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Check your details. After you confirm, WhatsApp opens with your order ready to send to the shop.</p>
            </div>

            <dl class="flex flex-col gap-space-xs">
                <div class="flex items-center gap-space-sm rounded-2xl bg-surface-container px-space-md py-space-sm">
                    <span class="material-symbols-outlined text-[22px] text-primary shrink-0">person</span>
                    <div class="min-w-0">
                        <dt class="font-label-md text-label-md text-on-surface-variant">Name</dt>
                        <dd class="font-title-md text-title-md text-on-surface break-words" x-text="customerName.trim()"></dd>
                    </div>
                </div>
                <div class="flex items-center gap-space-sm rounded-2xl bg-surface-container px-space-md py-space-sm">
                    <span class="material-symbols-outlined text-[22px] text-primary shrink-0">call</span>
                    <div class="min-w-0">
                        <dt class="font-label-md text-label-md text-on-surface-variant">Phone number</dt>
                        <dd class="font-title-md text-title-md text-on-surface tabular-nums break-words" x-text="customerPhone.trim()"></dd>
                    </div>
                </div>
            </dl>

            <div class="flex justify-between items-center px-space-xs">
                <span class="font-label-md text-label-md text-on-surface-variant" x-text="$store.cart.items.length + ($store.cart.items.length === 1 ? ' creation' : ' creations')"></span>
                <span class="font-title-md text-title-md text-primary tabular-nums" x-text="formatPrice($store.cart.total)"></span>
            </div>

            <div class="flex flex-col gap-space-xs">
                {{-- One button per admin: the customer picks who to chat with. No admin number set: just place the order. --}}
                <template x-if="admins.length === 0">
                    <button
                        type="button"
                        @click="checkout('')"
                        class="w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-[opacity,transform] duration-200 hover:opacity-95 active:translate-y-[2px]"
                    >
                        <span class="material-symbols-outlined text-[20px]">check</span>
                        Confirm order
                    </button>
                </template>
                <template x-for="admin in admins" :key="admin.url">
                    <button
                        type="button"
                        @click="checkout(admin.url)"
                        class="w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-[opacity,transform] duration-200 hover:opacity-95 active:translate-y-[2px]"
                    >
                        <span class="material-symbols-outlined text-[20px]">forum</span>
                        <span x-text="admins.length > 1 ? 'Confirm and chat ' + admin.name : 'Confirm and chat on WhatsApp'"></span>
                    </button>
                </template>
                <button
                    type="button"
                    @click="confirming = false; $nextTick(() => document.getElementById('cart-name')?.focus())"
                    class="w-full flex items-center justify-center gap-space-xs rounded-full bg-surface-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-surface transition-colors duration-200 hover:bg-surface-container-highest active:translate-y-[2px]"
                >
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                    Edit details
                </button>
            </div>
        </div>
    </div>
</div>
