<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BulkMenuRequest;
use App\Models\Component;
use App\Models\SignatureCombo;
use App\Models\StockMovement;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public const MAX_PRICE = 999999;

    public const NAME_PATTERN = "regex:/^[\\p{L}\\p{N}][\\p{L}\\p{N} &'().,+-]*$/u";

    public function index(): View
    {
        $all = Component::withCount('orderItems')->orderBy('name')->get();
        $byCategory = $all->groupBy('category');
        $combos = SignatureCombo::with(['bread', 'flavor', 'toppings', 'photo:id,signature_combo_id,updated_at'])->orderBy('sort_order')->orderBy('id')->get();

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

    /**
     * "Save all" on the menu page: name, price, color and availability of every changed item in one transaction.
     * One bad row saves nothing, and the errors come back per row.
     */
    public function bulkUpdate(BulkMenuRequest $request): JsonResponse
    {
        $rows = $request->changedRows();
        $priceChanged = false;

        $changed = DB::transaction(function () use ($rows, &$priceChanged) {
            $components = Component::whereKey(array_keys($rows))->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($rows as $id => $row) {
                $component = $components->get($id);

                if (! $component) {
                    $errors["rows.{$id}.version"][] = 'This item was removed by someone else. Reload to see the current menu.';
                } elseif ($row['version'] !== $component->menuVersion()) {
                    $errors["rows.{$id}.version"][] = "Someone else changed {$component->name} while you were editing. Reload to see their version.";
                } elseif ($component->category === 'ice_cream' && empty($row['hex_color'])) {
                    $errors["rows.{$id}.hex_color"][] = 'Pick a flavor color.';
                }
            }

            // The names an item would have once every row is saved, so swapping two names works but a clash does not.
            $categories = $components->pluck('category')->unique()->all();
            $final = Component::whereIn('category', $categories)->get(['id', 'name', 'category'])
                ->mapWithKeys(fn (Component $component) => [$component->id => [
                    'category' => $component->category,
                    'name' => mb_strtolower(isset($rows[$component->id]) ? (string) $rows[$component->id]['name'] : $component->name),
                ]]);

            foreach ($final->groupBy(fn (array $item) => $item['category'].'|'.$item['name'], preserveKeys: true) as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                foreach ($group->keys() as $id) {
                    if (isset($rows[$id]) && ! isset($errors["rows.{$id}.version"])) {
                        $label = self::CATEGORIES[$final[$id]['category']] ?? 'menu';
                        $errors["rows.{$id}.name"][] = "The {$label} menu already has an item with that name.";
                    }
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($rows as $id => $row) {
                $component = $components->get($id);
                $priceChanged = $priceChanged || (int) $component->price !== (int) $row['price'];

                $component->update([
                    'name' => $row['name'],
                    'price' => $row['price'],
                    'hex_color' => $this->colorFor($component->category, $row['hex_color'] ?? null),
                    'is_active' => (bool) $row['is_active'],
                ]);
            }

            return $components;
        });

        $count = $changed->count();
        $all = Component::get(['id', 'is_active']);

        return response()->json([
            'message' => $count.($count === 1 ? ' change' : ' changes').' saved'.($priceChanged ? '. Orders already placed keep their old price.' : '.'),
            'saved' => $count,
            'rows' => $changed->mapWithKeys(fn (Component $component) => [$component->id => [
                'version' => $component->menuVersion(),
                'fields' => [
                    'name' => $component->name,
                    'price' => (string) (int) $component->price,
                    'hex_color' => $component->hex_color ?: '#F8B8C4',
                    'is_active' => $component->is_active ? '1' : '0',
                ],
                'cells' => [],
                'flags' => ['active' => $component->is_active],
            ]]),
            'stats' => [
                'total' => (string) $all->count(),
                'active' => (string) $all->where('is_active', true)->count(),
                'hidden' => (string) $all->where('is_active', false)->count(),
            ],
        ]);
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
            self::NAME_PATTERN,
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
