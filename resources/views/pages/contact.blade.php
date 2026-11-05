@extends('layouts.app')

@section('title', 'Contact')

@section('content')

    <section class="w-full font-plus-jakarta bg-inverse-surface text-inverse-on-surface py-space-xl min-h-[70vh]">
        <div class="max-w-3xl mx-auto px-margin md:px-margin-tablet text-center">
            <div data-reveal>
                <span class="inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container px-space-md py-space-xs rounded-full font-label-lg text-label-lg mb-space-md">
                    <span class="material-symbols-outlined text-[18px]">storefront</span>
                    Visit Us
                </span>
                <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg tracking-tight">Visit Brread!</h1>
                <p class="mt-space-sm font-body-lg text-body-lg text-inverse-on-surface/70 max-w-md mx-auto">
                    Come taste the real thing — freshly stacked, Jakarta-style, right off the counter.
                </p>
            </div>

            <dl class="mt-space-xl grid sm:grid-cols-2 gap-space-lg text-left max-w-xl mx-auto" data-reveal-group>
                <div class="flex gap-space-sm" data-reveal>
                    <span class="material-symbols-outlined text-[22px] text-inverse-primary shrink-0">location_on</span>
                    <div>
                        <dt class="font-label-lg text-label-lg text-inverse-primary mb-0.5">Address</dt>
                        <dd class="font-body-md text-body-md text-inverse-on-surface/80">Jl. Melawai Raya No. 12<br>Jakarta Selatan 12160</dd>
                    </div>
                </div>
                <div class="flex gap-space-sm" data-reveal>
                    <span class="material-symbols-outlined text-[22px] text-inverse-primary shrink-0">schedule</span>
                    <div>
                        <dt class="font-label-lg text-label-lg text-inverse-primary mb-0.5">Hours</dt>
                        <dd class="font-body-md text-body-md text-inverse-on-surface/80">Daily<br>11:00 AM – 9:30 PM</dd>
                    </div>
                </div>
                <div class="flex gap-space-sm" data-reveal>
                    <span class="material-symbols-outlined text-[22px] text-inverse-primary shrink-0">call</span>
                    <div>
                        <dt class="font-label-lg text-label-lg text-inverse-primary mb-0.5">Phone</dt>
                        <dd class="font-body-md text-body-md text-inverse-on-surface/80">+62 21 7250 1234</dd>
                    </div>
                </div>
                <div class="flex gap-space-sm" data-reveal>
                    <span class="material-symbols-outlined text-[22px] text-inverse-primary shrink-0">mail</span>
                    <div>
                        <dt class="font-label-lg text-label-lg text-inverse-primary mb-0.5">Email</dt>
                        <dd class="font-body-md text-body-md text-inverse-on-surface/80">hello@brread.id</dd>
                    </div>
                </div>
            </dl>

            <div class="mt-space-xl flex justify-center gap-space-sm" data-reveal>
                <a href="#" class="hover-wiggle press-scale w-10 h-10 rounded-full bg-inverse-on-surface/10 hover:bg-inverse-on-surface/20 flex items-center justify-center transition-colors duration-200" aria-label="Instagram">
                    <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                </a>
                <a href="#" class="hover-wiggle press-scale w-10 h-10 rounded-full bg-inverse-on-surface/10 hover:bg-inverse-on-surface/20 flex items-center justify-center transition-colors duration-200" aria-label="Facebook">
                    <span class="material-symbols-outlined text-[18px]">thumb_up</span>
                </a>
                <a href="#" class="hover-wiggle press-scale w-10 h-10 rounded-full bg-inverse-on-surface/10 hover:bg-inverse-on-surface/20 flex items-center justify-center transition-colors duration-200" aria-label="WhatsApp">
                    <span class="material-symbols-outlined text-[18px]">forum</span>
                </a>
            </div>
        </div>
    </section>

@endsection
