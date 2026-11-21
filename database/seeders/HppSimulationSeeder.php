<?php

namespace Database\Seeders;

use App\Models\Component;
use App\Models\Expense;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts the numbers of the "Simulasi HPP Brread" sheet into the live data, once: the selling prices of the bread,
 * ice cream and toppings, the opening stock of the first purchase in MRP, and that purchase (plus the packaging
 * and the cooler box) as expenses in Accounting. Runs on every deploy but stops at the flag, so prices and stock
 * edited in Menu and MRP afterwards are never overwritten.
 *
 * Prices add up like the sheet's "Harga Jual": bread + ice cream = Rp 10.000 (Rp 12.000 for Matcha and Durian),
 * and with Marshmallow Mini and Yupi Gummy Bear Mini on top Rp 15.000 (Rp 17.000).
 */
class HppSimulationSeeder extends Seeder
{
    private const FLAG = 'hpp_simulation_applied';

    /** @var array<string, int> */
    private const PRICES = [
        'Plain White Bread' => 3000,
        'Whole Wheat Bread' => 4000,
        'Es Teler' => 7000,
        'Chocolate' => 7000,
        'Neapolitan' => 7000,
        'Matcha' => 9000,
        'Durian Musang King' => 9000,
        'Marshmallow Mini' => 2000,
        'Yupi Gummy Bear Mini' => 3000,
    ];

    /** Component => [quantity bought, what it cost, reorder point]. */
    private const PURCHASE = [
        'Es Teler' => [5, 28500, 2],
        'Chocolate' => [5, 28500, 2],
        'Neapolitan' => [5, 28500, 2],
        'Matcha' => [5, 39750, 2],
        'Durian Musang King' => [5, 39750, 2],
        'Plain White Bread' => [25, 28800, 8],
        'Marshmallow Mini' => [25, 44500, 8],
        'Yupi Gummy Bear Mini' => [25, 70000, 8],
    ];

    /** Seeded placeholder expenses the real purchases replace. */
    private const DEMO_EXPENSES = [
        'Weekly bread flour restock', 'Ice cream base supplier invoice', 'PLN electricity bill', 'Staff salaries — this period',
        'Cold-chain freezer maintenance', 'Toppings & sprinkles restock', 'Store rent — monthly', 'Packaging & insulated foil',
    ];

    public function run(): void
    {
        if (SiteSetting::allValues()[self::FLAG] ?? null) {
            return;
        }

        $components = Component::whereIn('name', array_keys(self::PRICES))->get()->keyBy('name');

        // A fresh install may not have the menu yet; the flag is only set once everything could be placed.
        if ($components->count() < count(self::PRICES)) {
            return;
        }

        DB::transaction(function () use ($components) {
            foreach (self::PRICES as $name => $price) {
                $components[$name]->update(['price' => $price]);
            }

            foreach (self::PURCHASE as $name => [$quantity, $cost, $reorder]) {
                $component = $components[$name];
                $component->update(['reorder_point' => $reorder]);

                $change = $quantity - $component->stock_quantity;

                if ($change !== 0) {
                    $component->recordStockMovement(
                        type: $change > 0 ? StockMovement::TYPE_RESTOCK : StockMovement::TYPE_ADJUSTMENT,
                        quantityChange: $change,
                        note: 'Opening stock from the Simulasi HPP purchase',
                    );
                }

                $this->expense("Restock: {$name} (+{$quantity} {$component->unit})", 'Ingredients', $cost);
            }

            $this->expense('Ice cream packaging, brown cardboard (25 pcs)', 'Ingredients', 8750);
            $this->expense('Lion Star cooler box 5.5 L', 'Equipment', 120000);

            Expense::whereIn('description', self::DEMO_EXPENSES)->delete();

            SiteSetting::put([self::FLAG => '1']);
        });
    }

    private function expense(string $description, string $category, int $amount): void
    {
        Expense::firstOrCreate(
            ['description' => $description],
            ['category' => $category, 'amount' => $amount, 'expense_date' => today(), 'note' => 'Simulasi HPP Brread'],
        );
    }
}
