{{--
    One Singapore-style ice cream sandwich in a single 3/4 view: ONE slice of bread folded in half like a book
    around a slab of ice cream that lies flat. The fold (spine) is on the left, the lower half sits under the
    slab, the upper half folds over the top and rests on it. The front and the right end stay open, so the slab
    pokes out of the bread there. Tapping the sandwich flips the upper half open like a book lid (order-fx.js).

    Everything comes from the order: the bread picked (white or wheat), the flavour picked, and only the
    toppings picked are drawn on the slab.

    Layers, back to front: lower half > slab > fold > upper half.

    Props: $creation (art keys), $uid (unique per sandwich on the page), $art (asset helper),
           $still (true = final pose only, used for the small popup version).
--}}
@php
    $still = $still ?? false;
    $breadKey = $creation['art']['bread'];
    $wheat = str_contains($breadKey, 'wheat');
    $bread = $art($breadKey);
    $ice = $art($creation['art']['ice']);
    $toppings = $creation['art']['toppings'] ?? [];
    $hasSauce = in_array('top-sauce', $toppings, true);
    $hasSprinkles = in_array('top-sprinkles', $toppings, true);
    // Pieces that sit on the top face (they are not pressed into the cut sides like sprinkles).
    $pieces = array_values(array_intersect(['top-marshmallow', 'top-gummy-bears'], $toppings));
    $crustVars = $wheat
        ? '--crust: #a8693a; --crust-d: #7a4624; --crumb: #c9a06c; --tint: rgba(70, 35, 8, 0.12)'
        : '--crust: #e6ad62; --crust-d: #bf7d33; --crumb: #f7ecd4; --tint: rgba(222, 164, 86, 0.34)';

    // A point list for an SVG polygon.
    $poly = fn (array $points): string => implode(' ', array_map(fn (array $p): string => round($p[0], 1).','.round($p[1], 1), $points));

    // Lower half of the slice: lies flat under the slab. Depth runs up and to the right.
    $lower = fn (float $u, float $v): array => [62 + $u + 0.55 * $v, 268 - 0.43 * $v];
    // Upper half: same slice, hinged on the left and closed flat on the slab.
    $upper = fn (float $u, float $v): array => [62 + $u + 0.55 * $v, 196 - 0.43 * $v];
    // The slab: top face (u along the length, v along the depth).
    $slab = fn (float $u, float $v): array => [66 + $u + 0.46 * $v, 210 - 0.36 * $v];

    $lowerTop = $poly([$lower(0, 0), $lower(180, 0), $lower(180, 100), $lower(0, 100)]);
    $lowerCrumb = $poly([$lower(0, 11), $lower(170, 11), $lower(170, 89), $lower(0, 89)]);
    $lowerFront = $poly([[62, 268], [242, 268], [242, 282], [62, 282]]);
    $lowerEnd = $poly([[242, 268], $lower(180, 100), [$lower(180, 100)[0], $lower(180, 100)[1] + 14], [242, 282]]);

    $upperTop = $poly([$upper(0, 0), $upper(180, 0), $upper(180, 100), $upper(0, 100)]);
    $upperCrumb = $poly([$upper(0, 11), $upper(170, 11), $upper(170, 89), $upper(0, 89)]);
    $upperFront = $poly([$upper(0, 0), $upper(180, 0), [$upper(180, 0)[0], $upper(180, 0)[1] + 14], [62, 210]]);
    $upperEnd = $poly([$upper(180, 0), $upper(180, 100), [$upper(180, 100)[0], $upper(180, 100)[1] + 14], [$upper(180, 0)[0], $upper(180, 0)[1] + 14]]);

    $slabTop = $poly([$slab(0, 0), $slab(196, 0), $slab(196, 100), $slab(0, 100)]);
    $slabFront = $poly([[66, 210], [262, 210], [262, 262], [66, 262]]);
    $slabEnd = $poly([[262, 210], $slab(196, 100), [$slab(196, 100)[0], $slab(196, 100)[1] + 52], [262, 262]]);

    $mxLower = 'matrix(1 0 0.55 -0.43 62 268)';
    $mxUpper = 'matrix(1 0 0.55 -0.43 62 196)';
    $mxSlabTop = 'matrix(1 0 0.46 -0.36 66 210)';
    $mxSlabFront = 'translate(66 210)';
    $mxSlabEnd = 'matrix(0.46 -0.36 0 1 262 210)';
@endphp
<svg class="fs {{ $still ? 'fs--still' : 'fs--anim' }}" viewBox="26 100 306 196" role="presentation" aria-hidden="true" focusable="false" style="{{ $crustVars }}">
    <defs>
        <linearGradient id="{{ $uid }}-cr" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" style="stop-color: var(--crust)"/>
            <stop offset="1" style="stop-color: var(--crust-d)"/>
        </linearGradient>
        <pattern id="{{ $uid }}-sk" width="15" height="15" patternUnits="userSpaceOnUse">
            <circle cx="3" cy="4" r="1.3" fill="#5a2d0f" fill-opacity="0.28"/>
            <circle cx="11" cy="9" r="1.1" fill="#5a2d0f" fill-opacity="0.22"/>
            <circle cx="6" cy="13" r="0.9" fill="#fff" fill-opacity="0.3"/>
        </pattern>
        <clipPath id="{{ $uid }}-lc"><polygon points="{{ $lowerCrumb }}"/></clipPath>
        <clipPath id="{{ $uid }}-uc"><polygon points="{{ $upperCrumb }}"/></clipPath>
        <clipPath id="{{ $uid }}-st"><polygon points="{{ $slabTop }}"/></clipPath>
        <clipPath id="{{ $uid }}-sf"><polygon points="{{ $slabFront }}"/></clipPath>
        <clipPath id="{{ $uid }}-se"><polygon points="{{ $slabEnd }}"/></clipPath>
    </defs>

    <g transform="rotate(-3 180 200)">
        {{-- Lower half of the slice, under the slab --}}
        <g class="fs-part fs-part--lower">
            <polygon points="{{ $lowerEnd }}" fill="var(--crust-d)"/>
            <polygon points="{{ $lowerFront }}" fill="url(#{{ $uid }}-cr)"/>
            <polygon points="{{ $lowerFront }}" fill="url(#{{ $uid }}-sk)"/>
            <polygon points="{{ $lowerTop }}" fill="url(#{{ $uid }}-cr)"/>
            <polygon points="{{ $lowerTop }}" fill="url(#{{ $uid }}-sk)"/>
            <g clip-path="url(#{{ $uid }}-lc)">
                <rect x="0" y="0" width="180" height="100" fill="var(--crumb)" transform="{{ $mxLower }}"/>
                <image href="{{ $bread }}" x="0" y="0" width="180" height="100" preserveAspectRatio="xMidYMid slice" transform="{{ $mxLower }}"/>
            </g>
            <polygon points="{{ $lowerCrumb }}" fill="none" stroke="#8d5a26" stroke-opacity="0.5" stroke-width="2"/>
            <polygon class="fs-ln" points="{{ $lowerEnd }}"/>
            <polygon class="fs-ln" points="{{ $lowerFront }}"/>
            <polygon class="fs-ln" points="{{ $lowerTop }}"/>
        </g>

        {{-- The slab of ice cream, lying flat: top, front and open right end --}}
        <g class="fs-part fs-part--slab">
            <g class="fs-bounce">
            <g clip-path="url(#{{ $uid }}-se)">
                {{-- Only the right-hand end of the cutout, so a layered flavor shows at its open end the layer that ends its front. --}}
                <image href="{{ $ice }}" x="-230" y="0" width="330" height="52" preserveAspectRatio="xMaxYMid slice" transform="{{ $mxSlabEnd }}"/>
                <rect x="256" y="160" width="60" height="110" fill="#3b1f16" fill-opacity="0.24"/>
                @if ($hasSprinkles)
                    <g class="fs-sprk"><image class="fs-top" href="{{ $art('top-sprinkles') }}" x="0" y="0" width="100" height="52" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabEnd }}"/></g>
                @endif
            </g>
            <polygon class="fs-ln" points="{{ $slabEnd }}"/>

            <g clip-path="url(#{{ $uid }}-sf)">
                <image href="{{ $ice }}" x="0" y="0" width="196" height="52" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabFront }}"/>
                <rect x="60" y="205" width="206" height="60" fill="#3b1f16" fill-opacity="0.07"/>
                @if ($hasSprinkles)
                    <g class="fs-sprk"><image class="fs-top" href="{{ $art('top-sprinkles') }}" x="0" y="0" width="196" height="52" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabFront }}"/></g>
                @endif
                @if ($hasSauce)
                    <g class="fs-sauce"><path class="fs-top fs-drip" d="M192 208v17a5.5 5.5 0 0 0 11 0v-17zM222 208v26a5.5 5.5 0 0 0 11 0v-26zM246 208v12a5 5 0 0 0 10 0v-12z"/></g>
                @endif
            </g>
            <polygon class="fs-ln" points="{{ $slabFront }}"/>

            <g clip-path="url(#{{ $uid }}-st)">
                <image href="{{ $ice }}" x="0" y="0" width="196" height="100" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabTop }}"/>
                <rect x="60" y="165" width="260" height="50" fill="#fff" fill-opacity="0.16"/>
                @if ($hasSauce)
                    <g class="fs-sauce"><image class="fs-top" href="{{ $art('top-sauce') }}" x="8" y="12" width="182" height="76" preserveAspectRatio="none" transform="{{ $mxSlabTop }}"/></g>
                @endif
                @if ($hasSprinkles)
                    <g class="fs-sprk"><image class="fs-top" href="{{ $art('top-sprinkles') }}" x="0" y="0" width="196" height="100" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabTop }}"/></g>
                @endif
                @foreach ($pieces as $piece)
                    <g class="fs-sprk"><image class="fs-top" href="{{ $art($piece) }}" x="{{ $loop->index * 7 }}" y="{{ $loop->index * 5 }}" width="196" height="100" preserveAspectRatio="xMidYMid slice" transform="{{ $mxSlabTop }}"/></g>
                @endforeach
            </g>
            <polygon class="fs-ln" points="{{ $slabTop }}"/>
            </g>
        </g>

        {{-- The fold: the rounded spine on the left where the slice bends, crust running round it --}}
        <g class="fs-part fs-part--fold">
            <path d="M64 196C36 196 36 282 64 282V210Z" fill="url(#{{ $uid }}-cr)"/>
            <path d="M64 196C36 196 36 282 64 282V210Z" fill="url(#{{ $uid }}-sk)"/>
            <path d="M62 207C48 214 48 264 62 271" fill="none" stroke="#8d5a26" stroke-opacity="0.55" stroke-width="2.5" stroke-linecap="round"/>
            <path class="fs-ln" d="M64 196C36 196 36 282 64 282"/>
        </g>

        {{-- Upper half of the slice: folds over the top, lifted a little so the slab shows --}}
        <g class="fs-part fs-part--upper">
            <g class="fs-flap">
            <polygon points="{{ $upperEnd }}" fill="var(--crust-d)"/>
            <polygon points="{{ $upperFront }}" fill="url(#{{ $uid }}-cr)"/>
            <polygon points="{{ $upperFront }}" fill="url(#{{ $uid }}-sk)"/>
            <polygon points="{{ $upperTop }}" fill="url(#{{ $uid }}-cr)"/>
            <polygon points="{{ $upperTop }}" fill="url(#{{ $uid }}-sk)"/>
            <g clip-path="url(#{{ $uid }}-uc)">
                <rect x="0" y="0" width="180" height="100" fill="var(--crumb)" transform="{{ $mxUpper }}"/>
                <image href="{{ $bread }}" x="0" y="0" width="180" height="100" preserveAspectRatio="xMidYMid slice" transform="{{ $mxUpper }}"/>
                <rect x="0" y="0" width="180" height="100" fill="var(--tint)" transform="{{ $mxUpper }}"/>
            </g>
            <polygon points="{{ $upperCrumb }}" fill="none" stroke="#8d5a26" stroke-opacity="0.5" stroke-width="2"/>
            <polygon class="fs-ln" points="{{ $upperEnd }}"/>
            <polygon class="fs-ln" points="{{ $upperFront }}"/>
            <polygon class="fs-ln" points="{{ $upperTop }}"/>
            </g>
        </g>
    </g>
</svg>
