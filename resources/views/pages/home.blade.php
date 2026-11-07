@extends('layouts.app')

@section('title', 'Home')

@section('content')

    <div class="w-full font-plus-jakarta" x-data="signatureMenu([
            { id: 1, badge: 'Bestseller #1', badgeClass: 'bg-primary text-on-primary', tag: 'Vanilla', category: ['white-bread'], rating: '4.9', reviews: '1.2k', name: 'Classic Vanilla Slab', desc: 'Soft plain white bread folded around a thick block of creamy Madagascar vanilla bean ice cream.', price: 14000, image: '/img/marketing/product-vanilla.jpg' },
            { id: 2, badge: 'Fan Favorite', badgeClass: 'bg-on-surface text-surface', tag: 'Strawberry + Sprinkles', category: ['white-bread'], rating: '4.8', reviews: '860', name: 'Strawberry Sprinkle Stack', desc: 'Plain white bread with real-fruit strawberry ice cream, finished with a scatter of rainbow sprinkles.', price: 17000, image: '/img/marketing/product-strawberry.jpg' },
            { id: 3, badge: 'Wholegrain', badgeClass: 'bg-tertiary text-on-tertiary', tag: 'Chocolate', category: ['wheat-bread'], rating: '4.7', reviews: '540', name: 'Wholegrain Choco Crunch', desc: 'Hearty whole wheat bread paired with rich dark chocolate ice cream for a less-sweet, grown-up bite.', price: 15000, image: '/img/marketing/product-chocolate.jpg' },
            { id: 4, badge: 'Drizzle Special', badgeClass: 'bg-primary text-on-primary', tag: 'Vanilla + Choco Drizzle', category: ['wheat-bread'], rating: '4.9', reviews: '610', name: 'Wheat Vanilla Drizzle', desc: 'Whole wheat bread and vanilla bean ice cream, drizzled generously with glossy dark chocolate sauce.', price: 18000, image: '/img/marketing/product-vanilla-wheat.jpg' },
        ])">

        {{-- HERO SECTION --}}
        <section class="relative w-full overflow-hidden bg-surface-container-low pb-space-xl">
            <div class="absolute -top-24 -left-20 w-96 h-96 rounded-full bg-secondary-fixed opacity-40 blur-3xl pointer-events-none"></div>
            <div class="absolute top-1/2 -right-32 w-[32rem] h-[32rem] rounded-full bg-tertiary-fixed opacity-30 blur-3xl pointer-events-none"></div>

            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop pt-space-lg lg:pt-space-xl">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg lg:gap-gutter-desktop items-center">

                    <div class="lg:col-span-7 flex flex-col items-start z-10" data-reveal>
                        <div class="inline-flex items-center gap-space-xs bg-surface-container-lowest px-space-md py-1 rounded-full shadow-[0_2px_8px_rgba(113,59,44,0.06)] mb-space-md">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary animate-pulse"></span>
                            <span class="font-label-md text-label-md text-primary tracking-wide uppercase">Singapore Orchard Nostalgia &bull; Handcrafted in Jakarta</span>
                        </div>
                        <h1 class="text-headline-lg-mobile md:text-display text-on-surface tracking-tight leading-none mb-space-md">
                            Your Bread.<br>
                            Your Scoop.<br>
                            <span class="text-primary italic">Your Happiness.</span>
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl mb-space-lg">
                            Mix, match, and create your dream Singapore-style ice cream sandwich with pillowy
                            soft bread, hand-sliced vanilla, strawberry or chocolate ice cream blocks, and your
                            favorite drizzle or sprinkles on top.
                        </p>
                        <div class="flex flex-wrap items-center gap-space-md mb-space-xl w-full sm:w-auto">
                            <a href="{{ route('build') }}" class="w-full sm:w-auto flex items-center justify-center gap-space-xs bg-primary-container text-on-primary-container px-space-xl py-space-sm rounded-full font-label-lg text-label-lg shadow-[0_8px_20px_rgba(199,70,89,0.35)] hover:opacity-95 active:translate-y-[2px] transition-[opacity,transform,box-shadow] duration-200 text-center">
                                <span>Build My Brread!</span>
                                <span class="material-symbols-outlined text-[20px]">icecream</span>
                            </a>
                            <a href="#flavor-section" class="w-full sm:w-auto flex items-center justify-center gap-space-xs bg-surface-container-lowest text-on-surface px-space-lg py-space-sm rounded-full font-label-lg text-label-lg shadow-[0_4px_14px_rgba(113,59,44,0.08)] hover:bg-surface-container transition-colors duration-200 text-center">
                                <span>Explore Flavors</span>
                                <span class="material-symbols-outlined text-[18px] text-primary">arrow_forward</span>
                            </a>
                        </div>

                        <div class="flex items-center gap-space-md bg-surface-container/70 p-space-sm rounded backdrop-blur-md">
                            <div class="flex -space-x-2">
                                <span class="w-8 h-8 rounded-full bg-secondary-fixed flex items-center justify-center text-primary ring-2 ring-surface-container-lowest">
                                    <span class="material-symbols-outlined text-[16px]">icecream</span>
                                </span>
                                <span class="w-8 h-8 rounded-full bg-tertiary-fixed flex items-center justify-center text-tertiary ring-2 ring-surface-container-lowest">
                                    <span class="material-symbols-outlined text-[16px]">bakery_dining</span>
                                </span>
                                <span class="w-8 h-8 rounded-full bg-primary-fixed flex items-center justify-center text-primary ring-2 ring-surface-container-lowest">
                                    <span class="material-symbols-outlined text-[16px]">nutrition</span>
                                </span>
                            </div>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1 text-primary">
                                    @for ($i = 0; $i < 5; $i++)
                                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    @endfor
                                </div>
                                <span class="font-label-md text-label-md text-on-surface-variant font-bold">4.9/5 from 8,400+ sandwich fans</span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5 relative flex justify-center items-center mt-space-lg lg:mt-0" data-reveal>
                        <div class="w-72 h-72 sm:w-96 sm:h-96 rounded-full bg-tertiary-fixed/60 flex items-center justify-center p-space-lg shadow-[0_12px_36px_rgba(113,59,44,0.12)] relative">
                            <img
                                class="w-full h-full object-cover rounded-full shadow-[0_8px_24px_rgba(113,59,44,0.18)] hover:scale-105 transition-transform duration-500"
                                alt="Close up of a Singapore ice cream sandwich held in hand, plain white bread with a strawberry ice cream block and rainbow sprinkles"
                                src="/img/marketing/hero-held.jpg"
                                loading="eager"
                            >
                            <div class="absolute -top-4 -right-4 bg-surface-container-lowest px-space-md py-space-xs rounded-full shadow-[0_6px_20px_rgba(113,59,44,0.14)] flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[18px] text-primary">ac_unit</span>
                                <span class="font-label-md text-label-md text-on-surface">Sub-Zero Cold Chain</span>
                            </div>
                            <div class="absolute -bottom-3 -left-4 bg-surface-container-lowest p-space-sm rounded shadow-[0_6px_20px_rgba(113,59,44,0.14)] flex items-center gap-space-sm">
                                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-primary font-bold">
                                    <span class="material-symbols-outlined text-[20px]">bakery_dining</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-label-md text-label-md text-on-surface">Pillowy Soft</span>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Daily Baked Loaf</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop mt-space-xl" data-reveal>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-space-md bg-surface-container-lowest p-space-lg rounded-lg shadow-[0_4px_20px_rgba(113,59,44,0.06)]">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-12 h-12 rounded-full bg-secondary-fixed flex items-center justify-center text-primary flex-shrink-0">
                            <span class="material-symbols-outlined text-[24px]">sentiment_very_satisfied</span>
                        </div>
                        <div>
                            <div class="font-headline-sm text-headline-sm text-on-surface">150,000+</div>
                            <div class="font-body-sm text-body-sm text-on-surface-variant">Happy Scoops Served</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-sm">
                        <div class="w-12 h-12 rounded-full bg-tertiary-fixed flex items-center justify-center text-tertiary flex-shrink-0">
                            <span class="material-symbols-outlined text-[24px]">verified</span>
                        </div>
                        <div>
                            <div class="font-headline-sm text-headline-sm text-on-surface">100% Halal</div>
                            <div class="font-body-sm text-body-sm text-on-surface-variant">Certified Ingredients</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-sm">
                        <div class="w-12 h-12 rounded-full bg-primary-fixed flex items-center justify-center text-primary flex-shrink-0">
                            <span class="material-symbols-outlined text-[24px]">timer</span>
                        </div>
                        <div>
                            <div class="font-headline-sm text-headline-sm text-on-surface">20 Mins</div>
                            <div class="font-body-sm text-body-sm text-on-surface-variant">Rapid Ice-Cold Delivery</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-sm">
                        <div class="w-12 h-12 rounded-full bg-secondary-fixed-dim/60 flex items-center justify-center text-on-secondary-fixed flex-shrink-0">
                            <span class="material-symbols-outlined text-[24px]">thermostat</span>
                        </div>
                        <div>
                            <div class="font-headline-sm text-headline-sm text-on-surface">60 Min Chill</div>
                            <div class="font-body-sm text-body-sm text-on-surface-variant">Insulated Thermal Foil</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- HOW IT WORKS --}}
        <section class="w-full py-space-xl bg-surface">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="text-center max-w-xl mx-auto mb-space-xl" data-reveal>
                    <span class="font-label-md text-label-md text-primary uppercase tracking-wider bg-primary-fixed px-space-md py-1 rounded-full inline-block mb-space-xs">Simple &amp; Sweet</span>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">How Your Sandwich Comes Alive</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-2">Just like standing in front of an Uncle Cart on Orchard Road, but crafted right from your palm.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg" data-reveal-group>
                    <div class="bg-surface-container rounded-lg p-space-lg flex flex-col relative overflow-hidden shadow-[0_4px_16px_rgba(113,59,44,0.06)] hover:-translate-y-1 transition-transform" data-reveal>
                        <div class="w-12 h-12 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center font-headline-sm text-headline-sm mb-space-md shadow-md">1</div>
                        <div class="h-44 w-full rounded overflow-hidden mb-space-md bg-surface-container-lowest flex items-center justify-center">
                            <img class="w-full h-full object-cover" alt="Stacks of plain white sandwich bread and whole wheat bread slices" src="/img/marketing/step-bread.jpg" loading="lazy">
                        </div>
                        <h3 class="font-title-md text-title-md text-on-surface mb-1">Pick Your Bread</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">Choose soft plain white sandwich bread, or hearty whole wheat for a less-sweet, grown-up bite.</p>
                        <div class="mt-auto flex flex-wrap gap-1.5">
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Plain White Bread</span>
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Whole Wheat Bread</span>
                        </div>
                    </div>

                    <div class="bg-surface-container rounded-lg p-space-lg flex flex-col relative overflow-hidden shadow-[0_4px_16px_rgba(113,59,44,0.06)] hover:-translate-y-1 transition-transform" data-reveal>
                        <div class="w-12 h-12 rounded-full bg-secondary text-on-secondary flex items-center justify-center font-headline-sm text-headline-sm mb-space-md shadow-md">2</div>
                        <div class="h-44 w-full rounded overflow-hidden mb-space-md bg-surface-container-lowest flex items-center justify-center">
                            <img class="w-full h-full object-cover" alt="Three sliced ice cream blocks: vanilla, strawberry, and chocolate on a marble slab" src="/img/marketing/step-icecream.jpg" loading="lazy">
                        </div>
                        <h3 class="font-title-md text-title-md text-on-surface mb-1">Slice Your Ice Cream Slab</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">Vanilla, strawberry, or chocolate — hand-sliced to a generous, nostalgic thickness.</p>
                        <div class="mt-auto flex flex-wrap gap-1.5">
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Vanilla</span>
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Strawberry</span>
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Chocolate</span>
                        </div>
                    </div>

                    <div class="bg-surface-container rounded-lg p-space-lg flex flex-col relative overflow-hidden shadow-[0_4px_16px_rgba(113,59,44,0.06)] hover:-translate-y-1 transition-transform" data-reveal>
                        <div class="w-12 h-12 rounded-full bg-tertiary text-on-tertiary flex items-center justify-center font-headline-sm text-headline-sm mb-space-md shadow-md">3</div>
                        <div class="h-44 w-full rounded overflow-hidden mb-space-md bg-surface-container-lowest flex items-center justify-center">
                            <img class="w-full h-full object-cover" alt="A completed ice cream sandwich packaged in a pastel pink thermal pouch with ice mist" src="/img/marketing/step-packaged.jpg" loading="lazy">
                        </div>
                        <h3 class="font-title-md text-title-md text-on-surface mb-1">Drizzle &amp; Deliver Ice-Cold</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">Top with sweet condensed milk, toasted peanuts, and receive it packed in dry ice within 20 minutes.</p>
                        <div class="mt-auto flex flex-wrap gap-1.5">
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Dry Ice Pod</span>
                            <span class="font-label-md text-label-md bg-surface-container-lowest text-on-surface-variant px-space-sm py-0.5 rounded-full">Guaranteed Cold</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FLAVORS & BEST SELLERS --}}
        <section class="w-full py-space-xl bg-surface-container-low" id="flavor-section">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg" data-reveal>
                    <div>
                        <span class="font-label-md text-label-md text-primary uppercase tracking-wider bg-secondary-fixed px-space-md py-1 rounded-full inline-block mb-space-xs">Daily Menu</span>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Explore Iconic Combinations</h2>
                    </div>
                    <div class="flex items-center gap-space-xs overflow-x-auto pb-1">
                        <button
                            type="button"
                            @click="activeFilter = 'all'"
                            class="press-scale px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color,box-shadow] duration-200"
                            :class="activeFilter === 'all' ? 'bg-primary-container text-on-primary-container shadow-md' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface'"
                        >All Flavors</button>
                        <button
                            type="button"
                            @click="activeFilter = 'white-bread'"
                            class="press-scale px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color,box-shadow] duration-200"
                            :class="activeFilter === 'white-bread' ? 'bg-primary-container text-on-primary-container shadow-md' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface'"
                        >White Bread</button>
                        <button
                            type="button"
                            @click="activeFilter = 'wheat-bread'"
                            class="press-scale px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color,box-shadow] duration-200"
                            :class="activeFilter === 'wheat-bread' ? 'bg-primary-container text-on-primary-container shadow-md' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface'"
                        >Wheat Bread</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md" data-reveal-group>
                    <template x-for="product in filteredProducts" :key="product.id">
                        <div class="product-card hover-lift bg-surface-container-lowest rounded-lg p-space-md flex flex-col shadow-[0_4px_16px_rgba(113,59,44,0.06)] hover:shadow-[0_12px_28px_rgba(113,59,44,0.12)] transition-shadow duration-200" data-reveal>
                            <div class="relative w-full h-52 rounded bg-tertiary-fixed/40 overflow-hidden mb-space-sm flex items-center justify-center">
                                <span class="absolute top-2 left-2 font-label-md text-label-md px-space-sm py-0.5 rounded-full z-10" :class="product.badgeClass" x-text="product.badge"></span>
                                <img class="w-full h-full object-cover hover:scale-105 transition-transform" :src="product.image" :alt="product.name" loading="lazy">
                            </div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center text-primary gap-1">
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-label-md text-label-md text-on-surface" x-text="product.rating + ' (' + product.reviews + ')'"></span>
                                </div>
                                <span class="font-label-md text-label-md text-secondary bg-secondary-fixed px-2 py-0.5 rounded-full" x-text="product.tag"></span>
                            </div>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1" x-text="product.name"></h3>
                            <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2 mb-space-md" x-text="product.desc"></p>
                            <div class="mt-auto flex items-center justify-between pt-space-xs">
                                <div>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Price</span>
                                    <div class="font-price-tag text-price-tag text-primary" x-text="formatPrice(priceOf(product))"></div>
                                </div>
                                <span x-show="isSoldOut(product)" x-cloak class="px-2 py-0.5 rounded-full bg-on-surface/80 text-surface font-label-md text-label-md">Sold Out</span>
                                <button x-show="!isSoldOut(product)" @click="addToCart($event, product)" :aria-label="'Add ' + product.name + ' to cart'" class="quick-add-btn press-scale w-11 h-11 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center shadow-md" type="button">
                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Studio CTA Banner --}}
                <div class="mt-space-xl bg-gradient-to-r from-primary-container to-primary text-on-primary-container rounded-lg p-space-lg md:p-space-xl flex flex-col md:flex-row items-center justify-between gap-space-lg shadow-[0_8px_24px_rgba(199,70,89,0.3)]" data-reveal>
                    <div class="space-y-space-xs text-center md:text-left">
                        <span class="bg-surface-container-lowest/20 text-on-primary-container px-space-md py-1 rounded-full font-label-md text-label-md uppercase tracking-wider inline-block">Interactive Flavor Lab</span>
                        <h3 class="font-headline-md text-headline-md font-extrabold text-on-primary-container">Want to build your own crazy sandwich?</h3>
                        <p class="font-body-md text-body-md text-on-primary-container/90 max-w-xl">Mix 2 bread bases, 3 ice cream flavors, and 2 toppings into your own combo in the Brread! Studio.</p>
                    </div>
                    <a href="{{ route('build') }}" class="bg-surface-container-lowest text-primary px-space-xl py-space-sm rounded-full font-label-lg text-label-lg shadow-lg hover:scale-105 transition-transform flex items-center gap-space-xs flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">auto_awesome</span>
                        <span>Launch Brread! Studio</span>
                    </a>
                </div>
            </div>
        </section>

        {{-- HERITAGE & STORY --}}
        <section class="w-full py-space-xl bg-surface">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-center" data-reveal-group>
                    <div class="lg:col-span-6 relative" data-reveal>
                        <div class="relative w-full h-[400px] rounded-lg overflow-hidden shadow-[0_8px_28px_rgba(113,59,44,0.12)]">
                            <img class="w-full h-full object-cover" alt="Friendly Singapore ice cream street cart vendor handing a rainbow bread ice cream sandwich to a smiling customer near Orchard Road" src="/img/marketing/heritage-cart.jpg" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-on-surface/80 via-transparent to-transparent flex items-end p-space-lg">
                                <div class="text-surface-container-lowest">
                                    <span class="font-label-md text-label-md text-secondary-fixed uppercase tracking-wider">Est. Orchard Road 1965 &bull; Jakarta 2024</span>
                                    <p class="font-title-md text-title-md">"One dollar, one smile, wrapped in rainbow swirl."</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-6 -right-6 hidden sm:flex items-center gap-space-sm bg-surface-container-lowest p-space-md rounded shadow-[0_8px_24px_rgba(113,59,44,0.12)] max-w-xs">
                            <span class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-primary flex-shrink-0">
                                <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                            </span>
                            <div class="text-xs">
                                <span class="font-bold text-on-surface block">Sub-Zero Packaging</span>
                                <span class="text-on-surface-variant">Guaranteed rock-solid frozen for 60 mins during transport.</span>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-6 space-y-space-md" data-reveal>
                        <span class="font-label-md text-label-md text-primary uppercase tracking-wider bg-secondary-fixed px-space-md py-1 rounded-full inline-block">Heritage Redefined</span>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">From Orchard Cart to Your Front Door</h2>
                        <p class="font-body-lg text-body-lg text-on-surface-variant">
                            Growing up visiting Singapore, nothing matched the pure rush of hearing the Uncle's bell
                            outside Ngee Ann City on a sweltering afternoon. A heavy knife slicing cleanly through a
                            frozen block, slipped effortlessly into fluffy pastel rainbow bread.
                        </p>
                        <p class="font-body-md text-body-md text-on-surface-variant">
                            Brread! honors that iconic street heritage by preserving the authentic texture while
                            elevating ingredients for modern foodies: real dairy cream, no artificial shortening,
                            and zero melted arrivals thanks to custom insulated thermal boxes.
                        </p>
                        <div class="grid grid-cols-2 gap-space-md pt-space-xs">
                            <div class="bg-surface-container p-space-md rounded">
                                <div class="font-headline-sm text-headline-sm text-primary mb-1">0%</div>
                                <div class="font-title-md text-title-md text-on-surface">Melt Guarantee</div>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">If it arrives melted, we instantly send a brand-new box for free.</p>
                            </div>
                            <div class="bg-surface-container p-space-md rounded">
                                <div class="font-headline-sm text-headline-sm text-primary mb-1">4.2cm</div>
                                <div class="font-title-md text-title-md text-on-surface">Thick-Cut Slabs</div>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">True to traditional street proportions for that signature bite.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- COMMUNITY WALL --}}
        <section class="w-full py-space-xl bg-surface-container-low">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-space-sm mb-space-lg" data-reveal>
                    <div>
                        <span class="font-label-md text-label-md text-primary uppercase tracking-wider bg-primary-fixed px-space-md py-1 rounded-full inline-block mb-space-xs">#BrreadSquad</span>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Loved by 150,000+ Sweet Tooths</h2>
                    </div>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">Tag <span class="text-primary font-bold">@brread.id</span> to be featured and win a monthly Rp 500.000 ice cream allowance.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md" data-reveal-group>
                    <div class="bg-surface-container-lowest p-space-md rounded-lg shadow-[0_4px_16px_rgba(113,59,44,0.06)] flex flex-col" data-reveal>
                        <div class="w-full h-56 rounded overflow-hidden mb-space-sm">
                            <img class="w-full h-full object-cover hover:scale-105 transition-transform" alt="Happy young university student laughing while biting into a large Singapore rainbow bread ice cream sandwich outdoors" src="/img/marketing/community-student.jpg" loading="lazy">
                        </div>
                        <div class="flex items-center gap-space-sm mb-space-xs">
                            <div class="w-8 h-8 rounded-full bg-secondary-fixed text-primary flex items-center justify-center font-bold text-xs">CS</div>
                            <div>
                                <span class="font-title-md text-title-md text-on-surface block text-sm">Clarissa S.</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Created: "Sweet Corn Cloud"</span>
                            </div>
                            <div class="ml-auto flex text-primary">
                                @for ($i = 0; $i < 5; $i++)
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                @endfor
                            </div>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant italic">"Literally transported me back to Orchard Road during high school holidays. The bread stays super soft even when cold!"</p>
                    </div>

                    <div class="bg-surface-container-lowest p-space-md rounded-lg shadow-[0_4px_16px_rgba(113,59,44,0.06)] flex flex-col" data-reveal>
                        <div class="w-full h-56 rounded overflow-hidden mb-space-sm">
                            <img class="w-full h-full object-cover hover:scale-105 transition-transform" alt="Overhead flatlay of two Singapore style ice cream sandwiches on pastel plates next to iced matcha lattes" src="/img/marketing/community-flatlay.jpg" loading="lazy">
                        </div>
                        <div class="flex items-center gap-space-sm mb-space-xs">
                            <div class="w-8 h-8 rounded-full bg-tertiary-fixed text-tertiary flex items-center justify-center font-bold text-xs">DA</div>
                            <div>
                                <span class="font-title-md text-title-md text-on-surface block text-sm">Dion A.</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Created: "Matcha Wafer Crunch"</span>
                            </div>
                            <div class="ml-auto flex text-primary">
                                @for ($i = 0; $i < 5; $i++)
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                @endfor
                            </div>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant italic">"Arrived in SCBD within 18 minutes in an insulated dry ice pouch. The wafer was 100% crispy, not soggy at all."</p>
                    </div>

                    <div class="bg-surface-container-lowest p-space-md rounded-lg shadow-[0_4px_16px_rgba(113,59,44,0.06)] flex flex-col" data-reveal>
                        <div class="w-full h-56 rounded overflow-hidden mb-space-sm">
                            <img class="w-full h-full object-cover hover:scale-105 transition-transform" alt="Group of friends sharing a party box of assorted colorful Singapore ice cream sandwiches at an office celebration" src="/img/marketing/community-friends.jpg" loading="lazy">
                        </div>
                        <div class="flex items-center gap-space-sm mb-space-xs">
                            <div class="w-8 h-8 rounded-full bg-primary-fixed text-primary flex items-center justify-center font-bold text-xs">MT</div>
                            <div>
                                <span class="font-title-md text-title-md text-on-surface block text-sm">Maya T.</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Created: "Double Choc Monster"</span>
                            </div>
                            <div class="ml-auto flex text-primary">
                                @for ($i = 0; $i < 5; $i++)
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                @endfor
                            </div>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant italic">"Ordered 20 boxes for our agency Friday treats. Team was obsessed. The durian and dark chocolate are unbelievable."</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Quick Order Floating Toast --}}
        <div
            class="fixed bottom-6 right-6 z-40 transition-[transform,opacity] duration-[400ms] ease-[var(--ease-bounce)] pointer-events-none"
            :class="toastVisible ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-32 scale-90 opacity-0'"
        >
            <div class="bg-primary-container text-on-primary-container px-space-md py-space-sm rounded-full shadow-[0_12px_28px_rgba(199,70,89,0.4)] flex items-center gap-space-sm">
                <span class="material-symbols-outlined text-[20px]" x-text="toastFailed ? 'error' : 'done'">done</span>
                <span class="font-label-lg text-label-lg" role="status" x-text="toastMessage"></span>
            </div>
        </div>

    </div>

@endsection
