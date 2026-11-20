<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\SignatureCombo;
use App\Models\StockMovement;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's menu: every bread, ice cream flavor and topping a customer can pick, with its price.
 * The builder and the checkout read the same `components` table, so a change here is live at once.
 */
class MenuController extends Controller
{
    public const CATEGORIES = [
        'bread' => 'Bread',
        'ice_cream' => 'Ice cream',
        'topping' => 'Topping',
    ];

    private const MAX_PRICE = 999999;

    public function index(): View
    {
        $all = Component::withCount('orderItems')->orderBy('name')->get();
        $byCategory = $all->groupBy('category');
        $combos = SignatureCombo::with(['bread', 'flavor', 'toppings'])->orderBy('sort_order')->orderBy('id')->get();

        return view('staff.menu.index', [
            'groups' => collect(self::CATEGORIES)->map(fn (string $label, string $category) => [
                'label' => $label,
                'items' => $byCategory->get($category, collect()),
            ]),
            'combos' => $combos,
            'comboChoices' => [
                'bread' => $byCategory->get('bread', collect())->where('is_active', true)->values(),
                'ice_cream' => $byCategory->get('ice_cream', collect())->where('is_active', true)->values(),
                'topping' => $byCategory->get('topping', collect())->where('is_active', true)->values(),
            ],
            'signature' => $combos->flatMap(fn (SignatureCombo $combo) => [
                $combo->bread->name,
                $combo->flavor->name,
                ...$combo->toppings->pluck('name'),
            ])->unique()->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('is_active', true)->count(),
                'hidden' => $all->where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = (string) $request->input('category');

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'name' => $this->nameRules($category),
            'price' => $this->priceRules(),
            'hex_color' => $this->colorRules($category),
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $component = Component::create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'price' => $validated['price'],
                'hex_color' => $this->colorFor($validated['category'], $validated['hex_color'] ?? null),
                'is_active' => true,
                'stock_quantity' => 0,
                'unit' => 'pcs',
                'reorder_point' => (int) ($validated['reorder_point'] ?? 0),
            ]);

            // Starting stock goes through the stock ledger like any other restock, so MRP shows where it came from.
            if ((int) ($validated['initial_stock'] ?? 0) > 0) {
                $component->recordStockMovement(
                    type: StockMovement::TYPE_RESTOCK,
                    quantityChange: (int) $validated['initial_stock'],
                    userId: $request->user()->id,
                    note: 'Starting stock when added to the menu',
                );
            }
        });

        return back()->with('status', "{$validated['name']} added to the menu.");
    }

    public function update(Request $request, Component $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => $this->nameRules($component->category, $component),
            'price' => $this->priceRules(),
            'hex_color' => $this->colorRules($component->category),
        ]);

        $oldPrice = (int) $component->price;

        $component->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'hex_color' => $this->colorFor($component->category, $validated['hex_color'] ?? null),
        ]);

        return back()->with('status', $this->updateMessage($component, $oldPrice));
    }

    /**
     * The quick path for the most common change on this page: just the price.
     */
    public function updatePrice(Request $request, Component $component): RedirectResponse
    {
        $validated = $request->validate(['price' => $this->priceRules()]);

        $oldPrice = (int) $component->price;
        $component->update(['price' => $validated['price']]);

        return back()->with('status', $this->updateMessage($component, $oldPrice));
    }

    public function toggleActive(Component $component): RedirectResponse
    {
        $component->update(['is_active' => ! $component->is_active]);

        return back()->with('status', $component->is_active
            ? "{$component->name} is back on the menu."
            : "{$component->name} is hidden from the builder. Past orders keep it.");
    }

    public function destroy(Component $component): RedirectResponse
    {
        // Deleting a component would also delete its order lines (the foreign key cascades) and rewrite
        // the sales history, so anything that was ever ordered can only be switched off.
        $ordered = $component->orderItems()->count();
        $inCombos = SignatureCombo::where('bread_id', $component->id)
            ->orWhere('flavor_id', $component->id)
            ->orWhereHas('toppings', fn ($query) => $query->whereKey($component->id))
            ->count();

        if ($inCombos > 0) {
            return back()->withErrors([
                'delete' => "{$component->name} is part of {$inCombos} signature ".($inCombos === 1 ? 'combo' : 'combos').'. Change or delete the combo first, or hide the item instead.',
            ]);
        }

        if ($ordered > 0) {
            return back()->withErrors([
                'delete' => "{$component->name} appears in {$ordered} order ".($ordered === 1 ? 'line' : 'lines').' and cannot be deleted. Hide it from the menu instead.',
            ]);
        }

        $component->delete();

        return back()->with('status', "{$component->name} deleted from the menu.");
    }

    /**
     * @return list<mixed>
     */
    private function nameRules(string $category, ?Component $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:60',
            "regex:/^[\\p{L}\\p{N}][\\p{L}\\p{N} &'().,+-]*$/u",
            function (string $attribute, mixed $value, Closure $fail) use ($category, $ignore) {
                $taken = Component::where('category', $category)
                    ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                    ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                    ->exists();

                if ($taken) {
                    $fail('The '.(self::CATEGORIES[$category] ?? 'menu').' menu already has an item with that name.');
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    private function priceRules(): array
    {
        return ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE];
    }

    /**
     * Only ice cream flavors carry a color (it tints the layer in the builder); it is required there.
     *
     * @return list<mixed>
     */
    private function colorRules(string $category): array
    {
        return [
            $category === 'ice_cream' ? 'required' : 'nullable',
            'regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    private function colorFor(string $category, ?string $color): ?string
    {
        return $category === 'ice_cream' && $color ? strtoupper($color) : null;
    }

    private function updateMessage(Component $component, int $oldPrice): string
    {
        $newPrice = (int) $component->price;

        if ($newPrice === $oldPrice) {
            return "{$component->name} saved.";
        }

        return "{$component->name} is now Rp ".number_format($newPrice, 0, ',', '.').' (was Rp '.number_format($oldPrice, 0, ',', '.').'). Orders already placed keep their old price.';
    }
}
