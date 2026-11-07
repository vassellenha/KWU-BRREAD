<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MrpAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Component $component;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->staff = User::factory()->withRole(User::ROLE_WAREHOUSE)->create();
        $this->component = Component::where('name', 'Vanilla')->firstOrFail();
    }

    public function test_a_restock_raises_stock_and_is_logged_as_a_restock(): void
    {
        $before = $this->component->stock_quantity;

        $this->actingAs($this->staff)
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'in', 'quantity' => 10])
            ->assertSessionHasNoErrors();

        $this->assertSame($before + 10, $this->component->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_RESTOCK, 'quantity_change' => 10, 'user_id' => $this->staff->id]);
        $this->assertSame(0, Expense::count());
    }

    public function test_a_restock_with_a_cost_lands_in_accounting(): void
    {
        $this->actingAs($this->staff)
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'in', 'quantity' => 10, 'cost' => 250000])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Restock: Vanilla (+10 pcs)',
            'category' => 'Ingredients',
            'amount' => 250000,
            'user_id' => $this->staff->id,
        ]);

        $this->actingAs(User::factory()->withRole(User::ROLE_ACCOUNTANT)->create())
            ->get('/staff/accounting')
            ->assertSee('Restock: Vanilla (+10 pcs)')
            ->assertSee('-Rp 250.000');
    }

    public function test_stock_out_cannot_remove_more_than_is_in_stock(): void
    {
        $before = $this->component->stock_quantity;

        $this->actingAs($this->staff)
            ->from('/staff/mrp')
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'out', 'quantity' => $before + 1, '_row' => $this->component->id])
            ->assertRedirect('/staff/mrp')
            ->assertSessionHasErrors('quantity');

        $this->assertSame($before, $this->component->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_failed_adjustment_reopens_its_row_with_the_error_shown(): void
    {
        $this->actingAs($this->staff)
            ->from('/staff/mrp')
            ->followingRedirects()
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'out', 'quantity' => 99999, '_row' => $this->component->id])
            ->assertOk()
            ->assertSee('so that much cannot be removed')
            ->assertSee("openId: {$this->component->id}", false)
            ->assertSee('value="99999"', false);
    }

    public function test_stock_out_cannot_carry_a_purchase_cost(): void
    {
        $this->actingAs($this->staff)
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'out', 'quantity' => 1, 'cost' => 5000])
            ->assertSessionHasErrors('cost');

        $this->assertSame(0, Expense::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'letters' => ['abc'],
            'scientific notation' => ['1e5'],
            'negative' => ['-5'],
            'decimal' => ['1.5'],
            'zero' => ['0'],
            'too large' => ['100001'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_quantity_must_be_a_positive_whole_number(string $quantity): void
    {
        $before = $this->component->stock_quantity;

        $this->actingAs($this->staff)
            ->post("/staff/mrp/{$this->component->id}/adjust", ['direction' => 'in', 'quantity' => $quantity])
            ->assertSessionHasErrors('quantity');

        $this->assertSame($before, $this->component->fresh()->stock_quantity);
    }

    public function test_the_sparkline_shows_the_latest_points_in_order(): void
    {
        foreach (range(1, 10) as $unused) {
            $this->component->recordStockMovement(StockMovement::TYPE_RESTOCK, 1);
        }

        $response = $this->actingAs($this->staff)->get('/staff/mrp')->assertOk();

        $series = $response->viewData('sparklines')->get($this->component->id);

        $this->assertCount(8, $series);
        $this->assertSame($this->component->fresh()->stock_quantity, $series->last());
        $this->assertSame($series->sort()->values()->all(), $series->all());
    }
}
