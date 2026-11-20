<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SignatureCombo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The one-click signature combos of the builder. They live on the Menu page because they are made of menu items.
 */
class SignatureComboController extends Controller
{
    private const MAX_COMBOS = 24;

    private const MAX_TOPPINGS = 6;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        if (SignatureCombo::count() >= self::MAX_COMBOS) {
            return back()->withInput()->withErrors(['combo' => 'The builder fits '.self::MAX_COMBOS.' combos. Delete one before adding another.']);
        }

        DB::transaction(function () use ($validated) {
            $combo = SignatureCombo::create([
                'name' => $validated['name'],
                'bread_id' => $validated['bread_id'],
                'flavor_id' => $validated['flavor_id'],
                'is_active' => true,
                'sort_order' => (int) SignatureCombo::max('sort_order') + 1,
            ]);

            $combo->toppings()->sync($validated['toppings'] ?? []);
        });

        return back()->with('status', "{$validated['name']} added to the signature combos.");
    }

    public function update(Request $request, SignatureCombo $combo): RedirectResponse
    {
        $validated = $request->validate($this->rules($combo));

        DB::transaction(function () use ($combo, $validated) {
            $combo->update([
                'name' => $validated['name'],
                'bread_id' => $validated['bread_id'],
                'flavor_id' => $validated['flavor_id'],
            ]);

            $combo->toppings()->sync($validated['toppings'] ?? []);
        });

        return back()->with('status', "{$combo->name} saved.");
    }

    public function toggleActive(SignatureCombo $combo): RedirectResponse
    {
        $combo->update(['is_active' => ! $combo->is_active]);

        return back()->with('status', $combo->is_active
            ? "{$combo->name} is back in the builder."
            : "{$combo->name} is hidden from the builder.");
    }

    /**
     * Swap places with the neighbour in the given direction, which is the order the builder shows them in.
     */
    public function move(Request $request, SignatureCombo $combo): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($combo, $direction) {
            $ordered = SignatureCombo::orderBy('sort_order')->orderBy('id')->get()->values();
            $index = $ordered->search(fn (SignatureCombo $item) => $item->is($combo));
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($ordered[$swapWith])) {
                return;
            }

            $moved = $ordered->splice($index, 1)->first();
            $ordered->splice($swapWith, 0, [$moved]);

            // Rewrite the whole sequence so ties from older rows can never leave the order ambiguous.
            $ordered->each(fn (SignatureCombo $item, int $position) => $item->update(['sort_order' => $position + 1]));
        });

        return back();
    }

    public function destroy(SignatureCombo $combo): RedirectResponse
    {
        $combo->delete();

        return back()->with('status', "{$combo->name} deleted from the signature combos.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?SignatureCombo $ignore = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:40',
                "regex:/^[\\p{L}\\p{N}][\\p{L}\\p{N} &'().,+-]*$/u",
                function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
                    $taken = SignatureCombo::whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                        ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                        ->exists();

                    if ($taken) {
                        $fail('A signature combo with that name already exists.');
                    }
                },
            ],
            'bread_id' => ['required', 'integer', Rule::exists('components', 'id')->where('category', 'bread')],
            'flavor_id' => ['required', 'integer', Rule::exists('components', 'id')->where('category', 'ice_cream')],
            'toppings' => ['nullable', 'array', 'max:'.self::MAX_TOPPINGS],
            'toppings.*' => ['integer', 'distinct', Rule::exists('components', 'id')->where('category', 'topping')],
        ];
    }
}
