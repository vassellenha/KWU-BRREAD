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
                    await navigator.clipboard.writeText('Jl. Melawai Raya No. 12, Jakarta Selatan 12160');
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
                            <dd class="mt-1 font-headline-md text-[clamp(1.5rem,3vw,2rem)] leading-[1.15] font-bold text-on-surface">Jl. Melawai Raya No. 12<br>Jakarta Selatan 12160</dd>
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
                                <dt class="font-hand text-[24px] leading-none text-primary">write</dt>
                                <dd class="mt-1"><a href="mailto:hello@brread.id" class="font-headline-sm text-[22px] font-bold text-on-surface hover:text-primary transition-colors">hello@brread.id</a></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-space-xl flex flex-wrap gap-space-md">
                        <a href="tel:+622172501234" class="btn-chunky inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-space-xl py-3 rounded-full font-label-lg text-label-lg">
                            <span class="material-symbols-outlined text-[20px]">call</span>Call the counter
                        </a>
                        <a href="mailto:hello@brread.id" class="btn-chunky inline-flex items-center gap-2 bg-surface-container-lowest text-on-surface border border-outline-variant px-space-xl py-3 rounded-full font-label-lg text-label-lg" style="--chunk: #debfc0">
                            <span class="material-symbols-outlined text-[20px]">mail</span>Send an email
                        </a>
                    </div>
                </div>

                {{-- Hand-drawn neighbourhood sketch. Decorative: the address above is the real direction. --}}
                <div class="lg:col-span-6 relative" data-reveal>
                    <div class="contact-map">
                        <svg viewBox="0 0 560 460" role="img" aria-label="Sketch map showing Brread! on Jl. Melawai Raya">
                            <rect width="560" height="460" fill="#fbe9d0" />
                            <g fill="#f3d9b4">
                                <path d="M-10 -10h170l-18 150H-10zM196 -10h170l-12 142H180zM402 -10h170v160l-150-14zM-10 196l150-10-14 150H-10zM176 190l180-8-10 152-182 8zM396 180l176 14v150l-184-10zM-10 386l136-8-8 100H-10zM160 380l188-8-6 100H154zM386 374l186 10v90H380z" />
                            </g>
                            <path d="M40 232c30-22 62-20 84 0s16 60-12 72-70 2-82-22 0-42 10-50z" fill="#cfe6c8" />
                            <g fill="none" stroke="#fffaf3" stroke-linecap="round">
                                <path d="M-10 166c120-10 250-16 580-2" stroke-width="30" />
                                <path d="M-10 360c160-12 330-14 580 0" stroke-width="22" />
                                <path d="M166 -10c-6 150-16 320-22 480" stroke-width="22" />
                                <path d="M380 -10c-4 160-10 320-8 480" stroke-width="26" />
                            </g>
                            <path d="M-10 166c120-10 250-16 580-2" fill="none" stroke="#e7c9a0" stroke-width="2" stroke-dasharray="12 12" />
                            <path id="melawai" d="M24 160c110-9 230-14 520-3" fill="none" />
                            <text font-family="'Baloo 2', sans-serif" font-weight="700" font-size="15" fill="#9a6b3a" letter-spacing="1"><textPath href="#melawai" startOffset="4%">JL. MELAWAI RAYA</textPath></text>
                            <g class="contact-map-pin">
                                <circle cx="280" cy="208" r="26" fill="#f58fa6" opacity=".35" class="contact-map-pulse" />
                                <path d="M280 134c-25 0-44 19-44 43 0 31 44 71 44 71s44-40 44-71c0-24-19-43-44-43z" fill="#c74659" />
                                <path d="M252 168c2-13 13-23 28-23" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="5" stroke-linecap="round" />
                                <rect x="262" y="160" width="36" height="9" rx="4" fill="#fbe9d0" /><rect x="265" y="169" width="30" height="11" rx="2" fill="#f7a3b6" /><rect x="262" y="180" width="36" height="9" rx="4" fill="#fbe9d0" />
                            </g>
                        </svg>
                        <p class="contact-map-note font-hand">we're right here
                            <svg width="54" height="44" viewBox="0 0 54 44" fill="none" aria-hidden="true"><path d="M50 4C40 30 24 36 6 34" stroke="currentColor" stroke-width="3" stroke-linecap="round" /><path d="M16 26l-11 8 12 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </p>
                        <p class="contact-map-scale font-hand">not to scale, obviously</p>
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
