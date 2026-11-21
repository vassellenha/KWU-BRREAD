<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BulkHrRequest;
use App\Models\Employee;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Services\ShiftScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    /**
     * "Save all" on the HR page: details, rotation order and active state of every changed employee in one
     * transaction. One bad row saves nothing, and the errors come back per row.
     */
    public function bulkUpdate(BulkHrRequest $request): JsonResponse
    {
        $rows = $request->changedRows();

        $changed = DB::transaction(function () use ($rows) {
            $employees = Employee::whereKey(array_keys($rows))->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($rows as $id => $row) {
                $employee = $employees->get($id);

                if (! $employee) {
                    $errors["rows.{$id}.version"][] = 'This employee was removed by someone else. Reload to see the current team.';
                } elseif ($row['version'] !== $employee->rowVersion()) {
                    $errors["rows.{$id}.version"][] = "Someone else changed {$employee->name} while you were editing. Reload to see their version.";
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($rows as $id => $row) {
                $employees->get($id)->update([
                    'name' => $row['name'],
                    'position' => $row['position'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'rotation_order' => $row['rotation_order'],
                    'is_active' => (bool) $row['is_active'],
                ]);
            }

            return $employees;
        });

        $count = $changed->count();

        return response()->json([
            'message' => $count.($count === 1 ? ' change' : ' changes').' saved.',
            'saved' => $count,
            'rows' => $changed->mapWithKeys(fn (Employee $employee) => [$employee->id => [
                'version' => $employee->rowVersion(),
                'fields' => [
                    'name' => $employee->name,
                    'position' => (string) $employee->position,
                    'phone' => (string) $employee->phone,
                    'rotation_order' => (string) $employee->rotation_order,
                    'is_active' => $employee->is_active ? '1' : '0',
                ],
                'cells' => [],
                'flags' => ['active' => $employee->is_active],
            ]]),
            'stats' => [],
        ]);
    }

    public function toggleActive(Employee $employee): RedirectResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        $status = $employee->is_active ? 'reactivated' : 'deactivated';

        return back()->with('status', "{$employee->name} {$status}.");
    }
}
