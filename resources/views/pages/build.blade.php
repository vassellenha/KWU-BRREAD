@extends('layouts.app')

@section('title', 'Brread! Studio')

@section('content')

    <main x-data="brreadBuilder()" class="w-full font-plus-jakarta">

        <div class="max-w-[1440px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop py-space-lg flex flex-col gap-space-lg">

            {{-- IDENTITY BANNER --}}
            <div class="bg-surface-container-lowest rounded-lg p-space-lg border border-outline-variant/30 shadow-[0_4px_16px_rgba(113,59,44,0.06)] flex flex-col lg:flex-row lg:items-center justify-between gap-space-md" data-reveal>
                <div class="flex items-center gap-space-md">
                    <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-secondary-fixed to-tertiary-fixed flex items-center justify-center text-primary shadow-inner shrink-0">
                        <span class="material-symbols-outlined text-[30px]">auto_awesome</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-space-xs flex-wrap">
                            <h1 class="font-headline-md text-headline-md text-on-surface tracking-tight">Brread! Studio</h1>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-tertiary animate-pulse"></span>
                                Live Preview
                            </span>
                        </div>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-1 max-w-lg">
                            Pick your base, your flavor, and your toppings — watch it come together live, then order it fresh.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-space-sm flex-wrap">
                    <div class="flex items-center gap-1.5 px-space-md py-space-xs bg-surface-container rounded-full font-label-md text-label-md text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px] text-primary">verified</span>
                        100% Halal Certified
                    </div>
                    <div class="flex items-center gap-1.5 px-space-md py-space-xs bg-surface-container rounded-full font-label-md text-label-md text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px] text-tertiary">ac_unit</span>
                        Cold-Chain Jakarta Delivery
                    </div>
                </div>
            </div>

            {{-- STEP PROGRESS STRIP --}}
            <div class="bg-surface-container-lowest rounded-lg p-space-sm border border-outline-variant/30 shadow-sm">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-space-sm">
                    <template x-for="(tab, index) in tabs" :key="tab.key">
                        <button
                            type="button"
                            @click="activeTab = tab.key"
                            class="press-scale flex items-center gap-space-sm p-space-sm rounded-DEFAULT text-left transition-colors duration-200"
                            :class="activeTab === tab.key ? 'bg-primary-container/15 border border-primary-container' : 'border border-transparent hover:bg-surface-container'"
                        >
                            <span
                                class="w-8 h-8 rounded-full flex items-center justify-center font-label-lg text-label-lg shrink-0"
                                :class="isStepDone(tab.key) ? 'bg-primary text-on-primary' : (activeTab === tab.key ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant')"
                            >
                                <span x-show="isStepDone(tab.key)" x-cloak class="material-symbols-outlined text-[16px]">check</span>
                                <span x-show="!isStepDone(tab.key)" x-text="index + 1"></span>
                            </span>
                            <div class="flex flex-col leading-tight">
                                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Step <span x-text="index + 1"></span></span>
                                <span class="font-label-lg text-label-lg text-on-surface" x-text="tab.label"></span>
                            </div>
                        </button>
                    </template>
                    <div class="flex items-center gap-space-sm p-space-sm rounded-DEFAULT border border-transparent">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center font-label-lg text-label-lg shrink-0" :class="customerName && customerPhone ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'">
                            <span x-show="customerName && customerPhone" x-cloak class="material-symbols-outlined text-[16px]">check</span>
                            <span x-show="!(customerName && customerPhone)" x-cloak>4</span>
                        </span>
                        <div class="flex flex-col leading-tight">
                            <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Step 4</span>
                            <span class="font-label-lg text-label-lg text-on-surface">Name &amp; Order</span>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="loading" class="text-center py-space-xl font-body-lg text-body-lg text-on-surface-variant">
                Loading ingredients...
            </div>

            {{-- 3-COLUMN WORKSPACE --}}
            <div x-show="!loading" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-start">

                {{-- LEFT: Ingredient selectors --}}
                <div class="lg:col-span-4 flex flex-col gap-space-md">

                    {{-- Signature presets --}}
                    <div class="bg-gradient-to-br from-secondary-fixed/40 via-surface-container-lowest to-tertiary-fixed/30 rounded-lg p-space-md border border-outline-variant/30 shadow-sm">
                        <div class="flex items-center gap-1.5 mb-space-sm">
                            <span class="material-symbols-outlined text-[16px] text-primary">stars</span>
                            <span class="font-label-md text-label-md text-on-surface uppercase tracking-wide">Signature Combos — 1-Click</span>
                        </div>
                        <div class="grid grid-cols-2 gap-space-xs">
                            <template x-for="preset in presets" :key="preset.name">
                                <button
                                    type="button"
                                    @click="applyPreset(preset, $event)"
                                    class="press-scale hover-lift relative flex items-start gap-space-xs p-space-sm rounded-DEFAULT text-left transition-[background-color,border-color,box-shadow] duration-200 border-2"
                                    :class="isActivePreset(preset) ? 'bg-primary-container/15 border-primary shadow-[0_4px_14px_rgba(199,70,89,0.22)]' : 'bg-surface-container-lowest border-transparent hover:border-outline-variant'"
                                >
                                    <span
                                        class="w-7 h-7 rounded-full shrink-0 mt-0.5 border border-outline-variant/60 flex items-center justify-center transition-transform duration-200"
                                        :class="isActivePreset(preset) ? 'scale-110' : ''"
                                        :style="{ backgroundColor: presetSwatch(preset) }"
                                    >
                                        <span x-show="isActivePreset(preset)" x-cloak class="material-symbols-outlined text-[14px] text-white drop-shadow">check</span>
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block font-label-lg text-label-lg text-on-surface leading-tight" x-text="preset.name"></span>
                                        <span class="block font-label-md text-label-md text-on-surface-variant mt-0.5" x-text="preset.bread.replace('Plain ', '').replace('Whole ', '') + ' · ' + preset.flavor"></span>
                                        <span x-show="preset.toppings.length" class="inline-flex items-center gap-0.5 mt-1 font-label-md text-label-md text-primary">
                                            <span class="material-symbols-outlined text-[13px]">water_drop</span>
                                            <span x-text="preset.toppings.join(', ')"></span>
                                        </span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Step 1: Bread --}}
                    <div class="bg-surface-container-lowest rounded-lg p-space-md border border-outline-variant/30 shadow-sm">
                        <div class="flex items-center justify-between mb-space-sm">
                            <h2 class="font-title-md text-title-md text-on-surface">Bread &amp; Crust</h2>
                            <span class="font-label-md text-label-md text-primary px-2 py-0.5 rounded-full bg-primary-fixed">Required</span>
                        </div>
                        <div class="grid grid-cols-2 gap-space-sm">
                            <template x-for="component in components.bread" :key="component.id">
                                <button
                                    type="button"
                                    @click="selectComponent(component, $event)"
                                    class="press-scale hover-lift relative rounded-DEFAULT border-2 bg-surface-container-lowest p-space-sm text-left transition-[border-color,box-shadow] duration-200 shadow-[0_2px_8px_rgba(113,59,44,0.04)]"
                                    :class="isSelected(component) ? 'border-primary ring-2 ring-primary/30' : 'border-outline-variant/50'"
                                >
                                    <img :src="window.INGREDIENT_IMAGES[component.name]" class="w-full h-16 object-contain mb-space-xs" alt="">
                                    <span class="block font-label-lg text-label-lg text-on-surface leading-tight" x-text="component.name"></span>
                                    <span class="block font-price-tag text-price-tag text-primary mt-1" x-text="formatPrice(component.price)"></span>
                                    <span x-show="isSelected(component)" x-cloak class="material-symbols-outlined absolute top-2 right-2 text-[18px] text-primary">check_circle</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Step 2: Flavor --}}
                    <div class="bg-surface-container-lowest rounded-lg p-space-md border border-outline-variant/30 shadow-sm">
                        <div class="flex items-center justify-between mb-space-sm">
                            <h2 class="font-title-md text-title-md text-on-surface">Ice Cream Slab</h2>
                            <span class="font-label-md text-label-md text-on-surface-variant">Thick Cut</span>
                        </div>
                        <div class="grid grid-cols-3 gap-space-sm">
                            <template x-for="component in components.ice_cream" :key="component.id">
                                <button
                                    type="button"
                                    @click="selectComponent(component, $event)"
                                    class="press-scale hover-lift relative flex flex-col items-center p-space-sm rounded-DEFAULT text-center border-2 transition-[border-color,box-shadow] duration-200"
                                    :class="isSelected(component) ? 'border-primary shadow-[0_2px_8px_rgba(199,70,89,0.2)]' : 'border-outline-variant/50'"
                                >
                                    <span class="w-10 h-10 rounded-full mb-space-xs border border-outline-variant flex items-center justify-center" :style="{ backgroundColor: component.hex_color }">
                                        <span x-show="isSelected(component)" x-cloak class="material-symbols-outlined text-[16px] text-white drop-shadow">check</span>
                                    </span>
                                    <span class="font-label-md text-label-md text-on-surface leading-tight" x-text="component.name"></span>
                                    <span class="font-label-md text-label-md text-primary font-bold mt-0.5" x-text="formatPrice(component.price)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Step 3: Toppings --}}
                    <div class="bg-surface-container-lowest rounded-lg p-space-md border border-outline-variant/30 shadow-sm">
                        <div class="flex items-center justify-between mb-space-sm">
                            <h2 class="font-title-md text-title-md text-on-surface">Toppings &amp; Drizzle</h2>
                            <span class="font-label-md text-label-md text-on-surface-variant">Multi-Select</span>
                        </div>
                        <div class="flex flex-wrap gap-space-xs">
                            <template x-for="component in components.topping" :key="component.id">
                                <button
                                    type="button"
                                    @click="selectComponent(component, $event)"
                                    class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-colors duration-200 border"
                                    :class="isSelected(component) ? 'bg-primary-container text-on-primary-container border-primary-container' : 'bg-surface-container-lowest text-on-surface border-outline-variant/50 hover:border-primary'"
                                >
                                    <span class="material-symbols-outlined text-[16px]" x-text="isSelected(component) ? 'done' : 'add'"></span>
                                    <span x-text="component.name"></span>
                                    <span class="opacity-80" x-text="'+' + formatPrice(component.price)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- CENTER: Big floating stage --}}
                <div class="lg:col-span-5 flex flex-col gap-space-md">
                    <div class="relative w-full rounded-lg bg-gradient-to-b from-tertiary-fixed/50 via-surface-container-low to-secondary-fixed/30 p-space-lg flex flex-col items-center justify-between min-h-[560px] border border-outline-variant/30 shadow-[0_8px_28px_rgba(113,59,44,0.1)] overflow-hidden">

                        <div class="w-full flex items-center justify-between z-10 gap-space-sm flex-wrap">
                            <span class="px-space-md py-1 rounded-full bg-surface-container-lowest/95 backdrop-blur-md text-primary font-label-md text-label-md shadow-sm flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">auto_awesome</span>
                                Made By You
                            </span>
                            <span class="flex items-center gap-1.5 bg-surface-container-lowest/95 backdrop-blur-md px-space-md py-1 rounded-full shadow-sm font-label-md text-label-md text-on-surface">
                                <span class="w-2 h-2 rounded-full bg-tertiary animate-pulse"></span>
                                Live Recipe Synced
                            </span>
                        </div>

                        <div class="relative w-full flex-1 flex flex-col items-center justify-center my-space-md select-none">
                            <div class="absolute w-72 h-72 rounded-full bg-secondary-fixed/40 blur-3xl pointer-events-none"></div>

                            <div class="animate-float" style="width: 280px;">
                                <div
                                    x-ref="stackPreview"
                                    class="relative mx-auto flex flex-col items-center"
                                    style="width: 280px; min-height: 320px;"
                                >
                                    <img :src="breadImage" x-show="breadImage" x-cloak src="" alt="" class="w-full drop-shadow-xl relative z-30 pointer-events-none select-none transition-all duration-500" :class="explodedView ? '-mb-2' : ''">
                                    <div x-show="!breadImage" class="w-full h-20 rounded-lg bg-surface-container-high"></div>

                                    <div class="relative z-20 w-[82%] aspect-[2.6/1] transition-all duration-500" :class="explodedView ? 'mt-2 mb-2' : '-mt-8'">
                                        <img :src="flavorImage" x-show="flavorImage" x-cloak src="" alt="" class="absolute inset-0 w-full h-full object-cover rounded-md drop-shadow-xl pointer-events-none select-none">
                                        <div x-show="!flavorImage" class="w-full h-full rounded-lg bg-surface-container-highest"></div>
                                        <img :src="toppingImage('Chocolate Sauce')" x-show="hasChocolateSauce" x-cloak src="" alt="" class="absolute inset-0 w-full h-full object-contain pointer-events-none select-none">
                                        <img :src="toppingImage('Rainbow Sprinkles')" x-show="hasSprinkles" x-cloak src="" alt="" class="absolute inset-0 w-full h-full object-contain pointer-events-none select-none">
                                    </div>

                                    <img :src="breadImage" x-show="breadImage" x-cloak src="" alt="" class="w-full drop-shadow-xl relative z-10 pointer-events-none select-none transition-all duration-500" :class="explodedView ? '-mt-2' : '-mt-8'">
                                    <div x-show="!breadImage" class="w-full h-20 rounded-lg bg-surface-container-high transition-all duration-500" :class="explodedView ? '-mt-2' : '-mt-8'"></div>

                                    <div x-show="!selectedBread && !selectedFlavor" x-cloak class="absolute inset-0 flex items-center justify-center text-center font-body-sm text-body-sm text-on-surface-variant px-space-lg">
                                        Pick a base and a flavor to start stacking →
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="w-full flex flex-wrap items-center justify-between gap-space-sm bg-surface-container-lowest/95 backdrop-blur-md px-space-sm py-space-xs rounded-lg shadow-sm border border-outline-variant/30 z-10">
                            <div class="flex items-center gap-space-xs">
                                <button type="button" @click="toggleExplodedView()" class="press-scale px-space-sm py-1.5 rounded-DEFAULT hover:bg-surface-container text-on-surface font-label-md text-label-md flex items-center gap-1.5 transition-colors duration-200" :class="explodedView ? 'bg-primary-container/20 text-primary' : ''">
                                    <span class="material-symbols-outlined text-[16px] text-primary">unfold_more</span>
                                    Exploded View
                                </button>
                                <button type="button" @click="resetStage()" class="press-scale px-space-sm py-1.5 rounded-DEFAULT hover:bg-surface-container text-on-surface font-label-md text-label-md flex items-center gap-1.5 transition-colors duration-200">
                                    <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                                    Reset
                                </button>
                            </div>
                            <p class="font-body-sm text-body-sm text-on-surface-variant text-center sm:text-right">
                                <span x-show="selectedBread" x-text="selectedBread?.name"></span>
                                <span x-show="selectedBread && selectedFlavor"> · </span>
                                <span x-show="selectedFlavor" x-text="selectedFlavor?.name"></span>
                                <template x-for="topping in selectedToppings" :key="topping.id">
                                    <span> · <span x-text="topping.name"></span></span>
                                </template>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Sticky order summary --}}
                <div class="lg:col-span-3 lg:sticky lg:top-28">
                    <div class="bg-surface-container-lowest rounded-lg p-space-md border border-outline-variant/30 shadow-[0_8px_24px_rgba(113,59,44,0.08)] flex flex-col gap-space-md">
                        <div class="flex items-center justify-between pb-space-sm border-b border-outline-variant/30">
                            <div class="flex items-center gap-space-xs">
                                <span class="w-8 h-8 rounded-full bg-secondary-fixed flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                                </span>
                                <h3 class="font-title-md text-title-md text-on-surface">Your Order</h3>
                            </div>
                        </div>

                        <dl class="space-y-1.5 font-body-md text-body-md text-on-surface-variant">
                            <div class="flex justify-between" x-show="selectedBread">
                                <dt x-text="selectedBread?.name"></dt>
                                <dd x-text="formatPrice(selectedBread?.price ?? 0)"></dd>
                            </div>
                            <div class="flex justify-between" x-show="selectedFlavor">
                                <dt x-text="selectedFlavor?.name"></dt>
                                <dd x-text="formatPrice(selectedFlavor?.price ?? 0)"></dd>
                            </div>
                            <template x-for="topping in selectedToppings" :key="topping.id">
                                <div class="flex justify-between">
                                    <dt x-text="topping.name"></dt>
                                    <dd x-text="formatPrice(topping.price)"></dd>
                                </div>
                            </template>
                        </dl>

                        <div class="flex justify-between items-center border-t border-outline-variant/30 pt-space-sm">
                            <span class="font-title-md text-title-md text-on-surface">Total</span>
                            <span class="font-headline-sm text-headline-sm text-primary" x-text="formatPrice(totalPrice)"></span>
                        </div>

                        <template x-if="orderSuccess">
                            <div
                                x-transition:enter="transition-[opacity,transform] duration-500 ease-[var(--ease-bounce)]"
                                x-transition:enter-start="opacity-0 scale-90"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="rounded-DEFAULT bg-primary-container border-2 border-primary p-space-lg text-center"
                            >
                                <span class="material-symbols-outlined text-[28px] text-on-primary-container">celebration</span>
                                <p class="font-title-md text-title-md text-on-primary-container mt-1">Order placed!</p>
                                <p class="font-body-sm text-body-sm text-on-primary-container/80 mt-1">
                                    Order number: <span class="font-mono font-bold" x-text="orderSuccess"></span>
                                </p>
                                <button type="button" @click="resetOrder()" class="mt-space-md font-label-lg text-label-lg text-primary hover:underline">
                                    Build another one
                                </button>
                            </div>
                        </template>

                        <form x-show="!orderSuccess" @submit.prevent="submitOrder()" class="space-y-space-sm">
                            <div>
                                <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                                <input
                                    type="text"
                                    x-model="customerName"
                                    placeholder="Your name"
                                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                            </div>
                            <div>
                                <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone Number</label>
                                <input
                                    type="tel"
                                    x-model="customerPhone"
                                    placeholder="+62 812 3456 7890"
                                    class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                            </div>

                            <p x-show="errorMessage" x-cloak class="font-body-sm text-body-sm text-error font-medium" x-text="errorMessage"></p>

                            <button
                                type="submit"
                                :disabled="!canCheckout || submitting"
                                class="w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-[opacity,transform] duration-200 hover:opacity-95 active:translate-y-[2px] disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <span x-show="!submitting" class="flex items-center gap-space-xs">
                                    <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                                    Order Now
                                </span>
                                <span x-show="submitting" x-cloak>Placing order...</span>
                            </button>
                        </form>

                        <div class="flex items-center justify-center gap-1.5 text-on-surface-variant font-body-sm text-body-sm">
                            <span class="material-symbols-outlined text-[16px] text-tertiary">verified_user</span>
                            Packed fresh in insulated cold-chain foil
                        </div>
                    </div>
                </div>
            </div>

            {{-- BOTTOM: Trust row --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md mt-space-sm" data-reveal-group>
                <div class="bg-surface-container-lowest rounded-lg p-space-lg flex items-start gap-space-md border border-outline-variant/30 shadow-sm" data-reveal>
                    <span class="w-12 h-12 rounded-full bg-secondary-fixed flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-[24px]">bakery_dining</span>
                    </span>
                    <div>
                        <h4 class="font-title-md text-title-md text-on-surface mb-1">Authentic Fluffy Loaf</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Baked fresh daily — pick plain white for classic softness or whole wheat for a heartier, less-sweet bite.</p>
                    </div>
                </div>
                <div class="bg-surface-container-lowest rounded-lg p-space-lg flex items-start gap-space-md border border-outline-variant/30 shadow-sm" data-reveal>
                    <span class="w-12 h-12 rounded-full bg-tertiary-fixed flex items-center justify-center text-tertiary shrink-0">
                        <span class="material-symbols-outlined text-[24px]">ac_unit</span>
                    </span>
                    <div>
                        <h4 class="font-title-md text-title-md text-on-surface mb-1">Zero-Melt Delivery</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Every order is packed in chilled insulated foil, straight to your door across Jakarta.</p>
                    </div>
                </div>
                <div class="bg-surface-container-lowest rounded-lg p-space-lg flex items-start gap-space-md border border-outline-variant/30 shadow-sm" data-reveal>
                    <span class="w-12 h-12 rounded-full bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-[24px]">sentiment_very_satisfied</span>
                    </span>
                    <div>
                        <h4 class="font-title-md text-title-md text-on-surface mb-1">150,000+ Happy Scoops</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Rated 4.9/5 by sandwich fans across Jakarta — build yours and see why.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

@endsection
