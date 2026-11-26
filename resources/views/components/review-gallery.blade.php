@props(['review', 'alt' => 'Photo of a Brread! sandwich'])

@php
    // A review can carry several photos; the launch photos and old data have just the one.
    $photos = $review['photos'] ?? [[
        'photo' => $review['photo'],
        'width' => $review['width'] ?? null,
        'height' => $review['height'] ?? null,
        'frame' => $review['frame'],
    ]];
    $count = count($photos);
@endphp

<div {{ $attributes->class(['review-gallery', 'w-full']) }} @if ($count > 1) x-data="{ i: 0 }" @endif>
    @foreach ($photos as $n => $photo)
        <div class="review-frame w-full mb-space-sm" style="{{ $photo['frame'] }}" @if ($count > 1) x-show="i === {{ $n }}" @if ($n > 0) x-cloak @endif @endif>
            <img class="review-frame__img" src="{{ $photo['photo'] }}" @if (! empty($photo['width'])) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif alt="{{ $alt }}{{ $count > 1 ? ' ('.($n + 1).' of '.$count.')' : '' }}" loading="lazy">
        </div>
    @endforeach

    @if ($count > 1)
        <div class="review-gallery__dots" role="group" aria-label="Photos in this review">
            @foreach ($photos as $n => $photo)
                <button type="button" class="review-gallery__dot" :class="{ 'is-on': i === {{ $n }} }" @click="i = {{ $n }}" :aria-pressed="i === {{ $n }}" aria-label="Show photo {{ $n + 1 }} of {{ $count }}">
                    <img src="{{ $photo['photo'] }}" alt="" loading="lazy">
                </button>
            @endforeach
        </div>
    @endif
</div>
