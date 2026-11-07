@extends('layouts.staff')

@section('title', 'Sales')

@section('content')

    @php
        $rupiah = fn (float $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
        $count = fn (float $amount): string => number_format($amount, 0, ',', '.');
        $compact = fn (float $amount): string => match (true) {
            $amount >= 1000000 => round($amount / 1000000, 1).'M',
            $amount >= 1000 => round($amount / 1000, 1).'K',
            default => (string) $amount,
        };
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary';

        $today = today();
        $presets = [
            'Last 7 days' => [$today->copy()->subDays(6), $today],
            'Last 30 days' => [$today->copy()->subDays(29), $today],
            'Last 90 days' => [$today->copy()->subDays(89), $today],
            'This month' => [$today->copy()->startOfMonth(), $today],
        ];

        $previousPeriod = 'previous '.$report['days'].' '.Str::plural('day', $report['days']);
        $hasSales = $report['stats']['orders'] > 0;

        $statTiles = [
            ['label' => 'Revenue', 'icon' => 'payments', 'key' => 'revenue', 'format' => $rupiah],
            ['label' => 'Sandwiches sold', 'icon' => 'icecream', 'key' => 'sandwiches', 'format' => $count],
            ['label' => 'Orders', 'icon' => 'receipt_long', 'key' => 'orders', 'format' => $count],
            ['label' => 'Average order', 'icon' => 'shopping_bag', 'key' => 'average_order', 'format' => $rupiah],
        ];

        $tallestCombination = $report['combinations']->max('units') ?: 1;
        $unitPoints = fn (array $points): array => array_map(fn (array $point) => [
            'label' => $point['label'],
            'value' => (float) $point['units'],
            'display' => $count($point['units']).' sold',
        ], $points);
        $revenuePoints = array_map(fn (array $point) => [
            'label' => $point['label'],
            'value' => $point['revenue'],
            'display' => $rupiah($point['revenue']),
        ], $report['trend']);

        $peakLabel = function (array $points): ?string {
            $peak = collect($points)->sortByDesc('units')->first();

            return $peak && $peak['units'] > 0 ? $peak['label'] : null;
        };
    @endphp

    <div class="mb-space-lg">
        <h1 class="font-headline-md text-headline-md text-on-surface">Sales</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
            What customers actually buy: the best-selling combinations, ingredients, and when orders come in.
        </p>
    </div>

    {{-- DATE RANGE FILTER --}}
    <div class="flex flex-wrap items-end gap-x-space-lg gap-y-space-sm mb-space-lg">
        <nav aria-label="Date range presets" class="flex flex-wrap gap-space-xs">
            @foreach ($presets as $presetLabel => [$presetFrom, $presetTo])
                @php $isActivePreset = $from->isSameDay($presetFrom) && $to->isSameDay($presetTo); @endphp
                <a
                    href="{{ route('staff.sales.index', ['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString()]) }}"
                    @if ($isActivePreset) aria-current="true" @endif
                    class="press-scale px-space-md py-space-xs rounded-full font-label-md text-label-md {{ $isActivePreset ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                >
                    {{ $presetLabel }}
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('staff.sales.index') }}" class="flex flex-wrap items-end gap-space-sm">
            <div>
                <label for="from" class="block font-label-md text-label-md text-on-surface-variant mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" max="{{ $today->toDateString() }}" class="{{ $fieldClasses }}">
            </div>
            <div>
                <label for="to" class="block font-label-md text-label-md text-on-surface-variant mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" max="{{ $today->toDateString() }}" class="{{ $fieldClasses }}">
            </div>
            <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95">
                Apply
            </button>
        </form>
    </div>

    {{-- HEADLINE FIGURES --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-lg">
        @foreach ($statTiles as $tile)
            @php
                $current = $report['stats'][$tile['key']];
                $previous = $report['previous'][$tile['key']];
                $change = $previous > 0 ? round(($current - $previous) / $previous * 100) : null;
            @endphp
            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
                <div class="flex items-center gap-space-xs mb-1">
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">{{ $tile['icon'] }}</span>
                    <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">{{ $tile['label'] }}</span>
                </div>
                <p class="font-headline-sm text-headline-sm text-on-surface">{{ $tile['format']($current) }}</p>
                @if ($current > 0 || $previous > 0)
                    <p class="mt-1 flex items-center gap-1 font-body-sm text-body-sm text-on-surface-variant">
                        @if ($change === null)
                            No sales in the {{ $previousPeriod }}
                        @elseif ($change == 0)
                            Same as the {{ $previousPeriod }}
                        @else
                            <span class="material-symbols-outlined text-[16px] {{ $change > 0 ? 'text-tertiary' : 'text-error' }}">{{ $change > 0 ? 'trending_up' : 'trending_down' }}</span>
                            {{ $change > 0 ? '+' : '' }}{{ $change }}% vs {{ $previousPeriod }}
                        @endif
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    @if (! $hasSales)
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 px-space-md py-space-xl text-center">
            <div class="flex flex-col items-center gap-space-xs">
                <span class="material-symbols-outlined text-[40px] text-on-surface-variant/40">monitoring</span>
                <p class="font-title-md text-title-md text-on-surface-variant">No sales in this period</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant/70 max-w-xs">Every order placed through the storefront is counted here. Try a wider date range.</p>
            </div>
        </div>
    @else
        {{-- TREND --}}
        <section x-data="{ metric: 'units' }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md mb-space-lg">
            <div class="flex items-center justify-between flex-wrap gap-space-sm">
                <h2 class="font-title-md text-title-md text-on-surface">Sales trend</h2>
                <div role="group" aria-label="Chart measure" class="flex gap-1 p-1 rounded-full bg-surface-container">
                    <button type="button" @click="metric = 'units'" :aria-pressed="metric === 'units'" :class="metric === 'units' ? 'bg-surface-container-lowest text-on-surface shadow-sm' : 'text-on-surface-variant'" class="px-space-sm py-1 rounded-full font-label-md text-label-md">Sandwiches</button>
                    <button type="button" @click="metric = 'revenue'" :aria-pressed="metric === 'revenue'" :class="metric === 'revenue' ? 'bg-surface-container-lowest text-on-surface shadow-sm' : 'text-on-surface-variant'" class="px-space-sm py-1 rounded-full font-label-md text-label-md">Revenue</button>
                </div>
            </div>
            <div x-show="metric === 'units'">
                @include('staff.sales.partials.column-chart', [
                    'points' => $unitPoints($report['trend']),
                    'description' => 'Sandwiches sold over time',
                    'tableHeading' => 'Sandwiches',
                    'formatAxis' => $compact,
                ])
            </div>
            <div x-show="metric === 'revenue'" x-cloak>
                @include('staff.sales.partials.column-chart', [
                    'points' => $revenuePoints,
                    'description' => 'Revenue over time',
                    'tableHeading' => 'Revenue',
                    'formatAxis' => $compact,
                ])
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-space-md mb-space-lg items-start">
            {{-- BEST-SELLING COMBINATIONS --}}
            <section class="xl:col-span-3 bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
                <h2 class="font-title-md text-title-md text-on-surface">Best-selling combinations</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                    @if ($report['combination_count'] > $report['combinations']->count())
                        Top {{ $report['combinations']->count() }} of {{ $report['combination_count'] }} combinations sold.
                    @else
                        {{ $report['combination_count'] }} {{ Str::plural('combination', $report['combination_count']) }} sold.
                    @endif
                </p>

                <ol class="mt-space-sm space-y-space-sm">
                    @foreach ($report['combinations'] as $combination)
                        <li class="flex gap-space-sm">
                            <span class="w-5 shrink-0 font-label-lg text-label-lg text-on-surface-variant tabular-nums">{{ $loop->iteration }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-space-sm">
                                    <div class="min-w-0">
                                        <p class="font-label-lg text-label-lg text-on-surface">{{ $combination['label'] }}</p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $combination['toppings'] }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="font-label-lg text-label-lg text-on-surface tabular-nums">{{ $count($combination['units']) }} sold</p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant tabular-nums">{{ $rupiah($combination['revenue']) }}</p>
                                    </div>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-surface-container">
                                    <div class="h-2 rounded-full bg-primary-container" style="width: {{ $combination['units'] / $tallestCombination * 100 }}%"></div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- INGREDIENT RANKINGS --}}
            <div class="xl:col-span-2 grid grid-cols-1 gap-space-md">
                @include('staff.sales.partials.ingredient-ranking', ['title' => 'Ice cream flavors', 'rankings' => $report['flavors']])
                @include('staff.sales.partials.ingredient-ranking', ['title' => 'Bread', 'rankings' => $report['breads']])
                @include('staff.sales.partials.ingredient-ranking', [
                    'title' => 'Toppings',
                    'note' => round($report['topping_rate']).'% of sandwiches have at least one topping.',
                    'rankings' => $report['toppings'],
                ])
            </div>
        </div>

        {{-- WHEN ORDERS COME IN --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
            <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
                <h2 class="font-title-md text-title-md text-on-surface">Busiest days</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Peak: {{ $peakLabel($report['weekdays']) }}</p>
                @include('staff.sales.partials.column-chart', [
                    'points' => $unitPoints($report['weekdays']),
                    'description' => 'Sandwiches sold by day of the week',
                    'tableHeading' => 'Sandwiches',
                    'formatAxis' => $compact,
                    'highlightPeak' => true,
                ])
            </section>
            <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
                <h2 class="font-title-md text-title-md text-on-surface">Busiest hours</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Peak: {{ $peakLabel($report['hours']) }} WIB</p>
                @include('staff.sales.partials.column-chart', [
                    'points' => $unitPoints($report['hours']),
                    'description' => 'Sandwiches sold by hour of the day',
                    'tableHeading' => 'Sandwiches',
                    'formatAxis' => $compact,
                    'highlightPeak' => true,
                ])
            </section>
        </div>
    @endif

@endsection
