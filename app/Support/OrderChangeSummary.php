<?php

namespace App\Support;

/**
 * Turns two snapshots of an order's creations (see OrderEditor::snapshot()) into short Indonesian lines, so the
 * admin's preview, the history, the WhatsApp message and the customer's tracking page all say the same thing.
 */
final class OrderChangeSummary
{
    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return list<string>
     */
    public static function lines(array $before, array $after): array
    {
        $lines = [];
        $oldByIndex = collect($before)->keyBy('creation');
        $label = count($after) > 1;

        foreach ($after as $new) {
            $old = $oldByIndex->get($new['creation']);
            if ($old === null) {
                continue;
            }

            $prefix = $label ? "Kreasi #{$new['creation']}: " : '';

            if (($old['bread'] ?? null) !== ($new['bread'] ?? null)) {
                $lines[] = "{$prefix}Roti {$old['bread']} diganti {$new['bread']}";
            }
            if (($old['flavor'] ?? null) !== ($new['flavor'] ?? null)) {
                $lines[] = "{$prefix}Rasa {$old['flavor']} diganti {$new['flavor']}";
            }

            $oldToppings = $old['toppings'] ?? [];
            $newToppings = $new['toppings'] ?? [];
            foreach (array_values(array_diff($newToppings, $oldToppings)) as $name) {
                $lines[] = "{$prefix}Topping {$name} ditambah";
            }
            foreach (array_values(array_diff($oldToppings, $newToppings)) as $name) {
                $lines[] = "{$prefix}Topping {$name} dihapus";
            }

            if ((int) ($old['quantity'] ?? 1) !== (int) ($new['quantity'] ?? 1)) {
                $lines[] = "{$prefix}Jumlah {$old['quantity']} menjadi {$new['quantity']}";
            }
        }

        return $lines;
    }

    /** One creation as a single line: "2x Plain White Bread, Matcha + Marshmallow Mini". */
    public static function describe(array $creation): string
    {
        $line = "{$creation['quantity']}x {$creation['bread']}, {$creation['flavor']}";
        $toppings = $creation['toppings'] ?? [];

        return $toppings === [] ? $line : $line.' + '.implode(', ', $toppings);
    }
}
