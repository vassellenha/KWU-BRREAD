<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BulkMrpRequest;
use App\Models\Component;
use App\Models\Expense;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MrpController extends Controller
{
    private const SPARKLINE_POINTS = 8;

    public function index(): View
    {
        $components = Component::orderBy('category')->orderBy('name')->get();

        $sparklines = $components->mapWithKeys(fn (Component $component) => [
            $component->id => $component->stockMovements()
                ->latest('id')
                ->limit(self::SPARKLINE_POINTS)
                ->pluck('balance_after')
                ->reverse()
                ->values(),
        ]);

        return view('staff.mrp.index', [
            'components' => $components,
            'sparklines' => $sparklines,
            'stats' => $this->stats($components),
        ]);
    }

    /**
     * @param  Collection<int, Component>  $components
     * @return array{total_ingredients: int, low_stock: int, total_units: int, stock_value: float|int}
     */
    private function stats(Collection $components): array
    {
        return [
            'total_ingredients' => $components->count(),
            'low_stock' => $components->filter->isLowStock()->count(),
            'total_units' => $components->sum('stock_quantity'),
            'stock_value' => $components->sum(fn (Component $component) => $component->stock_quantity * $component->price),
        ];
    }

    /**
     * "Save all" on the stock page: every changed ingredient in one transaction. One bad row (more taken out
     * than is in stock, a row somebody else just changed) saves nothing, and the errors come back per row.
     */
    public function bulkAdjust(BulkMrpRequest $request): JsonResponse
    {
        $rows = $request->changedRows();
        $sharedNote = $request->validated('shared_note');
        $userId = $request->user()->id;

        $costLogged = 0;

        $changed = DB::transaction(function () use ($rows, $sharedNote, $userId, &$costLogged) {
            $components = Component::whereKey(array_keys($rows))->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($rows as $id => $row) {
                $component = $components->get($id);

                if (! $component) {
                    $errors["rows.{$id}.version"][] = 'This ingredient was removed by someone else. Reload to see the current list.';

                    continue;
                }

                if ($row['version'] !== $component->stockVersion()) {
                    $errors["rows.{$id}.version"][] = "Someone else changed the stock of {$component->name} while you were editing. Reload to see the current amount.";

                    continue;
                }

                if ($row['direction'] === 'out' && (int) ($row['cost'] ?? 0) > 0) {
                    $errors["rows.{$id}.cost"][] = 'A purchase cost only applies to stock in.';
                }

                if ($row['direction'] === 'out' && $row['quantity'] > $component->stock_quantity) {
                    $errors["rows.{$id}.quantity"][] = "Only {$component->stock_quantity} {$component->unit} of {$component->name} in stock, so that much cannot be removed.";
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($rows as $id => $row) {
                $component = $components->get($id);
                $isRestock = $row['direction'] === 'in';
                $cost = $isRestock ? (int) ($row['cost'] ?? 0) : 0;
                $note = $row['note'] ?? $sharedNote;

                $component->recordStockMovement(
                    type: $isRestock ? StockMovement::TYPE_RESTOCK : StockMovement::TYPE_ADJUSTMENT,
                    quantityChange: $isRestock ? $row['quantity'] : -$row['quantity'],
                    userId: $userId,
                    note: $note,
                );

                if ($cost > 0) {
                    Expense::create([
                        'description' => "Restock: {$component->name} (+{$row['quantity']} {$component->unit})",
                        'category' => 'Ingredients',
                        'amount' => $cost,
                        'expense_date' => today(),
                        'user_id' => $userId,
                        'note' => $note,
                    ]);
                    $costLogged++;
                }
            }

            return $components;
        });

        $everything = Component::orderBy('category')->orderBy('name')->get();
        $stats = $this->stats($everything);
        $count = $changed->count();

        return response()->json([
            'message' => $count.($count === 1 ? ' stock change' : ' stock changes').' saved'
                .($costLogged > 0 ? ", $costLogged purchase ".($costLogged === 1 ? 'cost' : 'costs').' logged in Accounting.' : '.'),
            'saved' => $count,
            'rows' => $changed->mapWithKeys(fn (Component $component) => [$component->id => [
                'version' => $component->stockVersion(),
                'fields' => ['direction' => 'in', 'quantity' => '', 'cost' => '', 'note' => ''],
                'cells' => ['stock' => "{$component->stock_quantity} {$component->unit}"],
                'flags' => ['low' => $component->isLowStock()],
            ]]),
            'stats' => [
                'total_ingredients' => (string) $stats['total_ingredients'],
                'low_stock' => (string) $stats['low_stock'],
                'total_units' => number_format($stats['total_units'], 0, ',', '.'),
                'stock_value' => 'Rp '.number_format($stats['stock_value'], 0, ',', '.'),
            ],
        ]);
    }

    public function adjustStock(Request $request, Component $component): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:9999999999', 'prohibited_if:direction,out'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $isRestock = $validated['direction'] === 'in';
        $cost = (int) ($validated['cost'] ?? 0);

        DB::transaction(function () use ($request, $component, $validated, $isRestock, $cost) {
            $lockedComponent = Component::whereKey($component->id)->lockForUpdate()->firstOrFail();

            if (! $isRestock && $validated['quantity'] > $lockedComponent->stock_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$lockedComponent->stock_quantity} {$lockedComponent->unit} of {$lockedComponent->name} in stock, so that much cannot be removed.",
                ]);
            }

            // "Stock in" is a restock; "stock out" is a manual correction/waste write-off.
            $lockedComponent->recordStockMovement(
                type: $isRestock ? StockMovement::TYPE_RESTOCK : StockMovement::TYPE_ADJUSTMENT,
                quantityChange: $isRestock ? $validated['quantity'] : -$validated['quantity'],
                userId: $request->user()->id,
                note: $validated['note'] ?? null,
            );

            if ($cost > 0) {
                Expense::create([
                    'description' => "Restock: {$lockedComponent->name} (+{$validated['quantity']} {$lockedComponent->unit})",
                    'category' => 'Ingredients',
                    'amount' => $cost,
                    'expense_date' => today(),
                    'user_id' => $request->user()->id,
                    'note' => $validated['note'] ?? null,
                ]);
            }
        });

        return back()->with('status', $cost > 0
            ? "Stock for {$component->name} updated and the cost logged in Accounting."
            : "Stock for {$component->name} updated.");
    }

    public function movements(Request $request): View
    {
        $filters = $request->validate([
            'component_id' => ['nullable', 'integer', 'exists:components,id'],
            'type' => ['nullable', 'in:restock,consumption,adjustment'],
        ]);

        $movements = StockMovement::with(['component', 'order', 'user'])
            ->when($filters['component_id'] ?? null, fn ($q, $id) => $q->where('component_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('staff.mrp.movements', [
            'movements' => $movements,
            'components' => Component::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
