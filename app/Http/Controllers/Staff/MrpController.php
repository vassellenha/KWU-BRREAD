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

        return view('staff.mrp.index', [
            'components' => $components,
            'lowStockCount' => $components->filter->isLowStock()->count(),
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

        $component->recordStockMovement(
            type: StockMovement::TYPE_ADJUSTMENT,
            quantityChange: $change,
            userId: $request->user()->id,
            note: $validated['note'] ?? null,
        );

        return back()->with('status', "Stock for {$component->name} updated.");
    }

    public function movements(): View
    {
        $movements = StockMovement::with(['component', 'order', 'user'])
            ->latest()
            ->paginate(30);

        return view('staff.mrp.movements', [
            'movements' => $movements,
        ]);
    }
}
