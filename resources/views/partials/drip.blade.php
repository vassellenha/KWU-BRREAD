{{--
    Melting ice cream edge, hung from the top of a section.
    $flavor: strawberry | chocolate | vanilla | cream | peach   $seed: 0-2 (shape)   $class: extras
    $drops: false to leave out the falling droplets.
--}}
@php
    $seed = ($seed ?? 0) % 3;
    $tips = [
        0 => [[165, 69], [754, 55], [1157, 88]],
        1 => [[198, 68], [817, 102], [1150, 55]],
        2 => [[182, 55], [748, 87], [1006, 61]],
    ][$seed];
@endphp
<div class="drip drip--{{ $flavor ?? 'strawberry' }}-{{ $seed }} {{ $class ?? '' }}" aria-hidden="true">
    @if ($drops ?? true)
        @foreach ($tips as $i => [$x, $y])
            <span class="drip-drop" style="left: {{ $x }}px; top: {{ $y - 6 }}px; --delay: {{ $i * 1.9 }}s"></span>
        @endforeach
    @endif
</div>
