<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fixed, deterministic duty rotation: active employees (ordered by rotation_order)
 * are split into teams of GROUP_SIZE, and the teams take turns covering
 * SHIFT_LENGTH_DAYS-day blocks starting from ROTATION_ANCHOR. Nothing about a
 * shift is stored — "who's on duty when" is always recomputed from the current
 * employee list, so adding/removing/reordering staff reshapes future shifts
 * immediately without touching historical data.
 */
class ShiftScheduler
{
    public const GROUP_SIZE = 3;

    public const SHIFT_LENGTH_DAYS = 3;

    public const ROTATION_ANCHOR = '2026-11-02'; // a Monday

    /** @return Collection<int, Employee> */
    public function activeEmployees(): Collection
    {
        return Employee::where('is_active', true)
            ->orderBy('rotation_order')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Collection<int, Employee>> each inner collection is one team */
    public function teams(): Collection
    {
        return $this->activeEmployees()->chunk(self::GROUP_SIZE)->values();
    }

    public function shiftIndexForDate(CarbonImmutable $date): int
    {
        $anchor = CarbonImmutable::parse(self::ROTATION_ANCHOR)->startOfDay();
        $daysSinceAnchor = $anchor->diffInDays($date->startOfDay(), false);

        return (int) floor($daysSinceAnchor / self::SHIFT_LENGTH_DAYS);
    }

    public function periodForShiftIndex(int $shiftIndex): array
    {
        $anchor = CarbonImmutable::parse(self::ROTATION_ANCHOR)->startOfDay();
        $start = $anchor->addDays($shiftIndex * self::SHIFT_LENGTH_DAYS);

        return [$start, $start->addDays(self::SHIFT_LENGTH_DAYS - 1)];
    }

    /**
     * The team on duty for the shift period containing $date, plus that period's
     * date range. Returns an empty team collection if there are no active employees.
     */
    public function teamForDate(CarbonImmutable $date): array
    {
        $teams = $this->teams();
        $shiftIndex = $this->shiftIndexForDate($date);
        [$start, $end] = $this->periodForShiftIndex($shiftIndex);

        if ($teams->isEmpty()) {
            return ['period_start' => $start, 'period_end' => $end, 'employees' => collect()];
        }

        $teamIndex = (($shiftIndex % $teams->count()) + $teams->count()) % $teams->count();

        return ['period_start' => $start, 'period_end' => $end, 'employees' => $teams[$teamIndex]];
    }

    /** @return array<int, array{period_start: CarbonImmutable, period_end: CarbonImmutable, employees: Collection}> */
    public function upcoming(CarbonImmutable $from, int $count = 6): array
    {
        $startIndex = $this->shiftIndexForDate($from);

        return collect(range($startIndex, $startIndex + $count - 1))
            ->map(fn (int $shiftIndex) => $this->teamForDate($this->periodForShiftIndex($shiftIndex)[0]))
            ->all();
    }
}
