<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Services\ShiftScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(ShiftScheduler $scheduler): View
    {
        $employees = Employee::orderBy('rotation_order')->orderBy('id')->get();

        $today = CarbonImmutable::today();
        $currentShift = $scheduler->teamForDate($today);
        $upcoming = $scheduler->upcoming($today, 6);

        $stats = [
            'total_employees' => $employees->count(),
            'active_employees' => $employees->where('is_active', true)->count(),
            'on_duty' => $currentShift['employees']->count(),
            'next_rotation' => $upcoming[1]['period_start'],
        ];

        return view('staff.hr.index', [
            'employees' => $employees,
            'currentShift' => $currentShift,
            'upcoming' => $upcoming,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', new PhoneNumber],
        ]);

        $nextOrder = ((int) Employee::max('rotation_order')) + 1;

        Employee::create($validated + ['rotation_order' => $nextOrder]);

        return back()->with('status', "{$validated['name']} added to the team.");
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', new PhoneNumber],
            'rotation_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $employee->update($validated);

        return back()->with('status', "{$employee->name} updated.");
    }

    public function toggleActive(Employee $employee): RedirectResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        $status = $employee->is_active ? 'reactivated' : 'deactivated';

        return back()->with('status', "{$employee->name} {$status}.");
    }
}
