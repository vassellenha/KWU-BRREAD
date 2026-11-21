<?php

namespace App\Support;

use App\Models\Component;
use App\Models\SignatureCombo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the public pages say about the menu, read from the same tables the Menu and MRP modules manage, so a new
 * flavor, a hidden topping or an edited combo shows up on Home and About without anyone touching a view.
 */
class HomeMenu
{
    /** Real photos for the combos that have one, keyed by "flavor|topping+topping" in lower case. */
    private const PHOTOS = [
        'vanilla|' => '/img/marketing/product-vanilla.jpg',
        'strawberry|rainbow sprinkles' => '/img/marketing/product-strawberry.jpg',
        'chocolate|' => '/img/marketing/product-chocolate.jpg',
        'vanilla|chocolate sauce' => '/img/marketing/product-vanilla-wheat.jpg',
    ];

    /** Topping name => the key the 3D scene reads. */
    private const TOPPING_FLAGS = [
        'Chocolate Sauce' => 'sauce',
        'Rainbow Sprinkles' => 'sprinkles',
        'Marshmallow Mini' => 'marshmallows',
        'Yupi Gummy Bear Mini' => 'gummies',
    ];

    /**
     * @return array{
     *     breads: list<string>, flavors: list<string>, toppings: list<string>,
     *     counts: array{breads: int, flavors: int, toppings: int}, ways: int,
     *     filters: list<array{slug: string, label: string}>,
     *     combos: list<array<string, mixed>>, hero: list<array<string, mixed>>
     * }
     */
    public static function get(): array
    {
        try {
            return self::load();
        } catch (\Throwable) {
            // A menu that cannot be read leaves the generic wording in place instead of taking the page down.
            return ['breads' => [], 'flavors' => [], 'toppings' => [], 'counts' => ['breads' => 0, 'flavors' => 0, 'toppings' => 0], 'ways' => 0, 'filters' => [], 'combos' => [], 'hero' => []];
        }
    }

    private static function load(): array
    {
        $active = Component::where('is_active', true)->orderBy('price')->orderBy('id')->get()->groupBy('category');
        $breads = $active->get('bread', collect());
        $flavors = $active->get('ice_cream', collect());
        $toppings = $active->get('topping', collect());

        $signatures = SignatureCombo::with(['bread', 'flavor', 'toppings'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $combos = $signatures->map(fn (SignatureCombo $combo) => self::card($combo))->values()->all();

        return [
            'breads' => $breads->pluck('name')->all(),
            'flavors' => $flavors->pluck('name')->all(),
            'toppings' => $toppings->pluck('name')->all(),
            'counts' => ['breads' => $breads->count(), 'flavors' => $flavors->count(), 'toppings' => $toppings->count()],
            // Any bread with any flavor and any mix of the toppings (or none).
            'ways' => $breads->count() * $flavors->count() * (2 ** $toppings->count()),
            'filters' => self::filters($signatures),
            'combos' => $combos,
            'hero' => self::hero($flavors, $breads, $signatures),
        ];
    }

    /**
     * Just the names on the menu, for the footer that every page carries.
     *
     * @return array{breads: list<string>, flavors: list<string>, toppings: list<string>}
     */
    public static function names(): array
    {
        // The footer sits on every page, error pages included, so a database that is down must not take them with it.
        try {
            $active = Component::where('is_active', true)->orderBy('price')->orderBy('id')->get()->groupBy('category');
        } catch (\Throwable) {
            $active = collect();
        }

        return [
            'breads' => $active->get('bread', collect())->pluck('name')->all(),
            'flavors' => $active->get('ice_cream', collect())->pluck('name')->all(),
            'toppings' => $active->get('topping', collect())->pluck('name')->all(),
        ];
    }

    /** "A, B or C". */
    public static function join(array $names, string $word = 'or'): string
    {
        $names = array_values($names);

        return match (count($names)) {
            0 => '',
            1 => $names[0],
            default => implode(', ', array_slice($names, 0, -1)).' '.$word.' '.end($names),
        };
    }

    /** "Plain White Bread" reads as "white" in running text, "Whole Wheat Bread" as "whole wheat". */
    public static function breadLabel(string $name): string
    {
        $label = Str::of($name)->replaceMatches('/^plain\s+/i', '')->replaceMatches('/\s+bread$/i', '')->lower()->toString();

        return $label === 'wheat' ? 'whole wheat' : $label;
    }

    /** The chips above the combo cards: one per bread that at least one combo uses. */
    private static function filters(Collection $signatures): array
    {
        return $signatures
            ->pluck('bread')
            ->filter()
            ->unique('id')
            ->map(fn (Component $bread) => [
                'slug' => Str::slug($bread->name),
                'label' => Str::ucfirst(Str::of($bread->name)->replaceMatches('/^(plain|whole)\s+/i', '')->lower()->toString()),
            ])
            ->values()
            ->all();
    }

    private static function card(SignatureCombo $combo): array
    {
        $toppingNames = $combo->toppings->pluck('name')->all();
        $flavor = $combo->flavor;
        $photo = self::PHOTOS[Str::lower($flavor->name).'|'.Str::lower(implode('+', $toppingNames))] ?? null;
        $fallback = '/img/ingredients/icecream-'.Str::slug($flavor->name).'.webp';

        return [
            'id' => $combo->id,
            'tag' => implode(' + ', [$flavor->name, ...$toppingNames]),
            'category' => [Str::slug($combo->bread->name)],
            'name' => $combo->name,
            'desc' => ucfirst(self::breadLabel($combo->bread->name)).' bread with '.Str::lower($flavor->name).' ice cream'
                .($toppingNames ? ', topped with '.self::join(array_map(Str::lower(...), $toppingNames), 'and').'.' : '.'),
            'price' => $combo->price(),
            'image' => $photo ?? (file_exists(public_path($fallback)) ? $fallback : null),
            'photo' => $photo !== null,
            'color' => $flavor->hex_color,
        ];
    }

    /** One chip per flavor for the hero's 3D sandwich, dressed like the richest combo that uses it. */
    private static function hero(Collection $flavors, Collection $breads, Collection $signatures): array
    {
        return $flavors->map(function (Component $flavor) use ($breads, $signatures) {
            $combo = $signatures
                ->filter(fn (SignatureCombo $candidate) => $candidate->flavor_id === $flavor->id && $candidate->bread)
                ->sortByDesc(fn (SignatureCombo $candidate) => $candidate->toppings->count())
                ->first();

            $bread = $combo?->bread ?? $breads->first();
            $toppingNames = $combo?->toppings->pluck('name')->all() ?? [];
            $color = $flavor->hex_color ?: '#F3E9D2';

            $state = ['bread' => $bread?->name, 'flavor' => $flavor->name, 'flavorColor' => $color, 'exploded' => false];
            foreach (self::TOPPING_FLAGS as $name => $flag) {
                $state[$flag] = in_array($name, $toppingNames, true);
            }

            return [
                'key' => Str::slug($flavor->name),
                'label' => $flavor->name,
                'swatch' => $color,
                'summary' => implode(' · ', array_filter([
                    $bread ? ucfirst(self::breadLabel($bread->name)).' bread' : null,
                    Str::lower($flavor->name),
                    ...array_map(Str::lower(...), $toppingNames),
                ])),
                'state' => $state,
            ];
        })->values()->all();
    }
}
