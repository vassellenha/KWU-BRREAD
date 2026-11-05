@extends('layouts.app')

@section('title', 'Explore Menu')

@section('content')

    <section
        x-data="{
            loading: true,
            components: { bread: [], ice_cream: [], topping: [] },
            async init() {
                try {
                    const res = await axios.get('/api/components');
                    this.components = res.data;
                } finally {
                    this.loading = false;
                }
            },
            formatPrice(n) {
                return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
            },
        }"
        class="w-full font-plus-jakarta py-space-xl"
    >
        <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
            <div class="text-center mb-space-xl" data-reveal>
                <span class="inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container px-space-md py-space-xs rounded-full font-label-lg text-label-lg mb-space-md">
                    <span class="material-symbols-outlined text-[18px]">restaurant_menu</span>
                    Explore Menu
                </span>
                <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface tracking-tight">Every Ingredient, In One Place</h1>
                <p class="mt-space-sm font-body-lg text-body-lg text-on-surface-variant max-w-xl mx-auto">
                    Every base, flavor, and topping available in the builder — browse before you build.
                </p>
            </div>

            <div x-show="loading" class="text-center font-body-lg text-body-lg text-on-surface-variant py-space-xl">Loading menu...</div>

            <div x-show="!loading" x-cloak class="space-y-space-xl">
                <div data-reveal>
                    <h2 class="flex items-center gap-space-xs font-title-md text-title-md text-on-surface mb-space-lg">
                        <span class="material-symbols-outlined hover-wiggle text-[22px] text-primary">bakery_dining</span>
                        Bread Bases
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-space-md" data-reveal-group>
                        <template x-for="item in components.bread" :key="item.id">
                            <div class="hover-lift relative rounded-DEFAULT border border-outline-variant/40 bg-surface-container-lowest p-space-md text-center shadow-[0_2px_8px_rgba(113,59,44,0.04)] transition-shadow duration-200 hover:shadow-[0_8px_20px_rgba(113,59,44,0.1)]" :class="item.stock_quantity <= 0 ? 'opacity-60' : ''" data-reveal>
                                <span x-show="item.stock_quantity <= 0" x-cloak class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-on-surface/80 text-surface font-label-md text-label-md z-10">Sold Out</span>
                                <img :src="window.INGREDIENT_IMAGES[item.name]" class="w-full h-20 object-contain mb-space-sm" :class="item.stock_quantity <= 0 ? 'grayscale' : ''" alt="">
                                <p class="font-label-lg text-label-lg text-on-surface" x-text="item.name"></p>
                                <p class="font-price-tag text-price-tag text-primary" x-text="formatPrice(item.price)"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div data-reveal>
                    <h2 class="flex items-center gap-space-xs font-title-md text-title-md text-on-surface mb-space-lg">
                        <span class="material-symbols-outlined hover-wiggle text-[22px] text-primary">icecream</span>
                        Ice Cream Flavors
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-space-md" data-reveal-group>
                        <template x-for="item in components.ice_cream" :key="item.id">
                            <div class="hover-lift relative rounded-DEFAULT border border-outline-variant/40 bg-surface-container-lowest p-space-md text-center shadow-[0_2px_8px_rgba(113,59,44,0.04)] transition-shadow duration-200 hover:shadow-[0_8px_20px_rgba(113,59,44,0.1)]" :class="item.stock_quantity <= 0 ? 'opacity-60' : ''" data-reveal>
                                <span x-show="item.stock_quantity <= 0" x-cloak class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-on-surface/80 text-surface font-label-md text-label-md z-10">Sold Out</span>
                                <img :src="window.INGREDIENT_IMAGES[item.name]" class="w-full h-20 object-contain mb-space-sm" :class="item.stock_quantity <= 0 ? 'grayscale' : ''" alt="">
                                <p class="font-label-lg text-label-lg text-on-surface" x-text="item.name"></p>
                                <p class="font-price-tag text-price-tag text-primary" x-text="formatPrice(item.price)"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div data-reveal>
                    <h2 class="flex items-center gap-space-xs font-title-md text-title-md text-on-surface mb-space-lg">
                        <span class="material-symbols-outlined hover-wiggle text-[22px] text-primary">cookie</span>
                        Toppings
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-space-md" data-reveal-group>
                        <template x-for="item in components.topping" :key="item.id">
                            <div class="hover-lift relative rounded-DEFAULT border border-outline-variant/40 bg-surface-container-lowest p-space-md text-center shadow-[0_2px_8px_rgba(113,59,44,0.04)] transition-shadow duration-200 hover:shadow-[0_8px_20px_rgba(113,59,44,0.1)]" :class="item.stock_quantity <= 0 ? 'opacity-60' : ''" data-reveal>
                                <span x-show="item.stock_quantity <= 0" x-cloak class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-on-surface/80 text-surface font-label-md text-label-md z-10">Sold Out</span>
                                <img :src="window.INGREDIENT_IMAGES[item.name]" class="w-full h-20 object-contain mb-space-sm" :class="item.stock_quantity <= 0 ? 'grayscale' : ''" alt="">
                                <p class="font-label-lg text-label-lg text-on-surface" x-text="item.name"></p>
                                <p class="font-price-tag text-price-tag text-primary" x-text="formatPrice(item.price)"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="text-center mt-space-xl" data-reveal>
                <a href="{{ route('build') }}" class="press-scale inline-flex items-center gap-space-xs justify-center rounded-full bg-primary-container text-on-primary-container px-space-lg py-space-sm font-label-lg text-label-lg shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 transition-[opacity,transform] duration-200">
                    <span class="material-symbols-outlined text-[20px]">auto_awesome</span>
                    Start Building Your Own
                </a>
            </div>
        </div>
    </section>

@endsection
