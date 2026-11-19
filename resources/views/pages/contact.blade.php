@extends('layouts.app')

@section('title', 'Contact')

@section('description', 'Get in touch with Brread! for orders, questions or feedback about our Singapore-style ice cream sandwiches.')

@section('content')

    {{-- Opening hours live here once: the "open now" pill and the week strip both read them. --}}
    <div
        x-data="{
            opens: 11 * 60,
            closes: 21 * 60 + 30,
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
                return this.minutes >= this.opens && this.minutes < this.closes;
            },
            get status() {
                if (this.isOpen) return 'Open now, until 9:30 PM';
                return this.minutes < this.opens ? 'Closed, opens at 11:00 AM' : 'Closed, opens tomorrow at 11:00 AM';
            },
            async copyAddress() {
                try {
                    await navigator.clipboard.writeText('Telkom University Jakarta Kampus C, Jl. Minangkabau Barat No. 50, Pasar Manggis, Setiabudi, Jakarta Selatan');
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

                    <p class="mt-space-lg inline-flex items-center gap-2 rounded-full pl-3 pr-space-md py-2 font-label-lg text-label-lg border" :class="isOpen ? 'bg-[#e3f3e6] border-[#9fcfa8] text-[#1e5a2b]' : 'bg-surface-container-lowest border-outline-variant text-on-surface-variant'" role="status">
                        <span class="w-2.5 h-2.5 rounded-full" :class="isOpen ? 'bg-[#2e9a47] animate-pulse' : 'bg-outline'"></span>
                        <span x-text="status">Daily, 11:00 AM – 9:30 PM</span>
                    </p>

                    <div class="mt-space-xl space-y-space-lg">
                    <dl>
                        <div>
                            <dt class="font-hand text-[24px] leading-none text-primary">where</dt>
                            <dd class="mt-2">
                                <p class="inline-flex items-center gap-3 rounded-full bg-surface-container-lowest border border-outline-variant pl-2 pr-3 py-1.5 shadow-sm">
                                    <span class="flex w-9 h-9 items-center justify-center rounded-full bg-[#f6a21e] text-white"><span class="material-symbols-outlined text-[22px]">location_on</span></span>
                                    <span class="flex flex-col leading-tight">
                                        <span class="text-[10px] font-extrabold tracking-[0.14em] text-on-surface-variant">LOKASI</span>
                                        <span class="font-label-lg text-[17px] font-extrabold text-on-surface">Minangkabau, Tel-U</span>
                                    </span>
                                    <span class="rounded-full bg-primary text-on-primary text-[11px] font-extrabold tracking-wide px-2.5 py-1">+ BISA ONLINE</span>
                                </p>
                                <p class="mt-space-md font-headline-md text-[clamp(1.3rem,2.6vw,1.8rem)] leading-[1.2] font-bold text-on-surface">Telkom University, Kampus C<br>Jl. Minangkabau Barat No. 50<br>Setiabudi, Jakarta Selatan</p>
                            </dd>
                        </div>
                    </dl>
                            <button type="button" @click="copyAddress()" data-tip="Copies the full address" class="press-scale -mt-space-sm mb-space-lg inline-flex items-center gap-1.5 font-label-lg text-label-lg text-primary underline underline-offset-4 decoration-dotted">
                                <span class="material-symbols-outlined text-[18px]" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                                <span x-text="copied ? 'Copied' : 'Copy address'">Copy address</span>
                            </button>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-lg">
                            <dl>
                                <dt class="font-hand text-[24px] leading-none text-primary">call</dt>
                                <dd class="mt-1"><a href="tel:+622172501234" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">+62 21 7250 1234</a></dd>
                            </dl>
                            <dl>
                                <dt class="font-hand text-[24px] leading-none text-primary">follow</dt>
                                <dd class="mt-1"><a href="{{ config('services.instagram.url') }}" target="_blank" rel="noopener" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">{{ '@'.config('services.instagram.handle') }}</a></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-space-xl flex flex-wrap gap-space-md">
                        <a href="tel:+622172501234" class="btn-chunky inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-space-xl py-3 rounded-full font-label-lg text-label-lg">
                            <span class="material-symbols-outlined text-[20px]">call</span>Call the counter
                        </a>
                        <a href="{{ config('services.instagram.url') }}" target="_blank" rel="noopener" class="btn-chunky inline-flex items-center gap-2 bg-surface-container-lowest text-on-surface border border-outline-variant px-space-xl py-3 rounded-full font-label-lg text-label-lg" style="--chunk: #debfc0">
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
                        <a href="{{ config('services.google_maps.place_url') }}" target="_blank" rel="noopener" class="text-primary underline underline-offset-4 decoration-dotted inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">location_on</span>Open in Google Maps</a>
                        <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ urlencode(config('services.google_maps.query')) }}" target="_blank" rel="noopener" class="text-primary underline underline-offset-4 decoration-dotted inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">directions</span>Get directions</a>
                    </div>
                    <figure class="polaroid contact-polaroid" style="--tilt: 6deg; --tape: -5deg">
                        <img src="/img/marketing/hero-held.jpg" alt="A hand holding a strawberry ice cream sandwich with sprinkles" loading="lazy">
                        <figcaption class="font-hand text-[24px] leading-none text-on-surface mt-2 text-center">fresh off the counter</figcaption>
                    </figure>
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
                            <span class="contact-day-hours">11:00<br>21:30</span>
                            <span x-show="index === today" x-cloak class="contact-day-flag font-hand">today</span>
                        </li>
                    </template>
                </ol>
                <p class="mt-space-lg font-body-lg text-body-lg text-on-surface-variant">Can't make it over? <a href="{{ route('build') }}" class="text-primary font-bold underline underline-offset-4 hover:no-underline">Build one in the Studio</a> and we deliver across Jakarta in about twenty minutes.</p>
            </div>
        </section>
    </div>

@endsection
