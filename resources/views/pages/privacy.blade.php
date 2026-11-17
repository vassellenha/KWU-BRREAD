@extends('layouts.app')

@section('title', 'Privacy')

@section('description', 'How Brread! collects, uses and protects the information you share when you place an order.')

@section('content')
    <div class="w-full bg-surface-container-low font-plus-jakarta">
        <div class="max-w-[720px] mx-auto px-margin md:px-margin-tablet py-space-xl space-y-space-lg">
            <header class="space-y-space-xs">
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Privacy</h1>
                <p class="font-body-lg text-body-lg text-on-surface-variant">
                    The short version: we ask for the least we need to make and deliver your order.
                </p>
            </header>

            <section class="space-y-space-xs">
                <h2 class="font-title-md text-title-md text-on-surface">What we collect</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Your name and phone number at checkout, the items in your order, and, if you leave one,
                    the rating and comment on your order page.
                </p>
            </section>

            <section class="space-y-space-xs">
                <h2 class="font-title-md text-title-md text-on-surface">What we use it for</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Our team uses your name and number to confirm the order, reach you about delivery and
                    show you its status. We do not run ads, analytics trackers or session recording on this site.
                </p>
            </section>

            <section class="space-y-space-xs">
                <h2 class="font-title-md text-title-md text-on-surface">Who can see it</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Staff who handle orders can see your order details. The data is stored with our hosting
                    providers so the site can run. We do not sell it.
                </p>
            </section>

            <section class="space-y-space-xs">
                <h2 class="font-title-md text-title-md text-on-surface">Security records</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    When someone sends attack code to our staff sign-in or tries to sign in too many times,
                    we record the IP address, the browser and device it reports, the approximate city and
                    network provider for that address, and what was typed as the username. Only the owner can
                    see these records, we use them to protect the site and, if needed, to report an attack to
                    the authorities, and we delete them after {{ config('services.security_events.keep_days') }} days.
                </p>
            </section>

            <section class="space-y-space-xs">
                <h2 class="font-title-md text-title-md text-on-surface">Questions or removal</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    To ask about your data or have it removed, reach us through the
                    <a href="{{ route('contact') }}" class="underline hover:text-primary">Contact</a> page.
                </p>
            </section>
        </div>
    </div>
@endsection
