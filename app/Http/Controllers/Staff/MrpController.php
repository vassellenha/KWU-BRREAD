<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MrpController extends Controller
{
    public function index(): View
    {
        $components = Component::orderBy('category')->orderBy('name')->get();

        $sparklines = StockMovement::whereIn('component_id', $components->pluck('id'))
            ->orderBy('created_at')
            ->get(['component_id', 'balance_after'])
            ->groupBy('component_id')
            ->map(fn ($rows) => $rows->pluck('balance_after')->slice(-8)->values());

        $stats = [
            'total_ingredients' => $components->count(),
            'low_stock' => $components->filter->isLowStock()->count(),
            'total_units' => $components->sum('stock_quantity'),
            'stock_value' => $components->sum(fn ($c) => $c->stock_quantity * $c->price),
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
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $change = $validated['direction'] === 'in' ? $validated['quantity'] : -$validated['quantity'];

        // "Stock in" is a restock; "stock out" is a manual correction/waste write-off.
        $type = $validated['direction'] === 'in' ? StockMovement::TYPE_RESTOCK : StockMovement::TYPE_ADJUSTMENT;

        $component->recordStockMovement(
            type: $type,
            quantityChange: $change,
            userId: $request->user()->id,
            note: $validated['note'] ?? null,
        );

        return back()->with('status', "Stock for {$component->name} updated.");
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
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('staff.mrp.movements', [
            'movements' => $movements,
            'components' => Component::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
