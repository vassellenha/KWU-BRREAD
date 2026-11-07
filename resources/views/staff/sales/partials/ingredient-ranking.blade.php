{{--
    $title     card heading
    $note      optional line under the heading
    $rankings  list of ['name' => string, 'units' => int, 'share' => float], best seller first
--}}
<section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
    <h2 class="font-title-md text-title-md text-on-surface">{{ $title }}</h2>
    @isset($note)
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">{{ $note }}</p>
    @endisset

    <ul class="mt-space-sm space-y-space-sm">
        @foreach ($rankings as $ranking)
            <li>
                <div class="flex items-baseline justify-between gap-space-sm">
                    <span class="font-label-lg text-label-lg text-on-surface min-w-0">{{ $ranking['name'] }}</span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant tabular-nums whitespace-nowrap">{{ number_format($ranking['units'], 0, ',', '.') }} sold · {{ round($ranking['share']) }}%</span>
                </div>
                <div class="mt-1 h-2 rounded-full bg-surface-container">
                    @if ($ranking['units'] > 0)
                        <div class="h-2 rounded-full bg-primary-container" style="width: {{ max($ranking['share'], 1) }}%"></div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
