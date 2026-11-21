<?php

namespace App\Support;

/**
 * Which menu items have a hand-drawn icon in resources/views/partials/ingredient-icons.blade.php.
 * Keep in sync with INGREDIENT_ICONS in resources/js/app.js; tests/Feature/IngredientIconTest.php checks both.
 */
class IngredientIcon
{
    /** Item name => symbol id. Toppings get an icon of their shape; the multi-part flavors get a drawn swatch. */
    public const SYMBOLS = [
        'Chocolate Sauce' => 'ing-chocolate-sauce',
        'Rainbow Sprinkles' => 'ing-rainbow-sprinkles',
        'Marshmallow Mini' => 'ing-marshmallow-mini',
        'Yupi Gummy Bear Mini' => 'ing-yupi-gummy-bear-mini',
        'Matcha' => 'ing-matcha',
        'Neapolitan' => 'ing-neapolitan',
        'Durian Musang King' => 'ing-durian-musang-king',
        'Es Teler' => 'ing-es-teler',
    ];

    public static function for(?string $name): ?string
    {
        return $name === null ? null : (self::SYMBOLS[$name] ?? null);
    }
}
