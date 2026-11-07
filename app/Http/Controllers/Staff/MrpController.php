<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Expense;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $stats = [
            'total_ingredients' => $components->count(),
            'low_stock' => $components->filter->isLowStock()->count(),
            'total_units' => $components->sum('stock_quantity'),
            'stock_value' => $components->sum(fn (Component $component) => $component->stock_quantity * $component->price),
        ];

        return view('staff.mrp.index', [
            'components' => $components,
            'sparklines' => $sparklines,
            'stats' => $stats,
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
