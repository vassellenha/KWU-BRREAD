@extends('layouts.app')

@section('title', 'About')

@section('description', 'The story behind Brread!: a Singapore-style ice cream sandwich business made by students, built around fresh ingredients and made-to-order combos.')

@section('content')

    {{--
        Long-scroll story page. resources/js/about.js adds Lenis smooth scrolling and drives every
        effect from scroll position. Chapters marked data-pin become tall and sticky only once that
        script adds `about-live`; without it this is a plain, readable page.
    --}}
    <div data-about class="about w-full font-plus-jakarta">
        <div data-about-bar class="about-bar" aria-hidden="true"></div>

        {{-- 1. OPENING: ingredients drift past at different speeds --}}
        <section class="doodle relative bg-surface-container-low min-h-[calc(100svh-5rem)] flex items-center">

            @foreach ([
                ['bread-white', '-0.26', '-14deg', 'right-[4%] top-[17%] w-[34vw] md:w-[24vw] max-w-[360px]'],
                ['ice-es-teler', '0.20', '9deg', 'right-[22%] bottom-[9%] w-[30vw] md:w-[19vw] max-w-[280px]'],
                ['ice-chocolate', '-0.42', '18deg', 'hidden md:block right-[-3%] bottom-[24%] w-[16vw] max-w-[240px]'],
                ['bread-wheat', '0.34', '-7deg', 'hidden md:block right-[36%] top-[13%] w-[13vw] max-w-[190px]'],
                ['ice-matcha', '-0.14', '24deg', 'hidden lg:block left-[46%] bottom-[30%] w-[11vw] max-w-[170px]'],
            ] as [$cut, $speed, $tilt, $pos])
                <img data-parallax="{{ $speed }}" style="--tilt: {{ $tilt }}" class="about-float {{ $pos }}" src="/img/cutout/{{ $cut }}.webp" alt="">
            @endforeach

            <div class="relative w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop pt-40 pb-space-xl">
                <p class="font-hand text-[30px] leading-none text-primary -rotate-2 origin-left mb-space-sm">our story</p>
                <h1 class="about-title text-on-surface">
                    <span class="about-line" style="--i: 0"><span>Bread.</span></span>
                    <span class="about-line" style="--i: 1"><span>Ice cream.</span></span>
                    <span class="about-line" style="--i: 2"><span class="text-primary italic">That's the idea.</span></span>
                </h1>
                <p class="about-cue mt-space-xl font-hand text-[28px] leading-none text-primary flex items-center gap-2">
                    keep scrolling
                    <span class="material-symbols-outlined text-[22px]">arrow_downward</span>
                </p>
            </div>
        </section>

        {{-- 2. WHERE IT COMES FROM: a snapshot that grows until it fills the screen --}}
        <section data-pin style="--pin-h: 340vh" class="relative bg-surface">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 0])
            <div class="about-sticky about-origin">
                <p class="about-origin-note font-hand">where it comes from</p>
                <figure class="about-origin-frame">
                    <img src="/img/marketing/heritage-uncle.jpg" alt="One of the ice cream uncles of Orchard Road at his cart, giving a thumbs up">
                    <span class="about-origin-shade" aria-hidden="true"></span>
                    <figcaption class="about-origin-tag font-hand">Orchard Road, Singapore</figcaption>
                </figure>
                <div class="about-origin-steps">
                    <p data-step data-from="0.34" data-to="0.58">It started<br>at a cart.</p>
                    <p data-step data-from="0.58" data-to="0.8">A slab of ice cream.<br>A slice of bread.</p>
                    <p data-step data-from="0.8" data-to="2">We brought it<br>home to <em>Jakarta.</em></p>
                </div>
            </div>
        </section>

        {{-- 3. THE SIGNATURE COMBOS: full-screen cards that stack over each other, one per combo the admin has switched on --}}
        @php
            // Colours and a built-in picture for each flavor we sell; a combo on a flavor without an entry here gets the neutral pink.
            $flavorCards = [
                'chocolate' => ['cut' => 'ice-chocolate', 'bg' => '#4a2c1a', 'ink' => '#f6e3cf', 'photo' => 'product-chocolate'],
                'neapolitan' => ['cut' => 'ice-neapolitan', 'bg' => '#f4c3cf', 'ink' => '#4a1f24', 'photo' => 'product-neapolitan'],
                'es-teler' => ['cut' => 'ice-es-teler', 'bg' => '#f8d27e', 'ink' => '#4a3005', 'photo' => 'product-es-teler'],
                'durian-musang-king' => ['cut' => 'ice-durian-musang-king', 'bg' => '#e3e6a4', 'ink' => '#3b3f0c', 'photo' => 'product-durian'],
                'matcha' => ['cut' => 'ice-matcha', 'bg' => '#cfd8b4', 'ink' => '#2e3a17', 'photo' => 'product-matcha'],
            ];
            // One topping cut-out per card (the slab picture holds a single overlay): the first of these the combo uses.
            $toppingCuts = ['sprinkles' => 'top-sprinkles', 'gummies' => 'top-gummy-bears', 'marshmallows' => 'top-marshmallow', 'sauce' => 'top-sauce'];
            // The pictures uploaded for the signature combos in the admin (Menu, Signature combos), newest edit and all.
            $comboPhotos = collect($menu['combos'])->filter(fn ($combo) => $combo['photo'])->values();
            $stageCombo = $comboPhotos->first();
            $drips = ['cream', 'vanilla', 'strawberry', 'chocolate', 'peach'];
            $cards = collect($menu['combos'])->map(function ($combo) use ($flavorCards, $toppingCuts) {
                $look = $flavorCards[\Illuminate\Support\Str::slug($combo['flavor'])] ?? ['cut' => null, 'bg' => '#f4c3cf', 'ink' => '#4a1f24', 'photo' => null];
                $top = collect($toppingCuts)->first(fn ($cut, $flag) => ! empty($combo['state'][$flag]));

                return $look + [
                    'name' => $combo['name'],
                    'flavor' => $combo['flavor'],
                    'line' => $combo['desc'],
                    'price' => $combo['price'],
                    'top' => $top,
                    'image' => $combo['photo'] ? $combo['image'] : ($look['photo'] ? '/img/marketing/'.$look['photo'].'.jpg' : null),
                ];
            })->values();
        @endphp
        <section class="about-stack relative">
            @foreach ($cards as $i => $card)
                <article class="about-card" style="--bg: {{ $card['bg'] }}; --ink: {{ $card['ink'] }}" data-view>
                    @include('partials.drip', ['flavor' => $drips[$i % count($drips)], 'seed' => $i % 3, 'drops' => false])
                    <span class="about-card-word" aria-hidden="true">{{ $card['name'] }}</span>
                    <div class="about-card-body">
                        <div>
                            <p class="font-hand text-[30px] leading-none -rotate-2 origin-left opacity-80">signature no. {{ $i + 1 }}</p>
                            <h2 class="about-card-title">{{ $card['name'] }}</h2>
                            <p class="about-card-line">{{ $card['line'] }}</p>
                            <p class="about-card-line font-bold">Rp {{ number_format($card['price'], 0, ',', '.') }}</p>
                        </div>
                        <div class="about-card-art">
                            @if ($card['image'])
                                <div class="about-card-photo"><img src="{{ $card['image'] }}" alt="{{ $card['name'] }}, {{ $card['flavor'] }} ice cream sandwich" loading="lazy"></div>
                            @endif
                            @if ($card['cut'])
                                <div class="about-card-slab">
                                    <img src="/img/cutout/{{ $card['cut'] }}.webp" alt="" loading="lazy">
                                    @if ($card['top'])
                                        <img class="about-card-topping" src="/img/cutout/{{ $card['top'] }}.webp" alt="" loading="lazy">
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        {{-- 3. HOW ONE GETS MADE: sideways while you scroll down --}}
        <section data-pin style="--pin-h: 440vh" class="relative bg-surface">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 1])
            <div class="about-sticky flex flex-col justify-center">
                <div class="w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop pt-36 md:pt-28 mb-space-lg flex items-end justify-between gap-space-md">
                    <div>
                        <h2 class="font-headline-lg text-[clamp(2rem,5vw,3.5rem)] leading-[1.05] font-extrabold text-on-surface">How one gets made</h2>
                        @include('partials.squiggle')
                    </div>
                    <p class="about-live-only font-hand text-[26px] leading-none text-primary -rotate-2 whitespace-nowrap">it goes sideways →</p>
                </div>

                <div class="about-track-wrap">
                    <div class="about-track">
                        @foreach ([
                            ['n' => '1', 'img' => '/img/marketing/how-bread.jpg', 'alt' => 'Stacks of plain white and whole wheat sandwich bread on a wooden board', 'title' => 'It starts with a loaf', 'text' => 'Soft white sandwich bread, or whole wheat for people who want it less sweet. Sliced, never toasted.', 'tilt' => '-2deg'],
                            ['n' => '2', 'img' => '/img/marketing/how-icecream.jpg', 'alt' => 'Blocks of chocolate, es teler, matcha, durian and neapolitan ice cream on a marble counter', 'title' => 'A block becomes slabs', 'text' => 'Real dairy ice cream, frozen as a block and cut about 4.2 cm thick. '.(\App\Support\HomeMenu::join($menu['flavors']) ?: 'Plenty of flavors').'.', 'tilt' => '1.5deg'],
                            ['n' => '3', 'img' => '/img/marketing/how-toppings.jpg', 'alt' => 'Bowls of mini marshmallows, gummy bears and rainbow sprinkles', 'title' => 'You pick the top', 'text' => (\App\Support\HomeMenu::join($menu['toppings'], 'or') ?: 'A topping').', any mix of them, or none. It gets assembled when you order, not before.', 'tilt' => '-1deg'],
                            ['n' => '4', 'img' => '/img/marketing/how-box.jpg', 'alt' => 'A brown cardboard Brread! box with a scalloped edge', 'title' => 'Into the cold box', 'text' => 'Every order goes straight into an insulated box that keeps it frozen for '.\App\Support\ShopSettings::get()['frozen_minutes'].' minutes.', 'tilt' => '2deg'],
                            ['n' => '5', 'img' => '/img/marketing/how-combos.jpg', 'alt' => 'Five finished ice cream sandwiches with different toppings', 'title' => 'And into your hands', 'text' => 'Around '.\App\Support\ShopSettings::get()['delivery_minutes'].' minutes later, somewhere in Jakarta, it is yours. Eat it fast.', 'tilt' => '-1.5deg'],
                        ] as $step)
                            <article class="about-panel">
                                <div class="relative">
                                    <span class="about-panel-n font-hand" aria-hidden="true">{{ $step['n'] }}</span>
                                    <div class="about-panel-img" style="--tilt: {{ $step['tilt'] }}">
                                        <img src="{{ $step['img'] }}" alt="{{ $step['alt'] }}" loading="lazy">
                                    </div>
                                </div>
                                <h3 class="mt-space-lg font-headline-md text-[26px] leading-[32px] font-bold text-on-surface">{{ $step['title'] }}</h3>
                                <p class="mt-1 font-body-lg text-body-lg text-on-surface-variant max-w-[40ch]">{{ $step['text'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. LOOK INSIDE: the 3D sandwich comes apart and turns with the scroll --}}
        <section data-pin style="--pin-h: 400vh" class="relative bg-[#ffe7ec]">
            @include('partials.drip', ['flavor' => 'cream', 'seed' => 2])
            <div class="about-sticky">
                <div class="h-full w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop pt-32 pb-space-lg grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-center">
                    <div class="lg:col-span-5">
                        <p class="font-hand text-[30px] leading-none text-primary -rotate-2 origin-left mb-space-sm">look inside</p>
                        <div class="about-steps">
                            <div data-step data-from="-1" data-to="0.22">
                                <h2 class="about-step-title">Start with one slice.</h2>
                                <p class="about-step-text">{{ ucfirst(\App\Support\HomeMenu::join(array_map(fn ($name) => \App\Support\HomeMenu::breadLabel($name), $menu['breads'])) ?: 'Soft') }} bread. It folds around the ice cream and gives you something to hold on to, so we keep it simple.</p>
                            </div>
                            <div data-step data-from="0.22" data-to="0.62">
                                <h2 class="about-step-title">One thick slab in the middle.</h2>
                                <p class="about-step-text">About 4.2 cm of {{ \App\Support\HomeMenu::join(array_map('strtolower', $menu['flavors'])) ?: 'ice cream' }}, thick enough to still be cold at the centre.</p>
                            </div>
                            <div data-step data-from="0.62" data-to="0.86">
                                <h2 class="about-step-title">Then something on top.</h2>
                                <p class="about-step-text">{{ ucfirst(\App\Support\HomeMenu::join(array_map('strtolower', $menu['toppings']), 'or')) ?: 'A topping' }}, any mix, or none. Nobody's checking.</p>
                            </div>
                            <div data-step data-from="0.86" data-to="2">
                                <h2 class="about-step-title">Close it up.</h2>
                                <p class="about-step-text">That's one Brread!. With {{ $menu['counts']['breads'] }} {{ \Illuminate\Support\Str::plural('bread', $menu['counts']['breads']) }}, {{ $menu['counts']['flavors'] }} {{ \Illuminate\Support\Str::plural('flavor', $menu['counts']['flavors']) }} and {{ $menu['counts']['toppings'] }} {{ \Illuminate\Support\Str::plural('topping', $menu['counts']['toppings']) }}, there are {{ number_format($menu['ways']) }} ways to build it.</p>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-7 h-full min-h-[280px]">
                        <div data-about-stage-wrap class="about-stage-wrap">
                            <img class="about-stage-photo" src="{{ $stageCombo['image'] ?? '/img/marketing/product-es-teler.jpg' }}" alt="{{ $stageCombo ? $stageCombo['name'].', '.$stageCombo['desc'] : 'White bread ice cream sandwich with es teler ice cream and rainbow sprinkles' }}" loading="lazy">
                            <div data-about-stage class="stage-fade about-stage" role="img" aria-label="3D ice cream sandwich that comes apart as you scroll"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 5. OUT IN THE WILD + NUMBERS --}}
        <section class="about-dark relative pt-44 pb-space-xl overflow-hidden">
            @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 0])
            <p class="font-hand text-[30px] leading-none text-[#f7a3b6] -rotate-2 text-center mb-space-lg">out in the wild</p>
            @php
                // The wall keeps itself current: the newest customer review photos go up first, and the launch shots fill
                // whatever is left of the ten frames.
                $tilts = ['-2deg', '1.5deg', '-1deg', '2deg', '-1.5deg', '1.5deg', '-2deg', '1deg', '-1deg', '2deg'];
                $launch = ['community-mjordan.jpg', 'product-es-teler.jpg', 'community-targaryen.jpg', 'hero-held.jpg', 'product-chocolate.jpg', 'product-drizzle.jpg', 'step-packaged.jpg', 'community-rizky.jpg', 'product-neapolitan.jpg', 'step-icecream.jpg'];
                $frames = \App\Support\ReviewFeed::photos(10)->pluck('photo')
                    ->concat($comboPhotos->pluck('image'))
                    ->concat(array_map(fn ($file) => '/img/marketing/'.$file, $launch))
                    ->take(10)
                    ->values()
                    ->map(fn ($src, $i) => [$src, $tilts[$i]]);
                $wall = [$frames->slice(0, 5)->values()->all(), $frames->slice(5, 5)->values()->all()];
            @endphp
            @foreach ($wall as $row => $photos)
                <div class="about-wall" aria-hidden="true">
                    <div data-marquee="{{ $row === 0 ? '0.38' : '-0.3' }}" class="about-wall-row">
                        @for ($i = 0; $i < 2; $i++)
                            @foreach ($photos as [$photo, $tilt])
                                <span class="about-wall-photo" style="--tilt: {{ $tilt }}"><img src="{{ $photo }}" alt="" loading="lazy"></span>
                            @endforeach
                        @endfor
                    </div>
                </div>
            @endforeach

            <div class="w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop mt-24">
                <dl class="grid grid-cols-2 lg:grid-cols-4 gap-x-space-lg gap-y-space-xl">
                    @foreach ([
                        ['count' => '24', 'unit' => '', 'label' => 'ways to build one'],
                        ['count' => (string) \App\Support\ShopSettings::get()['delivery_minutes'], 'unit' => 'min', 'label' => 'typical delivery in Jakarta'],
                        ['count' => (string) \App\Support\ShopSettings::get()['frozen_minutes'], 'unit' => 'min', 'label' => 'it stays frozen in the box'],
                        ['count' => '4.2', 'unit' => 'cm', 'label' => 'of ice cream in every slab'],
                    ] as $stat)
                        <div data-in class="border-t border-[#fbeee4]/25 pt-space-md">
                            <dd class="about-stat"><span data-count="{{ $stat['count'] }}">{{ $stat['count'] }}</span><small>{{ $stat['unit'] }}</small></dd>
                            <dt class="mt-1 font-body-lg text-body-lg text-[#fbeee4]/75">{{ $stat['label'] }}</dt>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- 6. PROMISES: three stamps whose lettering turns as you scroll --}}
        <section class="doodle relative bg-surface-container-low pt-48 pb-24">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 2])
            <div class="w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <p class="font-hand text-[30px] leading-none text-primary -rotate-2 text-center mb-space-xl">three things we promise</p>
                <ul class="grid grid-cols-1 md:grid-cols-3 gap-space-xl">
                    @foreach ([
                        ['id' => 'dairy', 'ring' => 'REAL DAIRY ICE CREAM • NO SHORTENING • ', 'big' => 'Real dairy', 'small' => 'in every flavor', 'bg' => '#f7a3b6', 'ink' => '#5b0f22', 'tilt' => '-4deg'],
                        ['id' => 'frozen', 'ring' => 'ARRIVES FROZEN • OR THE NEXT BOX IS FREE • ', 'big' => 'Frozen', 'small' => 'or it\'s free', 'bg' => '#4a2c1a', 'ink' => '#f6e3cf', 'tilt' => '3deg'],
                        ['id' => 'halal', 'ring' => 'HALAL CERTIFIED • EVERY SINGLE INGREDIENT • ', 'big' => 'Halal', 'small' => 'all of it', 'bg' => '#f3d58c', 'ink' => '#4d3a05', 'tilt' => '-2deg'],
                    ] as $stamp)
                        <li data-in class="flex justify-center">
                            <div class="about-stamp" style="--bg: {{ $stamp['bg'] }}; --ink: {{ $stamp['ink'] }}; --tilt: {{ $stamp['tilt'] }}">
                                <svg viewBox="0 0 240 240" aria-hidden="true" focusable="false">
                                    <defs><path id="ring-{{ $stamp['id'] }}" d="M120,120 m-92,0 a92,92 0 1,1 184,0 a92,92 0 1,1 -184,0" /></defs>
                                    <circle cx="120" cy="120" r="118" fill="var(--bg)" />
                                    <circle cx="120" cy="120" r="72" fill="none" stroke="var(--ink)" stroke-opacity=".35" stroke-width="2" stroke-dasharray="3 7" stroke-linecap="round" />
                                    <g class="about-stamp-ring">
                                        <text fill="var(--ink)"><textPath href="#ring-{{ $stamp['id'] }}" textLength="570">{{ $stamp['ring'] }}</textPath></text>
                                    </g>
                                </svg>
                                <p class="about-stamp-core">
                                    <strong>{{ $stamp['big'] }}</strong>
                                    <span class="font-hand">{{ $stamp['small'] }}</span>
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- 7. CLOSING --}}
        <section class="relative bg-[#f7a3b6] pt-48 pb-28 overflow-hidden text-center">
            @include('partials.drip', ['flavor' => 'peach', 'seed' => 1])
            <div class="w-full max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <p class="font-hand text-[30px] leading-none text-[#6f1326] -rotate-2 mb-space-sm">that's us. your turn.</p>
                <h2 data-in class="about-title text-[#40000e]">Now build yours.</h2>
                <div class="mt-space-xl flex flex-wrap items-center justify-center gap-space-md">
                    <a href="{{ route('build') }}" class="btn-chunky bg-primary text-on-primary px-space-xl py-4 rounded-full font-label-lg text-[16px]" style="--chunk: #40000e">Open the Studio</a>
                    <a href="{{ route('contact') }}" class="btn-chunky bg-surface-container-lowest text-on-surface px-space-xl py-4 rounded-full font-label-lg text-[16px]" style="--chunk: #c9728a">Visit the counter</a>
                </div>
                <p class="mt-space-lg font-body-lg text-body-lg text-[#40000e]/80">{{ \App\Support\ShopSettings::get()['address_short'] }} &middot; {{ \App\Support\ShopSettings::get()['hours_label'] }}</p>
            </div>
        </section>
    </div>

@endsection
