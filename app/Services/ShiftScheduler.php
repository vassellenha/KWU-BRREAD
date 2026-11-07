<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fixed, deterministic duty rotation: GROUP_SIZE active employees (in rotation_order)
 * cover each SHIFT_LENGTH_DAYS-day block, and the next block starts where the last one
 * stopped, wrapping around the list. Six people split into two alternating teams; any
 * other headcount still fields a full team and shares the shifts evenly. Nothing about a
 * shift is stored, so adding, removing, or reordering staff reshapes it immediately.
 */
class ShiftScheduler
{
    public const GROUP_SIZE = 3;

    public const SHIFT_LENGTH_DAYS = 3;

    public const ROTATION_ANCHOR = '2026-11-02';

    /** @return Collection<int, Employee> */
    public function activeEmployees(): Collection
    {
        return Employee::where('is_active', true)
            ->orderBy('rotation_order')
            ->orderBy('id')
            ->get();
    }

    public function shiftIndexForDate(CarbonImmutable $date): int
    {
        $anchor = CarbonImmutable::parse(self::ROTATION_ANCHOR)->startOfDay();
        $daysSinceAnchor = $anchor->diffInDays($date->startOfDay(), false);

        return (int) floor($daysSinceAnchor / self::SHIFT_LENGTH_DAYS);
    }

    /**
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, employees: Collection<int, Employee>}
     */
    public function teamForDate(CarbonImmutable $date): array
    {
        return $this->shift($this->shiftIndexForDate($date), $this->activeEmployees());
    }

    /**
     * @return list<array{period_start: CarbonImmutable, period_end: CarbonImmutable, employees: Collection<int, Employee>}>
     */
    public function upcoming(CarbonImmutable $from, int $count = 6): array
    {
        $employees = $this->activeEmployees();
        $firstShift = $this->shiftIndexForDate($from);

        return collect(range($firstShift, $firstShift + $count - 1))
            ->map(fn (int $shiftIndex) => $this->shift($shiftIndex, $employees))
            ->all();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, employees: Collection<int, Employee>}
     */
    private function shift(int $shiftIndex, Collection $employees): array
    {
        $start = CarbonImmutable::parse(self::ROTATION_ANCHOR)
            ->startOfDay()
            ->addDays($shiftIndex * self::SHIFT_LENGTH_DAYS);

        return [
            'period_start' => $start,
            'period_end' => $start->addDays(self::SHIFT_LENGTH_DAYS - 1),
            'employees' => $this->teamForShift($shiftIndex, $employees),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, Employee>
     */
    private function teamForShift(int $shiftIndex, Collection $employees): Collection
    {
        $headcount = $employees->count();

        if ($headcount <= self::GROUP_SIZE) {
            return $employees;
        }

        $firstSeat = (($shiftIndex * self::GROUP_SIZE) % $headcount + $headcount) % $headcount;

        return collect(range(0, self::GROUP_SIZE - 1))
            ->map(fn (int $offset) => $employees[($firstSeat + $offset) % $headcount]);
    }
}
