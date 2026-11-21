<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Expense;
use App\Models\StockMovement;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\ExpenseSeeder;
use Database\Seeders\HppSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppSimulationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function price(string $name): int
    {
        return (int) Component::where('name', $name)->value('price');
    }

    public function test_prices_add_up_to_the_sheets_selling_prices(): void
    {
        $this->seed(ComponentSeeder::class);
        $this->seed(HppSimulationSeeder::class);

        $toppings = $this->price('Marshmallow Mini') + $this->price('Yupi Gummy Bear Mini');

        foreach (['Es Teler', 'Chocolate', 'Neapolitan'] as $flavor) {
            $this->assertSame(10000, $this->price('Plain White Bread') + $this->price($flavor));
            $this->assertSame(15000, $this->price('Plain White Bread') + $this->price($flavor) + $toppings);
        }
        foreach (['Matcha', 'Durian Musang King'] as $flavor) {
            $this->assertSame(12000, $this->price('Plain White Bread') + $this->price($flavor));
            $this->assertSame(17000, $this->price('Plain White Bread') + $this->price($flavor) + $toppings);
        }
    }

    public function test_the_first_purchase_lands_in_mrp_and_accounting(): void
    {
        $this->seed(ComponentSeeder::class);
        $this->seed(ExpenseSeeder::class);
        $this->seed(HppSimulationSeeder::class);

        $this->assertSame(5, Component::where('name', 'Matcha')->value('stock_quantity'));
        $this->assertSame(25, Component::where('name', 'Marshmallow Mini')->value('stock_quantity'));
        $this->assertSame(1, StockMovement::where('component_id', Component::where('name', 'Matcha')->value('id'))->count());

        // 317.050 of ingredients and packaging, plus the 120.000 cooler box, and none of the placeholder expenses.
        $this->assertSame(317050, (int) Expense::where('category', 'Ingredients')->sum('amount'));
        $this->assertSame(437050, (int) Expense::sum('amount'));
        $this->assertFalse(Expense::where('description', 'Store rent — monthly')->exists());
    }

    public function test_it_runs_once_and_leaves_later_edits_alone(): void
    {
        $this->seed(ComponentSeeder::class);
        $this->seed(HppSimulationSeeder::class);

        Component::where('name', 'Matcha')->update(['price' => 9500, 'stock_quantity' => 2]);
        $this->seed(HppSimulationSeeder::class);

        $this->assertSame(9500, $this->price('Matcha'));
        $this->assertSame(2, Component::where('name', 'Matcha')->value('stock_quantity'));
        $this->assertSame(10, Expense::count());
    }
}
