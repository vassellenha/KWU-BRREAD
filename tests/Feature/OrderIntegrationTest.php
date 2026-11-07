<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function ingredient(string $name): Component
    {
        return Component::where('name', $name)->firstOrFail();
    }

    /**
     * @param  list<array{component_ids: list<int>, quantity: int}>  $items
     */
    private function placeOrder(array $items, string $name = 'Budi Santoso', string $phone = '081234567890'): TestResponse
    {
        return $this->postJson('/api/orders', [
            'customer_name' => $name,
            'customer_phone' => $phone,
            'items' => $items,
        ]);
    }

    public function test_a_customer_order_flows_into_mrp_and_accounting(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Strawberry');
        $breadStock = $bread->stock_quantity;
        $flavorStock = $flavor->stock_quantity;

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 3]])
            ->assertCreated()
            ->assertJsonPath('total_price', '42000.00');

        $this->assertSame($breadStock - 3, $bread->fresh()->stock_quantity);
        $this->assertSame($flavorStock - 3, $flavor->fresh()->stock_quantity);

        $order = Order::firstOrFail();

        $this->assertDatabaseHas('stock_movements', [
            'component_id' => $bread->id,
            'order_id' => $order->id,
            'type' => StockMovement::TYPE_CONSUMPTION,
            'quantity_change' => -3,
            'balance_after' => $breadStock - 3,
        ]);

        $this->actingAs(User::factory()->withRole(User::ROLE_WAREHOUSE)->create())
            ->get('/staff/mrp/movements')
            ->assertSee($order->order_number);

        $this->actingAs(User::factory()->withRole(User::ROLE_ACCOUNTANT)->create())
            ->get('/staff/accounting')
            ->assertSee($order->order_number)
            ->assertSee('+Rp 42.000');
    }

    public function test_several_creations_share_one_order_and_add_up_per_ingredient(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $vanilla = $this->ingredient('Vanilla');
        $chocolate = $this->ingredient('Chocolate');
        $sauce = $this->ingredient('Chocolate Sauce');
        $breadStock = $bread->stock_quantity;

        $this->placeOrder([
            ['component_ids' => [$bread->id, $vanilla->id], 'quantity' => 2],
            ['component_ids' => [$bread->id, $chocolate->id, $sauce->id], 'quantity' => 1],
        ])->assertCreated()->assertJsonPath('total_price', '45000.00');

        $this->assertSame($breadStock - 3, $bread->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
        $this->assertSame(5, Order::first()->orderItems()->count());
    }

    public function test_an_order_needing_more_than_the_stock_is_rejected_with_nothing_written(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = tap($this->ingredient('Vanilla'))->update(['stock_quantity' => 2]);

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 3]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items')
            ->assertJsonFragment(['items' => ['Only 2 Vanilla left, but this order needs 3. Lower the quantity and try again.']]);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(2, $flavor->fresh()->stock_quantity);
    }

    public function test_a_sold_out_ingredient_says_so(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = tap($this->ingredient('Vanilla'))->update(['stock_quantity' => 0]);

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonFragment(['items' => ['Vanilla just sold out. Remove it from your cart and try again.']]);
    }

    public function test_a_deactivated_ingredient_cannot_be_ordered(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = tap($this->ingredient('Vanilla'))->update(['is_active' => false]);

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function incompleteCreations(): array
    {
        return [
            'no bread' => [['Vanilla', 'Chocolate Sauce']],
            'no ice cream' => [['Plain White Bread', 'Chocolate Sauce']],
            'two breads' => [['Plain White Bread', 'Whole Wheat Bread', 'Vanilla']],
            'two flavors' => [['Plain White Bread', 'Vanilla', 'Chocolate']],
            'same topping twice' => [['Plain White Bread', 'Vanilla', 'Chocolate Sauce', 'Chocolate Sauce']],
        ];
    }

    /**
     * @param  list<string>  $ingredients
     */
    #[DataProvider('incompleteCreations')]
    public function test_a_creation_must_be_one_bread_one_flavor_and_distinct_toppings(array $ingredients): void
    {
        $ids = array_map(fn (string $name) => $this->ingredient($name)->id, $ingredients);

        $this->placeOrder([['component_ids' => $ids, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, Order::count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidContacts(): array
    {
        return [
            'digits in name' => ['12345', '081234567890', 'customer_name'],
            'one-letter name' => ['A', '081234567890', 'customer_name'],
            'letters in phone' => ['Budi', 'not-a-phone', 'customer_phone'],
            'too short phone' => ['Budi', '0812', 'customer_phone'],
            'too long phone' => ['Budi', '0812345678901234567', 'customer_phone'],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_contact_details_are_validated(string $name, string $phone, string $invalidField): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Vanilla');

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]], $name, $phone)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($invalidField);
    }

    public function test_real_world_names_and_phone_formats_are_accepted(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Vanilla');

        $this->placeOrder([['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]], "O'Brien-Santoso", '+62 812-3456 7890')
            ->assertCreated();
    }

    public function test_the_storefront_catalog_reflects_mrp_stock_changes(): void
    {
        $flavor = $this->ingredient('Vanilla');
        $user = User::factory()->withRole(User::ROLE_WAREHOUSE)->create();

        $this->actingAs($user)
            ->post("/staff/mrp/{$flavor->id}/adjust", ['direction' => 'out', 'quantity' => $flavor->stock_quantity])
            ->assertSessionHasNoErrors();

        $this->getJson('/api/components')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Vanilla', 'stock_quantity' => 0]);
    }
}
