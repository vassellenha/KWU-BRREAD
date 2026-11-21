@extends('layouts.app')

@section('title', 'Contact')

@section('description', 'Get in touch with Brread! for orders, questions or feedback about our Singapore-style ice cream sandwiches.')

@section('content')

    @php
        $cp = \App\Support\ContactInfo::get();
        $shop = \App\Support\ShopSettings::get();
        // The playable stack: the newest customer photos on top, the launch shots under them.
        $stack = \App\Support\ReviewFeed::photos(5)
            ->map(fn ($review) => ['src' => $review['photo'], 'caption' => $review['name'], 'alt' => 'Photo from '.$review['name'].' of their Brread! sandwich'])
            ->concat([
                ['src' => '/img/marketing/hero-held.jpg', 'caption' => 'fresh off the counter', 'alt' => 'A hand holding a strawberry ice cream sandwich with sprinkles'],
                ['src' => '/img/marketing/product-chocolate.jpg', 'caption' => 'chocolate on wheat', 'alt' => 'Chocolate ice cream between two slices of whole wheat bread'],
                ['src' => '/img/marketing/product-drizzle.jpg', 'caption' => 'the drizzle one', 'alt' => 'Chocolate ice cream on whole wheat bread with chocolate sauce'],
                ['src' => '/img/marketing/product-neapolitan.jpg', 'caption' => 'the trio', 'alt' => 'Neapolitan ice cream between two slices of white bread'],
            ])
            ->take(6)
            ->values();
    @endphp

    {{-- Opening hours live here once: the "open now" pill and the week strip both read them. --}}
    <div
        x-data="{
            opens: {{ $shop['open_minutes'] }},
            closes: {{ $shop['close_minutes'] }},
            shut: @js($shop['counter_closed']),
            openLabel: @js($shop['open_label']),
            closeLabel: @js($shop['close_label']),
            note: @js($shop['counter_closed_note']),
            days: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            today: 0,
            minutes: 0,
            copied: false,
            init() {
                this.tick();
                setInterval(() => this.tick(), 60000);
            },
            tick() {
                {{-- The counter keeps Jakarta time, wherever the visitor is. --}}
                const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
                const get = (type) => parts.find((part) => part.type === type)?.value;
                this.today = this.days.indexOf(get('weekday'));
                this.minutes = (Number(get('hour')) % 24) * 60 + Number(get('minute'));
            },
            get isOpen() {
                return !this.shut && this.minutes >= this.opens && this.minutes < this.closes;
            },
            get status() {
                if (this.shut) return 'Closed for now, ' + this.note;
                if (this.isOpen) return 'Open now, until ' + this.closeLabel;
                return this.minutes < this.opens ? 'Closed, opens at ' + this.openLabel : 'Closed, opens tomorrow at ' + this.openLabel;
            },
            get signSub() {
                if (this.shut) return this.note;
                if (this.isOpen) return 'till ' + this.closeLabel + ' tonight';
                return this.minutes < this.opens ? 'back at ' + this.openLabel : 'back tomorrow, ' + this.openLabel;
            },
            async copyAddress() {
                try {
                    await navigator.clipboard.writeText(@js($shop['address_one_line']));
                    this.copied = true;
                    setTimeout(() => (this.copied = false), 2000);
                } catch (e) {}
            },
        }"
        class="w-full font-plus-jakarta"
    >
        <section class="doodle relative bg-surface-container-low pt-44 pb-24">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-center">

                <div class="lg:col-span-6" data-reveal>
                    <p class="font-hand text-[30px] leading-none text-primary -rotate-2 origin-left mb-space-sm">come say hi</p>
                    <h1 class="font-headline-lg font-extrabold text-on-surface tracking-tight text-[clamp(3rem,8.5vw,6.5rem)] leading-[0.92]">
                        Visit the
                        <span class="relative inline-block text-primary italic">counter.
                            <svg class="scribble absolute left-0 -bottom-[0.1em] w-full h-[0.2em]" viewBox="0 0 400 22" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
                                <path d="M4 14C70 4 120 20 190 11s130-8 206 2" stroke="#f3c969" stroke-width="7" stroke-linecap="round" />
                            </svg>
                        </span>
                    </h1>

                    {{-- The shop sign on the door: it hangs from a nail, swings when you poke it, and flips to CLOSED after hours. --}}
                    <div class="shop-sign mt-space-lg" :class="isOpen ? 'is-open' : 'is-closed'" x-data="{ swing: false }">
                        <span class="shop-sign__nail" aria-hidden="true"></span>
                        <button type="button" class="shop-sign__board" :class="swing ? 'is-swinging' : ''" @click="swing = false; $nextTick(() => swing = true)" @animationend="swing = false" :aria-label="status">
                            <span class="shop-sign__face shop-sign__face--open" aria-hidden="true">
                                <span class="shop-sign__word">Open</span>
                                <span class="shop-sign__sub font-hand" x-text="isOpen ? signSub : 'till ' + closeLabel">till {{ $shop['close_label'] }} tonight</span>
                            </span>
                            <span class="shop-sign__face shop-sign__face--closed" aria-hidden="true">
                                <span class="shop-sign__word">Closed</span>
                                <span class="shop-sign__sub font-hand" x-text="isOpen ? 'back at ' + openLabel : signSub">back at {{ $shop['open_label'] }}</span>
                            </span>
                        </button>
                        <span class="sr-only" role="status" x-text="status">{{ $shop['hours_label'] }}</span>
                    </div>

                    <div class="mt-space-xl space-y-space-lg">
                    <dl>
                        <div>
                            <dt class="font-hand text-[24px] leading-none text-primary">where</dt>
                            <dd class="mt-2">
                                <p class="inline-flex items-center gap-3 rounded-full bg-surface-container-lowest border border-outline-variant pl-2 pr-3 py-1.5 shadow-sm">
                                    <span class="flex w-9 h-9 items-center justify-center rounded-full bg-[#f6a21e] text-white"><span class="material-symbols-outlined text-[22px]">location_on</span></span>
                                    <span class="flex flex-col leading-tight">
                                        <span class="text-[10px] font-extrabold tracking-[0.14em] text-on-surface-variant">LOKASI</span>
                                        <span class="font-label-lg text-[17px] font-extrabold text-on-surface">{{ $shop['address_short'] }}</span>
                                    </span>
                                    <span class="rounded-full bg-primary text-on-primary text-[11px] font-extrabold tracking-wide px-2.5 py-1">+ BISA ONLINE</span>
                                </p>
                                <p class="mt-space-md font-headline-md text-[clamp(1.3rem,2.6vw,1.8rem)] leading-[1.2] font-bold text-on-surface">{!! collect($shop['address_lines'])->map(fn ($line) => e($line))->implode('<br>') !!}</p>
                            </dd>
                        </div>
                    </dl>
                            <button type="button" @click="copyAddress()" data-tip="Copies the full address" class="press-scale -mt-space-sm mb-space-lg inline-flex items-center gap-1.5 font-label-lg text-label-lg text-primary underline underline-offset-4 decoration-dotted">
                                <span class="material-symbols-outlined text-[18px]" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                                <span x-text="copied ? 'Copied' : 'Copy address'">Copy address</span>
                            </button>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-lg">
                            <dl>
                                <dt class="font-hand text-[24px] leading-none text-primary">call{{ $cp['name'] !== '' ? ' '.$cp['name'] : '' }}</dt>
                                <dd class="mt-1"><a href="{{ $cp['phone_href'] }}" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">{{ $cp['phone'] }}</a></dd>
                            </dl>
                            <dl>
                                <dt class="font-hand text-[24px] leading-none text-primary">follow</dt>
                                <dd class="mt-1"><a href="{{ $cp['instagram_url'] }}" target="_blank" rel="noopener" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">{{ '@'.$cp['instagram'] }}</a></dd>
                            </dl>
                            @if ($cp['whatsapp_url'])
                                <dl>
                                    <dt class="font-hand text-[24px] leading-none text-primary">chat</dt>
                                    <dd class="mt-1"><a href="{{ $cp['whatsapp_url'] }}" target="_blank" rel="noopener" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">{{ $cp['whatsapp'] }}</a></dd>
                                </dl>
                            @endif
                        </div>
                    </div>

                    <div class="mt-space-xl flex flex-wrap gap-space-md">
                        <a href="{{ $cp['phone_href'] }}" class="btn-chunky inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-space-xl py-3 rounded-full font-label-lg text-label-lg">
                            <span class="material-symbols-outlined text-[20px]">call</span>{{ $cp['name'] !== '' ? 'Call '.$cp['name'] : 'Call the counter' }}
                        </a>
                        <a href="{{ $cp['instagram_url'] }}" target="_blank" rel="noopener" class="btn-chunky inline-flex items-center gap-2 bg-surface-container-lowest text-on-surface border border-outline-variant px-space-xl py-3 rounded-full font-label-lg text-label-lg" style="--chunk: #debfc0">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="0.6" fill="currentColor"/></svg>Message us on Instagram
                        </a>
                    </div>
                </div>

                {{-- Live Google Map of the counter. The polaroid still overlaps its corner. --}}
                <div class="lg:col-span-6 relative" data-reveal>
                    <div class="contact-map">
                        @include('partials.google-map')
                    </div>
                    <div class="mt-space-md flex flex-wrap gap-x-space-lg gap-y-2 justify-end font-label-lg text-label-lg">
                        <a href="{{ $shop['maps_place_url'] }}" target="_blank" rel="noopener" class="text-primary underline underline-offset-4 decoration-dotted inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">location_on</span>Open in Google Maps</a>
                        <a href="{{ $shop['maps_directions_url'] }}" target="_blank" rel="noopener" class="text-primary underline underline-offset-4 decoration-dotted inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">directions</span>Get directions</a>
                    </div>
                    {{-- A pile of polaroids to play with: drag the top one off (or tap it) and it goes to the back of the pile. --}}
                    <div class="snap-pile" x-data="polaroidPile({{ $stack->count() }})" @keydown.right.prevent="next()" @keydown.left.prevent="previous()">
                        @foreach ($stack as $i => $snap)
                            <figure
                                class="polaroid snap-pile__card"
                                :class="cardClass({{ $i }})"
                                :style="cardStyle({{ $i }})"
                                @pointerdown="grab($event, {{ $i }})"
                                @pointermove="drag($event)"
                                @pointerup="drop($event)"
                                @pointercancel="drop($event)"
                                aria-hidden="true"
                            >
                                <img src="{{ $snap['src'] }}" alt="{{ $snap['alt'] }}" draggable="false" loading="lazy">
                                <figcaption class="font-hand text-[24px] leading-none text-on-surface mt-2 text-center truncate">{{ $snap['caption'] }}</figcaption>
                            </figure>
                        @endforeach
                        <button type="button" class="snap-pile__hint font-hand" @click="next()" aria-label="Show the next photo">
                            <svg viewBox="0 0 40 24" aria-hidden="true" focusable="false"><path d="M2 18C10 6 24 4 34 10M28 4l7 6-8 4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span>flick me</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- THE WEEK --}}
        <section class="relative bg-surface pt-44 pb-24">
            @include('partials.drip', ['flavor' => 'chocolate', 'seed' => 1])
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop" data-reveal>
                <div class="flex flex-wrap items-end justify-between gap-space-md mb-space-lg">
                    <div>
                        <h2 class="font-headline-lg text-[clamp(2rem,5vw,3.25rem)] leading-none font-extrabold text-on-surface">Open every day</h2>
                        @include('partials.squiggle')
                    </div>
                    <p class="font-hand text-[26px] leading-none text-primary -rotate-2">same hours, all week</p>
                </div>
                <ol class="grid grid-cols-4 sm:grid-cols-7 gap-space-sm">
                    <template x-for="(day, index) in days" :key="day">
                        <li class="contact-day" :class="index === today ? 'is-today' : ''" :aria-current="index === today ? 'date' : null">
                            <span class="contact-day-name" x-text="day"></span>
                            <span class="contact-day-hours">{{ $shop['hours_open'] }}<br>{{ $shop['hours_close'] }}</span>
                            <span x-show="index === today" x-cloak class="contact-day-flag font-hand">today</span>
                        </li>
                    </template>
                </ol>
                <p class="mt-space-lg font-body-lg text-body-lg text-on-surface-variant">Can't make it over? <a href="{{ route('build') }}" class="text-primary font-bold underline underline-offset-4 hover:no-underline">Build one in the Studio</a> and we deliver across Jakarta in about {{ $shop['delivery_minutes'] }} minutes.</p>
            </div>
        </section>
    </div>

@endsection
