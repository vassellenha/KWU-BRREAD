<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    private const EARLIEST_DATE = '2020-01-01';

    private const DEFAULT_RANGE_DAYS = 30;

    public function index(Request $request, SalesReport $salesReport): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view('staff.sales.index', [
            'from' => $from,
            'to' => $to,
            'report' => $salesReport->build($from, $to),
        ]);
    }

    /**
     * Dates are held to a sensible window because every day in range becomes part of the chart.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $today = CarbonImmutable::today();
        $earliest = CarbonImmutable::parse(self::EARLIEST_DATE);
        $withinWindow = fn (CarbonImmutable $date): CarbonImmutable => $date->max($earliest)->min($today);

        $to = $withinWindow(isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : $today);
        $from = $withinWindow(isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->subDays(self::DEFAULT_RANGE_DAYS - 1));

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
