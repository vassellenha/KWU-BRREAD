{{--
    Single-series column chart drawn with plain HTML, so it needs no chart library or script.

    $points         list of ['label' => string, 'value' => float, 'display' => string]
    $description    what the chart shows, read out by screen readers
    $tableHeading   heading of the value column in the table view
    $formatAxis     fn (float): string for the gridline labels
    $highlightPeak  true draws the tallest column in the accent and the rest in gray
--}}
@php
    use App\Services\SalesReport;

    $highlightPeak ??= false;
    $tallest = collect($points)->max('value');
    $ceiling = SalesReport::axisCeiling($tallest);
    $pointCount = count($points);
    $labelEvery = $pointCount <= 7 ? 1 : (int) ceil($pointCount / 6);

    // A half-way gridline only earns its place on a value someone could actually sell.
    $gridlines = collect([$ceiling, $ceiling / 2])->filter(fn (float $value) => floor($value) === $value);

    $tooltipAlignment = function (int $index) use ($pointCount): string {
        $position = $pointCount > 1 ? $index / ($pointCount - 1) : 0.5;

        return match (true) {
            $position < 0.25 => 'left-0',
            $position > 0.75 => 'right-0',
            default => 'left-1/2 -translate-x-1/2',
        };
    };

    // Short labels sit under their column. Longer ones only pull in at the chart's two edges.
    $axisLabelAlignment = fn (int $index): string => match (true) {
        $pointCount > 7 && $index === 0 => 'left-0',
        $pointCount > 7 && $index === $pointCount - 1 => 'right-0',
        default => 'left-1/2 -translate-x-1/2',
    };
@endphp

<div role="group" aria-label="{{ $description }}" class="relative h-44 ml-10 mt-space-md mb-7">
    <div class="absolute inset-x-0 bottom-0 border-t border-outline-variant"></div>
    <span class="absolute -left-10 bottom-0 translate-y-1/2 w-8 text-right font-label-md text-label-md text-on-surface-variant tabular-nums">0</span>

    @foreach ($gridlines as $gridline)
        <div class="absolute inset-x-0 border-t border-outline-variant/30" style="bottom: {{ $gridline / $ceiling * 100 }}%"></div>
        <span class="absolute -left-10 translate-y-1/2 w-8 text-right font-label-md text-label-md text-on-surface-variant tabular-nums" style="bottom: {{ $gridline / $ceiling * 100 }}%">{{ $formatAxis($gridline) }}</span>
    @endforeach

    <div class="absolute inset-0 flex items-end gap-0.5">
        @foreach ($points as $index => $point)
            @php
                $height = $point['value'] / $ceiling * 100;
                $isPeak = $point['value'] > 0 && $point['value'] === $tallest;
                $isLabelled = $index % $labelEvery === 0;
                $isDesktopOnlyLabel = $pointCount > 7 && $index % ($labelEvery * 2) !== 0;
            @endphp
            <div tabindex="0" role="img" aria-label="{{ $point['label'] }}: {{ $point['display'] }}" class="group relative flex-1 min-w-0 h-full flex items-end justify-center rounded-t-[4px] hover:bg-surface-container/60 focus:bg-surface-container/60">
                @if ($point['value'] > 0)
                    <div class="w-full max-w-6 min-h-0.5 rounded-t-[4px] {{ ! $highlightPeak || $isPeak ? 'bg-primary-container' : 'bg-outline-variant' }}" style="height: {{ $height }}%"></div>
                @endif

                <div class="hidden group-hover:block group-focus:block absolute z-20 {{ $tooltipAlignment($index) }} px-space-sm py-1 rounded bg-inverse-surface text-inverse-on-surface whitespace-nowrap pointer-events-none" style="bottom: calc({{ $height }}% + 0.375rem)">
                    <p class="font-label-lg text-label-lg">{{ $point['display'] }}</p>
                    <p class="font-label-md text-label-md opacity-80">{{ $point['label'] }}</p>
                </div>

                @if ($isLabelled)
                    <span class="{{ $isDesktopOnlyLabel ? 'hidden sm:block' : '' }} absolute top-full mt-1.5 {{ $axisLabelAlignment($index) }} font-label-md text-label-md text-on-surface-variant whitespace-nowrap">{{ $point['label'] }}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>

<details class="mt-space-sm">
    <summary class="cursor-pointer font-label-md text-label-md text-on-surface-variant hover:text-on-surface">View as table</summary>
    <div class="mt-space-xs max-h-56 overflow-y-auto rounded border border-outline-variant/30">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                    <th scope="col" class="px-space-sm py-1.5 font-label-md text-label-md text-on-surface-variant">Period</th>
                    <th scope="col" class="px-space-sm py-1.5 font-label-md text-label-md text-on-surface-variant text-right">{{ $tableHeading }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($points as $point)
                    <tr class="border-b border-outline-variant/20 last:border-0">
                        <td class="px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface-variant">{{ $point['label'] }}</td>
                        <td class="px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface text-right tabular-nums">{{ $point['display'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
