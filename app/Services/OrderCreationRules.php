<?php

namespace App\Services;

use App\Models\Component;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What a valid sandwich is, shared by checkout and by the staff's order edit so both say the same thing.
 */
final class OrderCreationRules
{
    /**
     * A sandwich is exactly one bread and one ice cream slab, each topping at most once.
     *
     * @param  list<int>  $componentIds
     * @param  Collection<int, Component>  $components
     */
    public static function ensureComplete(array $componentIds, Collection $components, int $creationNumber): void
    {
        $categories = collect($componentIds)->map(fn (int $id) => $components->get($id)?->category);

        $message = match (true) {
            $categories->contains(null) => "Creation #{$creationNumber} uses an ingredient that is no longer available.",
            count(array_unique($componentIds)) !== count($componentIds) => "Creation #{$creationNumber} lists the same ingredient twice.",
            $categories->filter(fn (string $category) => $category === 'bread')->count() !== 1 => "Creation #{$creationNumber} needs exactly one bread base.",
            $categories->filter(fn (string $category) => $category === 'ice_cream')->count() !== 1 => "Creation #{$creationNumber} needs exactly one ice cream flavor.",
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['items' => $message]);
        }
    }

    public static function ensureStockCovers(?Component $component, int $needed): void
    {
        if ($component === null || $component->stock_quantity >= $needed) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => $component->stock_quantity > 0
                ? "Only {$component->stock_quantity} {$component->name} left, but this order needs {$needed}. Lower the quantity and try again."
                : "{$component->name} just sold out. Remove it from your cart and try again.",
        ]);
    }
}
