<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SalesReport
{
    private const DAILY_TREND_MAX_DAYS = 45;

    private const WEEKLY_TREND_MAX_DAYS = 240;

    private const TOP_COMBINATIONS = 10;

    private const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /**
     * @return array{
     *     stats: array{revenue: float, sandwiches: int, orders: int, average_order: float},
     *     previous: array{revenue: float, sandwiches: int, orders: int, average_order: float},
     *     days: int,
     *     combinations: Collection<int, array{label: string, toppings: string, units: int, revenue: float}>,
     *     combination_count: int,
     *     flavors: Collection<int, array{name: string, units: int, share: float}>,
     *     breads: Collection<int, array{name: string, units: int, share: float}>,
     *     toppings: Collection<int, array{name: string, units: int, share: float}>,
     *     topping_rate: float,
     *     trend: list<array{label: string, units: int, revenue: float}>,
     *     weekdays: list<array{label: string, units: int}>,
     *     hours: list<array{label: string, units: int}>
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();

        $orders = $this->ordersBetween($from, $to);
        $creations = $this->creationsIn($orders);
        $sandwiches = $creations->sum('quantity');
        $components = Component::orderBy('name')->get();

        return [
            'stats' => $this->headlineFigures($orders, $creations),
            'previous' => $this->headlineFigures(
                $previousOrders = $this->ordersBetween($previousTo->subDays($days - 1), $previousTo),
                $this->creationsIn($previousOrders),
            ),
            'days' => $days,
            'combinations' => ($combinations = $this->combinations($creations))->take(self::TOP_COMBINATIONS)->values(),
            'combination_count' => $combinations->count(),
            'flavors' => $this->ingredientRanking($components, 'ice_cream', $creations, $sandwiches),
            'breads' => $this->ingredientRanking($components, 'bread', $creations, $sandwiches),
            'toppings' => $this->ingredientRanking($components, 'topping', $creations, $sandwiches),
            'topping_rate' => $sandwiches > 0
                ? $creations->filter(fn (array $creation) => $creation['toppings']->isNotEmpty())->sum('quantity') / $sandwiches * 100
                : 0.0,
            'trend' => $this->trend($creations, $from, $to, $days),
            'weekdays' => $this->weekdays($creations),
            'hours' => $this->hours($creations),
        ];
    }

    /**
     * The lowest round number at or above the tallest bar, so the top gridline reads cleanly.
     */
    public static function axisCeiling(float $tallest): float
    {
        if ($tallest <= 0) {
            return 1;
        }

        $magnitude = 10 ** floor(log10($tallest));

        foreach ([1, 2, 3, 4, 5, 6, 8] as $step) {
            if ($tallest <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::with('orderItems.component')
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->get();
    }

    /**
     * Rebuilds each sandwich from its order item rows, one row per ingredient.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{ordered_at: mixed, quantity: int, revenue: float, ingredients: Collection<int, Component>, toppings: Collection<int, Component>}>
     */
    private function creationsIn(Collection $orders): Collection
    {
        return $orders->flatMap(fn (Order $order) => $order->orderItems
            ->groupBy('creation_index')
            ->map(function (Collection $rows) use ($order) {
                $quantity = (int) $rows->first()->quantity;
                $ingredients = $rows->map(fn (OrderItem $row) => $row->component);

                return [
                    'ordered_at' => $order->created_at,
                    'quantity' => $quantity,
                    'revenue' => $rows->sum(fn (OrderItem $row) => (float) $row->unit_price) * $quantity,
                    'ingredients' => $ingredients,
                    'toppings' => $ingredients->where('category', 'topping')->sortBy('name')->values(),
                ];
            })
            ->values());
    }

    /**
     * Revenue comes from the order totals so it always agrees with income in Accounting.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return array{revenue: float, sandwiches: int, orders: int, average_order: float}
     */
    private function headlineFigures(Collection $orders, Collection $creations): array
    {
        $revenue = (float) $orders->sum('total_price');

        return [
            'revenue' => $revenue,
            'sandwiches' => (int) $creations->sum('quantity'),
            'orders' => $orders->count(),
            'average_order' => $orders->isEmpty() ? 0.0 : $revenue / $orders->count(),
        ];
    }

    /**
     * The same ingredients in any layer order count as one combination.
     *
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return Collection<int, array{label: string, toppings: string, units: int, revenue: float}>
     */
    private function combinations(Collection $creations): Collection
    {
        return $creations
            ->groupBy(fn (array $creation) => $creation['ingredients']->pluck('id')->sort()->implode('-'))
            ->map(function (Collection $sameCombination) {
                $sample = $sameCombination->first();

                return [
                    'label' => $sample['ingredients']->firstWhere('category', 'ice_cream')?->name
                        .' + '.$sample['ingredients']->firstWhere('category', 'bread')?->name,
                    'toppings' => $sample['toppings']->isEmpty() ? 'No toppings' : $sample['toppings']->pluck('name')->implode(', '),
                    'units' => (int) $sameCombination->sum('quantity'),
                    'revenue' => (float) $sameCombination->sum('revenue'),
                ];
            })
            ->sortBy([['units', 'desc'], ['revenue', 'desc'], ['label', 'asc']])
            ->values();
    }

    /**
     * Unsold ingredients stay in the list, because knowing what isn't moving is the point.
     *
     * @param  Collection<int, Component>  $components
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return Collection<int, array{name: string, units: int, share: float}>
     */
    private function ingredientRanking(Collection $components, string $category, Collection $creations, int $sandwiches): Collection
    {
        return $components
            ->where('category', $category)
            ->map(function (Component $component) use ($creations, $sandwiches) {
                $units = (int) $creations
                    ->filter(fn (array $creation) => $creation['ingredients']->contains('id', $component->id))
                    ->sum('quantity');

                return [
                    'name' => $component->name,
                    'units' => $units,
                    'share' => $sandwiches > 0 ? $units / $sandwiches * 100 : 0.0,
                    'is_active' => $component->is_active,
                ];
            })
            ->filter(fn (array $ranking) => $ranking['is_active'] || $ranking['units'] > 0)
            ->sortByDesc('units')
            ->values();
    }

    /**
     * Daily while the bars stay readable on a phone, then weekly, then monthly.
     *
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return list<array{label: string, units: int, revenue: float}>
     */
    private function trend(Collection $creations, CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $startOfBucket = match (true) {
            $days <= self::DAILY_TREND_MAX_DAYS => fn (CarbonImmutable $date) => $date->startOfDay(),
            $days <= self::WEEKLY_TREND_MAX_DAYS => fn (CarbonImmutable $date) => $date->startOfWeek(CarbonImmutable::MONDAY),
            default => fn (CarbonImmutable $date) => $date->startOfMonth(),
        };
        $nextBucket = match (true) {
            $days <= self::DAILY_TREND_MAX_DAYS => fn (CarbonImmutable $date) => $date->addDay(),
            $days <= self::WEEKLY_TREND_MAX_DAYS => fn (CarbonImmutable $date) => $date->addWeek(),
            default => fn (CarbonImmutable $date) => $date->addMonthNoOverflow(),
        };
        $labelFormat = $days <= self::WEEKLY_TREND_MAX_DAYS ? 'd M' : 'M Y';

        $byBucket = $creations->groupBy(fn (array $creation) => $startOfBucket(CarbonImmutable::parse($creation['ordered_at']))->toDateString());

        $points = [];
        for ($bucket = $startOfBucket($from); $bucket->lessThanOrEqualTo($to); $bucket = $nextBucket($bucket)) {
            $inBucket = $byBucket->get($bucket->toDateString(), collect());

            $points[] = [
                'label' => $bucket->max($from)->format($labelFormat),
                'units' => (int) $inBucket->sum('quantity'),
                'revenue' => (float) $inBucket->sum('revenue'),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return list<array{label: string, units: int}>
     */
    private function weekdays(Collection $creations): array
    {
        $units = $creations->groupBy(fn (array $creation) => CarbonImmutable::parse($creation['ordered_at'])->dayOfWeekIso);

        return collect(self::WEEKDAYS)
            ->map(fn (string $label, int $day) => ['label' => $label, 'units' => (int) $units->get($day, collect())->sum('quantity')])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $creations
     * @return list<array{label: string, units: int}>
     */
    private function hours(Collection $creations): array
    {
        $units = $creations->groupBy(fn (array $creation) => CarbonImmutable::parse($creation['ordered_at'])->hour);

        return collect(range(0, 23))
            ->map(fn (int $hour) => [
                'label' => sprintf('%02d:00', $hour),
                'units' => (int) $units->get($hour, collect())->sum('quantity'),
            ])
            ->all();
    }
}
