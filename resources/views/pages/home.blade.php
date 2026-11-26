@extends('layouts.app')

@section('title', 'Home')

@section('description', 'Build your own Singapore-style ice cream sandwich: pick the bread, the ice cream and a topping. Ordered online, delivered cold across Jakarta.')

@section('content')

    <div class="w-full font-plus-jakarta" x-data="signatureMenu(@js($menu['combos']))">

        {{-- HERO SECTION --}}
        <section class="doodle relative w-full bg-surface-container-low pb-space-xl">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop pt-36 lg:pt-40">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg lg:gap-gutter-desktop items-center">

                    <div class="lg:col-span-6 flex flex-col items-start z-10" data-reveal>
                        <p class="font-hand text-[26px] leading-none text-primary -rotate-2 mb-space-sm">Singapore-style, made in Jakarta</p>
                        <h1 class="font-headline-lg font-extrabold text-on-surface tracking-tight text-[clamp(2.5rem,6vw,4.75rem)] leading-[1.02] mb-space-md">
                            Ice cream sandwiches,
                            <span class="relative inline-block text-primary italic">built your way.
                                <svg class="scribble absolute left-0 -bottom-[0.12em] w-full h-[0.22em]" viewBox="0 0 400 22" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
                                    <path d="M4 14C70 4 120 20 190 11s130-8 206 2" stroke="#f3c969" stroke-width="7" stroke-linecap="round" />
                                </svg>
                            </span>
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl mb-space-lg">
                            Choose {{ \App\Support\HomeMenu::join(array_map(fn ($name) => \App\Support\HomeMenu::breadLabel($name), $menu['breads'])) ?: 'your bread' }} bread, a slab of ice cream from {{ $menu['counts']['flavors'] }} flavors, and a topping
                            or two. We pack it cold and deliver it across Jakarta.
                        </p>
                        <div class="flex flex-wrap items-center gap-space-md w-full sm:w-auto">
                            <a href="{{ route('build') }}" class="btn-chunky w-full sm:w-auto flex items-center justify-center bg-primary-container text-on-primary-container px-space-xl py-3 rounded-full font-label-lg text-label-lg text-center">
                                Build yours
                            </a>
                            <a href="#flavor-section" class="btn-chunky w-full sm:w-auto flex items-center justify-center bg-surface-container-lowest text-on-surface px-space-lg py-3 rounded-full font-label-lg text-label-lg border border-outline-variant text-center" style="--chunk: #debfc0">
                                See the menu
                            </a>
                        </div>
                    </div>

                    {{-- Live 3D sandwich (resources/js/studio3d.js). The photo shows first and stays if 3D cannot start. --}}
                    <div class="lg:col-span-6 flex flex-col items-center" x-data="heroSandwich(@js($menu['hero']))" data-reveal>
                        <div class="hero-stage relative w-full max-w-[520px] aspect-square select-none">
                            <div
                                x-ref="stage"
                                class="stage-fade absolute inset-0 transition-opacity duration-700"
                                :class="ready ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                role="img"
                                :aria-label="'3D preview: ' + activeCombo.summary + '. Drag to rotate.'"
                            ></div>

                            <div class="absolute inset-[14%] transition-opacity duration-500" :class="ready ? 'opacity-0 pointer-events-none' : 'opacity-100'">
                                <img
                                    class="w-full h-full object-cover rounded-full"
                                    alt="Close up of a Singapore ice cream sandwich held in hand, plain white bread with a strawberry ice cream block and rainbow sprinkles"
                                    src="/img/marketing/hero-held.jpg"
                                    loading="eager"
                                >
                            </div>

                            <div
                                x-show="ready && !touched" x-cloak
                                x-transition:leave="transition-opacity duration-300" x-transition:leave-end="opacity-0"
                                class="absolute bottom-[6%] left-[4%] flex items-end gap-1 font-hand text-[26px] leading-none text-primary -rotate-6 pointer-events-none whitespace-nowrap"
                            >
                                drag me around!
                                <svg width="46" height="40" viewBox="0 0 46 40" fill="none" class="-translate-y-3" aria-hidden="true" focusable="false">
                                    <path d="M3 36C14 34 30 28 36 8" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                    <path d="M26 12l10-5 4 11" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <div x-show="ready" x-cloak class="mt-space-sm flex flex-col items-center gap-space-sm">
                            <div class="flex flex-wrap items-center justify-center gap-space-xs" role="group" aria-label="Preview a combination">
                                <template x-for="combo in combos" :key="combo.key">
                                    <button
                                        type="button"
                                        @click="pick(combo.key)"
                                        :aria-pressed="active === combo.key"
                                        :data-tip="combo.summary"
                                        class="press-scale flex items-center gap-2 pl-1.5 pr-space-md py-1.5 rounded-full border font-label-lg text-label-lg transition-colors duration-200"
                                        :class="active === combo.key ? 'bg-surface-container-lowest border-primary text-on-surface' : 'bg-transparent border-outline-variant/60 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-lowest'"
                                    >
                                        <span class="w-6 h-6 rounded-full border border-on-surface/10" :style="{ backgroundColor: combo.swatch }"></span>
                                        <span x-text="combo.label"></span>
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    @click="toggleExploded()"
                                    :aria-pressed="exploded"
                                    data-tip="Pull the layers apart to see what's inside"
                                    class="press-scale flex items-center gap-1.5 px-space-md py-1.5 rounded-full border font-label-lg text-label-lg transition-colors duration-200"
                                    :class="exploded ? 'bg-surface-container-lowest border-primary text-on-surface' : 'bg-transparent border-outline-variant/60 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-lowest'"
                                >
                                    <span class="material-symbols-outlined text-[18px]">unfold_more</span>
                                    Layers
                                </button>
                            </div>
                            <p class="font-body-md text-body-md text-on-surface-variant text-center">
                                <span x-text="activeCombo.summary"></span>
                                <span aria-hidden="true">&middot;</span>
                                <a href="{{ route('build') }}" class="text-primary font-bold underline underline-offset-4 hover:no-underline">Make your own</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop mt-space-xl" data-reveal>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-space-md sm:divide-x divide-outline-variant/60 border-y border-outline-variant/60 py-space-lg">
                    <div class="sm:pr-space-lg">
                        <dt class="font-body-sm text-body-sm text-on-surface-variant">Ingredients</dt>
                        <dd class="font-headline-sm text-headline-sm text-on-surface">100% halal certified</dd>
                    </div>
                    <div class="sm:px-space-lg">
                        <dt class="font-body-sm text-body-sm text-on-surface-variant">Delivery</dt>
                        <dd class="font-headline-sm text-headline-sm text-on-surface"><span tabindex="0" data-tip="Typical time from order to your door inside Jakarta" class="underline decoration-dotted decoration-outline underline-offset-4 cursor-help">About {{ \App\Support\ShopSettings::get()['delivery_minutes'] }} minutes</span></dd>
                    </div>
                    <div class="sm:px-space-lg">
                        <dt class="font-body-sm text-body-sm text-on-surface-variant">Stays frozen</dt>
                        <dd class="font-headline-sm text-headline-sm text-on-surface"><span tabindex="0" data-tip="The insulated box keeps your order frozen for {{ \App\Support\ShopSettings::get()['frozen_minutes'] }} minutes after it leaves us" class="underline decoration-dotted decoration-outline underline-offset-4 cursor-help">{{ \App\Support\ShopSettings::get()['frozen_minutes'] }} minutes in the box</span></dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- HOW IT WORKS --}}
        <section class="relative w-full pt-40 pb-space-xl bg-surface">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 0])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="max-w-xl mb-space-xl" data-reveal>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">How it works</h2>
                    @include('partials.squiggle')
                    <p class="font-body-md text-body-md text-on-surface-variant mt-2">Three choices, then we assemble it and bring it over.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg" data-reveal-group>
                    <div class="flex flex-col" data-reveal>
                        <div class="zoom-on-hover h-52 w-full rounded overflow-hidden mb-space-md bg-surface-container">
                            <img class="w-full h-full object-cover" alt="Stacks of plain white sandwich bread and whole wheat bread slices on a wooden board" src="/img/marketing/how-bread.jpg" loading="lazy">
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1 flex items-baseline gap-space-sm"><span class="font-hand text-[64px] leading-[0.8] text-primary-container -rotate-6" aria-hidden="true">1</span><span><span class="sr-only">Step 1: </span>Pick the bread</span></h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">
                            @if (collect($menu['breads'])->contains(fn ($name) => str_contains($name, 'White')) && collect($menu['breads'])->contains(fn ($name) => str_contains($name, 'Wheat')) && $menu['counts']['breads'] === 2)
                                Soft white sandwich bread, or whole wheat if you want it less sweet.
                            @else
                                {{ ucfirst(\App\Support\HomeMenu::join($menu['breads'])) ?: 'Soft sandwich bread.' }}.
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-col md:mt-10" data-reveal>
                        <div class="zoom-on-hover h-52 w-full rounded overflow-hidden mb-space-md bg-surface-container">
                            <img class="w-full h-full object-cover" alt="Slabs of chocolate, neapolitan, es teler, durian musang king and matcha ice cream on a marble counter" src="/img/marketing/how-icecream.jpg" loading="lazy">
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1 flex items-baseline gap-space-sm"><span class="font-hand text-[64px] leading-[0.8] text-primary-container rotate-3" aria-hidden="true">2</span><span><span class="sr-only">Step 2: </span>Pick the ice cream</span></h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ \App\Support\HomeMenu::join($menu['flavors']) ?: 'Real dairy ice cream' }}, cut into a thick slab.</p>
                    </div>

                    <div class="flex flex-col md:mt-4" data-reveal>
                        <div class="zoom-on-hover h-52 w-full rounded overflow-hidden mb-space-md bg-surface-container">
                            <img class="w-full h-full object-cover" alt="Bowls of mini marshmallows, gummy bears and rainbow sprinkles" src="/img/marketing/how-toppings.jpg" loading="lazy">
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1 flex items-baseline gap-space-sm"><span class="font-hand text-[64px] leading-[0.8] text-primary-container -rotate-3" aria-hidden="true">3</span><span><span class="sr-only">Step 3: </span>Add a topping, we deliver</span></h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ \App\Support\HomeMenu::join($menu['toppings']) ?: 'A topping of your choice' }}, then it goes into an insulated box and out the door.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- FLAVORS & BEST SELLERS --}}
        <section class="doodle w-full pt-40 pb-space-xl bg-surface-container-low" id="flavor-section">
            @include('partials.drip', ['flavor' => 'vanilla', 'seed' => 1])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg" data-reveal>
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Signature combinations</h2>
                        @include('partials.squiggle')
                    </div>
                    <div class="flex items-center gap-space-xs overflow-x-auto pb-1">
                        <button
                            type="button"
                            @click="activeFilter = 'all'"
                            class="press-scale px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color] duration-200"
                            :class="activeFilter === 'all' ? 'bg-inverse-surface text-inverse-on-surface' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface'"
                        >All</button>
                        @foreach ($menu['filters'] as $filter)
                            <button
                                type="button"
                                @click="activeFilter = @js($filter['slug'])"
                                class="press-scale px-space-md py-space-xs rounded-full font-label-lg text-label-lg whitespace-nowrap transition-[background-color,color] duration-200"
                                :class="activeFilter === @js($filter['slug']) ? 'bg-inverse-surface text-inverse-on-surface' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface'"
                            >{{ $filter['label'] }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md" data-reveal-group>
                    <template x-for="product in filteredProducts" :key="product.id">
                        <div class="product-card hover-lift bg-surface-container-lowest rounded-lg p-space-md flex flex-col border border-outline-variant/40 hover:shadow-[0_12px_28px_rgba(113,59,44,0.10)]" data-reveal>
                            <div class="zoom-on-hover relative w-full h-52 rounded bg-surface-container overflow-hidden mb-space-sm">
                                <img x-show="product.photo || product.thumb || product.image" class="w-full h-full" :class="product.photo ? 'object-cover' : 'object-contain p-space-sm'" :style="product.photo ? '' : { backgroundColor: (product.color || '#F3E9D2') + '2B' }" :src="product.photo ? product.image : (product.thumb || product.image)" :alt="product.name" loading="lazy">
                            </div>
                            <p class="font-hand text-[22px] leading-none text-primary-container mb-1 -rotate-1 origin-left" x-text="product.tag"></p>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1" x-text="product.name"></h3>
                            <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2 mb-space-md" x-text="product.desc"></p>
                            <div class="mt-auto flex items-center justify-between pt-space-xs">
                                <div class="font-price-tag text-price-tag text-primary" x-text="formatPrice(priceOf(product))"></div>
                                <span x-show="isSoldOut(product)" x-cloak data-tip="Out of stock right now. Check back soon." tabindex="0" class="px-2 py-0.5 rounded-full bg-on-surface/80 text-surface font-label-md text-label-md">Sold Out</span>
                                <button x-show="!isSoldOut(product)" @click="addToCart($event, product)" :aria-label="'Add ' + product.name + ' to cart'" data-tip="Add to cart" class="quick-add-btn press-scale w-11 h-11 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center" type="button">
                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Studio CTA Banner --}}
                <div class="mt-space-xl bg-primary text-on-primary rounded-lg p-space-lg md:p-space-xl flex flex-col md:flex-row items-center justify-between gap-space-lg" data-reveal>
                    <div class="space-y-space-xs text-center md:text-left">
                        <p class="font-hand text-[26px] leading-none text-secondary-fixed -rotate-2 origin-left">none of these quite right?</p>
                        <h3 class="font-headline-md text-headline-md">Build your own in the Studio</h3>
                        <p class="font-body-md text-body-md text-on-primary/90 max-w-xl">Choose from {{ $menu['counts']['breads'] }} {{ \Illuminate\Support\Str::plural('bread', $menu['counts']['breads']) }}, {{ $menu['counts']['flavors'] }} ice cream {{ \Illuminate\Support\Str::plural('flavor', $menu['counts']['flavors']) }} and {{ $menu['counts']['toppings'] }} {{ \Illuminate\Support\Str::plural('topping', $menu['counts']['toppings']) }}, then turn your sandwich around in 3D before you order.</p>
                    </div>
                    <a href="{{ route('build') }}" class="btn-chunky bg-surface-container-lowest text-primary px-space-xl py-3 rounded-full font-label-lg text-label-lg flex-shrink-0" style="--chunk: #6f1326">
                        Open the Studio
                    </a>
                </div>
            </div>
        </section>

        {{-- HERITAGE & STORY --}}
        <section class="relative w-full pt-40 pb-space-xl bg-surface">
            @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 0])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-center" data-reveal-group>
                    <div class="lg:col-span-6" data-reveal>
                        <div class="relative w-full h-[400px] rounded-lg overflow-hidden -rotate-1 shadow-[0_14px_30px_rgba(113,59,44,0.16)]">
                            <img class="w-full h-full object-cover object-[50%_40%]" alt="One of the ice cream uncles of Orchard Road at his cart, giving a thumbs up" src="/img/marketing/heritage-uncle-hd.jpg" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-on-surface/80 via-transparent to-transparent flex items-end p-space-lg">
                                <p class="font-title-md text-title-md text-surface-container-lowest">Orchard Road, Singapore</p>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-6 space-y-space-md" data-reveal>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">From the Orchard Road cart to your door</h2>
                        @include('partials.squiggle')
                        <p class="font-body-lg text-body-lg text-on-surface-variant">
                            In Singapore, vendors on Orchard Road slice a block of ice cream to order and fold it
                            into a piece of soft bread. Brread! makes that sandwich in Jakarta, and you choose
                            every layer.
                        </p>
                        <p class="font-body-md text-body-md text-on-surface-variant">
                            We use real dairy ice cream and no artificial shortening, and each order travels in an
                            insulated box so it arrives frozen.
                        </p>
                        <dl class="grid grid-cols-2 gap-space-md pt-space-xs">
                            <div class="border-t border-outline-variant/60 pt-space-sm">
                                <dt class="font-title-md text-title-md text-on-surface">Free replacement</dt>
                                <dd class="font-body-sm text-body-sm text-on-surface-variant">If it arrives melted, we send a new box at no charge.</dd>
                            </div>
                            <div class="border-t border-outline-variant/60 pt-space-sm">
                                <dt class="font-title-md text-title-md text-on-surface">Thick slabs</dt>
                                <dd class="font-body-sm text-body-sm text-on-surface-variant">Cut about 4.2 cm thick, like the street-cart original.</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        {{-- COMMUNITY WALL --}}
        <section class="doodle w-full pt-40 pb-space-xl bg-surface-container-low">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 2])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-space-sm mb-space-xl" data-reveal>
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Made by our customers</h2>
                        @include('partials.squiggle')
                    </div>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">Add a photo to your review on your order page to land here. Tag <a href="{{ \App\Support\ContactInfo::get()['instagram_url'] }}" target="_blank" rel="noopener" class="text-primary font-bold hover:underline">{{ '@'.\App\Support\ContactInfo::get()['instagram'] }}</a> {{ \App\Support\ShopSettings::get()['promo_text'] }}</p>
                </div>

                @php
                    // The three newest reviews that came with a photo; until there are three, the launch photos fill the rest.
                    $tilts = [['-2deg', '-4deg'], ['1.5deg', '3deg'], ['-1deg', '-2deg']];
                    $launch = [
                        ['photo' => '/img/marketing/community-mjordan.jpg', 'alt' => 'Man biting into a whole wheat bread and vanilla ice cream sandwich on a campus lawn', 'name' => 'M. Jordan', 'made' => 'Whole Wheat Vanilla', 'quote' => 'Whole wheat with vanilla is not too sweet, and the bread was still firm when I finished it.', 'rating' => 5, 'frame' => '--fit:cover;--fx:50%;--fy:30%;--zoom:1'],
                        ['photo' => '/img/marketing/community-rizky.jpg', 'alt' => 'Whole wheat bread ice cream sandwich with vanilla ice cream and chocolate sauce poured over the top', 'name' => 'Rizky S.', 'made' => 'Wheat Vanilla Drizzle', 'quote' => 'Arrived in SCBD in 18 minutes and was still frozen solid. The chocolate drizzle on wheat bread works.', 'rating' => 5, 'frame' => '--fit:cover;--fx:50%;--fy:50%;--zoom:1'],
                        ['photo' => '/img/marketing/community-targaryen.jpg', 'alt' => 'Man sitting by a sports car with an open box of assorted ice cream sandwiches on the hood', 'name' => 'J. Targaryen', 'made' => 'Double Choc Monster', 'quote' => 'Ordered 20 boxes for our agency Friday treats and the team finished them in minutes.', 'rating' => 5, 'frame' => '--fit:cover;--fx:50%;--fy:35%;--zoom:1'],
                    ];
                    $fresh = \App\Support\ReviewFeed::photos(3)->map(fn ($review) => $review + ['alt' => 'Photo from '.$review['name'].' of their Brread! sandwich']);
                    $polaroids = $fresh->concat(array_slice($launch, 0, max(0, 3 - $fresh->count())))->values();
                    $words = \App\Support\ReviewFeed::words(6);
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg md:gap-space-xl" data-reveal-group>
                    @foreach ($polaroids as $i => $review)
                        <div data-reveal>
                            <figure class="polaroid flex flex-col h-full" style="--tilt: {{ $tilts[$i][0] }}; --tape: {{ $tilts[$i][1] }}">
                                <x-review-gallery :review="$review" :alt="$review['alt']" />
                                <figcaption class="mb-space-xs">
                                    <div class="flex items-center justify-between gap-space-sm">
                                        <span class="font-hand text-[30px] leading-none text-on-surface truncate">{{ $review['name'] }}</span>
                                        <span class="flex shrink-0 text-primary" role="img" aria-label="{{ $review['rating'] }} out of 5 stars">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <span class="material-symbols-outlined text-[16px] {{ $star <= $review['rating'] ? '' : 'opacity-25' }}" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">star</span>
                                            @endfor
                                        </span>
                                    </div>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Made: {{ $review['made'] }}</span>
                                </figcaption>
                                <blockquote class="font-body-md text-body-md text-on-surface-variant [overflow-wrap:anywhere] line-clamp-5">“{{ $review['quote'] }}”</blockquote>
                            </figure>
                        </div>
                    @endforeach
                </div>

                {{-- Reviews that came with words only: newest first, as little order tickets under the photos. --}}
                @if ($words->isNotEmpty())
                    <div class="mt-space-xl" data-reveal>
                        <p class="font-hand text-[26px] leading-none text-primary -rotate-1 mb-space-md">fresh from the order slips</p>
                        <ul class="review-slips">
                            @foreach ($words as $review)
                                <li class="review-slip">
                                    <span class="review-slip__stars" role="img" aria-label="{{ $review['rating'] }} out of 5 stars">
                                        @for ($star = 1; $star <= 5; $star++)
                                            <span class="material-symbols-outlined {{ $star <= $review['rating'] ? '' : 'opacity-25' }}" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">star</span>
                                        @endfor
                                    </span>
                                    <p class="review-slip__quote">“{{ $review['quote'] }}”</p>
                                    <p class="review-slip__who"><span class="font-hand">{{ $review['name'] }}</span> · {{ $review['made'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-space-xl flex justify-center" data-reveal>
                    <a href="{{ route('reviews') }}" class="btn-chunky inline-flex items-center gap-2 bg-surface-container-lowest text-on-surface border-2 border-on-surface px-space-xl py-3 rounded-full font-label-lg text-label-lg" style="--chunk: #352f29">
                        <span class="material-symbols-outlined text-[20px]">photo_library</span>View more customer photos
                    </a>
                </div>
            </div>
        </section>

        {{-- Quick Order Floating Toast --}}
        <div
            class="fixed bottom-6 right-6 z-40 transition-[transform,opacity] duration-[400ms] ease-[var(--ease-bounce)] pointer-events-none"
            :class="toastVisible ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-32 scale-90 opacity-0'"
        >
            <div class="bg-primary-container text-on-primary-container px-space-md py-space-sm rounded-full shadow-lg flex items-center gap-space-sm">
                <span class="material-symbols-outlined text-[20px]" x-text="toastFailed ? 'error' : 'done'">done</span>
                <span class="font-label-lg text-label-lg" role="status" x-text="toastMessage"></span>
            </div>
        </div>

    </div>

@endsection
