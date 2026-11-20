@extends('layouts.app')

@section('title', 'Menu')

@section('description', 'Browse every Brread! combo: white or wheat bread, vanilla, chocolate or strawberry ice cream, and toppings. Filter by bread and order online.')

@section('content')

    <div
        x-data="{
            loading: true,
            loadFailed: false,
            components: { bread: [], ice_cream: [], topping: [] },
            {{-- How each ingredient is dressed on this page. Anything new in the catalog falls back to a plain tile. --}}
            looks: {
                'Plain White Bread': { cut: 'bread-white', bg: '#fbe9d0', ink: '#5a3a12', note: 'the classic', desc: 'Soft, a little sweet, and it soaks up the first melt.', tilt: '-5deg' },
                'Whole Wheat Bread': { cut: 'bread-wheat', bg: '#e9c9a0', ink: '#4a2c0c', note: 'less sweet', desc: 'Nutty and a bit firmer. Holds up to chocolate.', tilt: '4deg' },
                'Vanilla': { cut: 'ice-vanilla', bg: '#f6e7bd', ink: '#4d3a05', note: 'flavor no. 1', desc: 'Speckled with real vanilla.', tilt: '-6deg' },
                'Strawberry': { cut: 'ice-strawberry', bg: '#f7a3b6', ink: '#5b0f22', note: 'flavor no. 2', desc: 'A little tart, with real fruit in it.', tilt: '5deg' },
                'Chocolate': { cut: 'ice-chocolate', bg: '#4a2c1a', ink: '#f6e3cf', note: 'flavor no. 3', desc: 'Dark and not too sweet.', tilt: '-4deg' },
                'Neapolitan': { cut: 'ice-neapolitan', bg: '#f4c3cf', ink: '#4a1f24', note: 'flavor no. 4', desc: 'Vanilla, strawberry and chocolate side by side in one slab.', tilt: '6deg' },
                'Durian Musang King': { cut: 'ice-durian-musang-king', bg: '#e3e6a4', ink: '#3b3f0c', note: 'flavor no. 5', desc: 'Rich Musang King durian cream with chunks of golden custard.', tilt: '-5deg' },
                'Es Teler': { cut: 'ice-es-teler', bg: '#f8d27e', ink: '#4a3005', note: 'flavor no. 6', desc: 'Mango, strawberry and pandan with coconut, jackfruit and avocado.', tilt: '4deg' },
                'Chocolate Sauce': { cut: 'ice-vanilla', top: 'top-sauce', bg: '#d9b38c', ink: '#3d1b13', note: 'drizzled on', desc: 'Glossy dark chocolate, poured over the top.', tilt: '4deg' },
                'Rainbow Sprinkles': { cut: 'ice-strawberry', top: 'top-sprinkles', bg: '#cfe6ea', ink: '#1f4a52', note: 'for the crunch', desc: 'A handful of colour on every bite.', tilt: '-5deg' },
            },
            lookFor(item) {
                const generic = { bread: 'bread-white', ice_cream: 'ice-vanilla' }[item.category];
                return this.looks[item.name] ?? { cut: generic, bg: '#f7ece2', ink: '#201b15', note: '', desc: '', tilt: '0deg' };
            },
            async init() {
                try {
                    const res = await axios.get('/api/components');
                    this.components = res.data;
                } catch (e) {
                    this.loadFailed = true;
                } finally {
                    this.loading = false;
                }
            },
            get startsAt() {
                const cheapest = (list) => Math.min(...list.map((item) => Number(item.price)));
                if (!this.components.bread.length || !this.components.ice_cream.length) return null;
                return cheapest(this.components.bread) + cheapest(this.components.ice_cream);
            },
            get summary() {
                const count = (list, one, many) => list.length + ' ' + (list.length === 1 ? one : many);
                const c = this.components;
                if (!c.bread.length && !c.ice_cream.length && !c.topping.length) return 'Breads, ice creams and toppings.';
                return count(c.bread, 'bread', 'breads') + ', ' + count(c.ice_cream, 'ice cream', 'ice creams') + ', ' + count(c.topping, 'topping', 'toppings') + '.';
            },
            formatPrice(n) {
                return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
            },
        }"
        class="w-full font-plus-jakarta"
    >
        {{-- OPENING --}}
        <section class="doodle relative bg-surface-container-low pt-44 pb-space-xl">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-end">
                <div class="lg:col-span-7" data-reveal>
                    <p class="font-hand text-[30px] leading-none text-primary -rotate-2 origin-left mb-space-sm">the menu</p>
                    <h1 class="font-headline-lg font-extrabold text-on-surface tracking-tight text-[clamp(3rem,9vw,7rem)] leading-[0.92]">
                        Short on
                        <span class="relative inline-block text-primary italic">purpose.
                            <svg class="scribble absolute left-0 -bottom-[0.1em] w-full h-[0.2em]" viewBox="0 0 400 22" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
                                <path d="M4 14C70 4 120 20 190 11s130-8 206 2" stroke="#f3c969" stroke-width="7" stroke-linecap="round" />
                            </svg>
                        </span>
                    </h1>
                </div>
                <div class="lg:col-span-5" data-reveal>
                    <p class="font-body-lg text-[18px] leading-[28px] text-on-surface-variant">
                        <span x-text="summary">Breads, ice creams and toppings.</span> Pick a bread and an ice cream, add a topping
                        if you want one, and that's your sandwich.
                    </p>
                    <p x-show="startsAt" x-cloak class="mt-space-md inline-flex items-baseline gap-2 bg-surface-container-lowest border border-outline-variant rounded-full pl-space-md pr-space-lg py-2 -rotate-1">
                        <span class="font-hand text-[24px] leading-none text-primary">starts at</span>
                        <span class="font-headline-sm text-[24px] leading-none font-extrabold text-on-surface" x-text="formatPrice(startsAt)"></span>
                    </p>
                </div>
            </div>
        </section>

        <div x-show="loading" class="text-center font-body-lg text-body-lg text-on-surface-variant py-24">Loading menu…</div>
        <div x-show="loadFailed" x-cloak role="alert" class="text-center font-body-lg text-body-lg text-on-surface-variant py-24">The menu could not load. Please refresh the page.</div>

        <div x-show="!loading && !loadFailed" x-cloak>
            @foreach ([
                ['key' => 'bread', 'n' => '1', 'title' => 'Pick a bread', 'note' => 'choose one', 'grid' => 'md:grid-cols-2', 'bg' => 'bg-surface', 'drip' => 'chocolate', 'seed' => 0],
                ['key' => 'ice_cream', 'n' => '2', 'title' => 'Pick an ice cream', 'note' => 'choose one', 'grid' => 'md:grid-cols-3', 'bg' => 'bg-surface-container-low doodle', 'drip' => 'vanilla', 'seed' => 1],
                ['key' => 'topping', 'n' => '3', 'title' => 'Top it off', 'note' => 'optional, take both if you like', 'grid' => 'md:grid-cols-2', 'bg' => 'bg-surface', 'drip' => 'strawberry', 'seed' => 2],
            ] as $group)
                <section class="relative {{ $group['bg'] }} pt-44 pb-space-xl">
                    @include('partials.drip', ['flavor' => $group['drip'], 'seed' => $group['seed']])
                    <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                        <div class="flex items-end gap-space-md mb-space-lg" data-reveal>
                            <span class="font-hand text-[96px] leading-[0.7] text-primary-container -rotate-6" aria-hidden="true">{{ $group['n'] }}</span>
                            <div>
                                <h2 class="font-headline-lg text-[clamp(2rem,5vw,3.25rem)] leading-none font-extrabold text-on-surface">{{ $group['title'] }}</h2>
                                <p class="font-hand text-[24px] leading-none text-primary mt-1">{{ $group['note'] }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 {{ $group['grid'] }} gap-space-lg" data-reveal-group>
                            <template x-for="item in components.{{ $group['key'] }}" :key="item.id">
                                <a
                                    href="{{ route('build') }}"
                                    class="menu-tile"
                                    :class="item.stock_quantity <= 0 ? 'is-sold-out' : ''"
                                    :style="{ '--bg': lookFor(item).bg, '--ink': lookFor(item).ink, '--tilt': lookFor(item).tilt }"
                                    :data-tip="item.stock_quantity <= 0 ? 'Out of stock right now. Check back soon.' : 'Build a sandwich with ' + item.name"
                                    data-reveal
                                >
                                    <span class="menu-tile-price" x-text="formatPrice(item.price)"></span>
                                    <span x-show="item.stock_quantity <= 0" x-cloak class="menu-tile-stamp font-hand">sold out</span>
                                    <span class="menu-tile-art">
                                        <img x-show="lookFor(item).cut" :src="lookFor(item).cut ? '/img/cutout/' + lookFor(item).cut + '.webp' : window.ingredientImage(item)" alt="" loading="lazy">
                                        <img x-show="!lookFor(item).cut && window.ingredientImage(item)" :src="window.ingredientImage(item)" alt="" loading="lazy">
                                        <template x-if="lookFor(item).top">
                                            <img class="menu-tile-top" :src="'/img/cutout/' + lookFor(item).top + '.webp'" alt="">
                                        </template>
                                    </span>
                                    <span class="menu-tile-text">
                                        <span class="font-hand text-[24px] leading-none opacity-80" x-text="lookFor(item).note"></span>
                                        <span class="menu-tile-name" x-text="item.name"></span>
                                        <span class="menu-tile-desc" x-text="lookFor(item).desc"></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        {{-- CLOSING --}}
        <section class="relative bg-primary text-on-primary pt-48 pb-24 text-center overflow-hidden">
            @include('partials.drip', ['flavor' => 'cream', 'seed' => 1])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop" data-reveal>
                <p class="font-hand text-[30px] leading-none text-secondary-fixed -rotate-2 mb-space-sm">picked your three?</p>
                <h2 class="font-headline-lg font-extrabold tracking-tight text-[clamp(2.75rem,8vw,6rem)] leading-[0.95]">Stack it in 3D.</h2>
                <p class="mt-space-sm font-body-lg text-body-lg text-on-primary/85 max-w-md mx-auto">The Studio shows your sandwich as you build it. Turn it around, pull the layers apart, then order.</p>
                <a href="{{ route('build') }}" class="btn-chunky inline-flex mt-space-lg bg-surface-container-lowest text-primary px-space-xl py-4 rounded-full font-label-lg text-[16px]" style="--chunk: #6f1326">Open the Studio</a>
            </div>
        </section>
    </div>

@endsection
