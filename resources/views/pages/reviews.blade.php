@extends('layouts.app')

@section('title', 'Customer Photos')

@section('description', 'Photos and reviews from Brread! customers: the thirty newest, straight from their order pages.')

@section('content')
    @php
        $tilts = ['-2deg', '1.5deg', '-1deg', '2deg', '-1.5deg', '1deg'];
        $tapes = ['-4deg', '3deg', '-2deg', '4deg', '-3deg', '2deg'];
    @endphp

    <div class="w-full font-plus-jakarta">
        <section class="doodle relative bg-surface-container-low pt-44 pb-space-xl">
            <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
                <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-space-sm mb-space-xl" data-reveal>
                    <div>
                        <p class="font-hand text-[30px] leading-none text-primary -rotate-2 origin-left mb-space-sm">made by our customers</p>
                        <h1 class="font-headline-lg text-[clamp(2.4rem,6vw,4rem)] leading-[0.95] font-extrabold text-on-surface">Straight from the order slips</h1>
                        @include('partials.squiggle')
                    </div>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">The {{ \App\Support\ReviewFeed::WALL_MAX }} newest photos customers added to their reviews, newest first. Every one comes from a real order page.</p>
                </div>

                @if ($photos->isEmpty())
                    <div class="review-slip max-w-xl mx-auto text-center" data-reveal>
                        <p class="review-slip__quote">No customer photos yet. Order a sandwich, then add a photo to your review on the order page and it shows up here.</p>
                        <p class="review-slip__who"><a href="{{ route('build') }}" class="text-primary font-bold underline underline-offset-4">Build one in the Studio</a></p>
                    </div>
                @else
                    <div class="review-wall">
                        @foreach ($photos as $i => $review)
                            <figure class="polaroid" style="--tilt: {{ $tilts[$i % 6] }}; --tape: {{ $tapes[$i % 6] }}" data-reveal>
                                <div class="review-frame w-full mb-space-sm" style="{{ $review['frame'] }}">
                                    <img class="review-frame__img" src="{{ $review['photo'] }}" width="{{ $review['width'] }}" height="{{ $review['height'] }}" alt="Photo from {{ $review['name'] }} of their Brread! sandwich" loading="lazy">
                                </div>
                                <figcaption class="mb-space-xs">
                                    <div class="flex items-center justify-between gap-space-sm">
                                        <span class="font-hand text-[28px] leading-none text-on-surface truncate">{{ $review['name'] }}</span>
                                        <span class="flex shrink-0 text-primary" role="img" aria-label="{{ $review['rating'] }} out of 5 stars">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <span class="material-symbols-outlined text-[16px] {{ $star <= $review['rating'] ? '' : 'opacity-25' }}" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">star</span>
                                            @endfor
                                        </span>
                                    </div>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Made: {{ $review['made'] }} · {{ $review['when'] }}</span>
                                </figcaption>
                                <blockquote class="font-body-md text-body-md text-on-surface-variant [overflow-wrap:anywhere]">“{{ $review['quote'] }}”</blockquote>
                            </figure>
                        @endforeach
                    </div>
                @endif

                @if ($words->isNotEmpty())
                    <div class="mt-space-xl" data-reveal>
                        <p class="font-hand text-[26px] leading-none text-primary -rotate-1 mb-space-md">and the ones that said it in words</p>
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
            </div>
        </section>
    </div>
@endsection
