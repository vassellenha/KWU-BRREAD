<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matcha and the two mini toppings of the Studio (Marshmallow Mini, Yupi Gummy Bear Mini): seeded ready to
 * sell, orderable alone and together, drawn on the order status page, and marked with their own icons.
 */
class StudioMatchaToppingsTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_TOPPINGS = ['Marshmallow Mini', 'Yupi Gummy Bear Mini'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function newToppings(): array
    {
        return [
            'marshmallow mini' => ['Marshmallow Mini', 'top-marshmallow'],
            'yupi gummy bear mini' => ['Yupi Gummy Bear Mini', 'top-gummy-bears'],
        ];
    }

    private function ingredient(string $name): Component
    {
        return Component::where('name', $name)->firstOrFail();
    }

    /**
     * @param  list<int>  $componentIds
     */
    private function placeOrder(array $componentIds, int $quantity = 1): Order
    {
        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => $componentIds, 'quantity' => $quantity]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => Order::STATUS_CONFIRMED, 'confirmation_token' => Str::random(40), 'confirmed_at' => now()])->save();

        return $order;
    }

    public function test_the_seeder_puts_matcha_and_the_two_toppings_on_sale(): void
    {
        $matcha = $this->ingredient('Matcha');
        $this->assertSame('ice_cream', $matcha->category);
        $this->assertTrue($matcha->is_active);
        $this->assertSame('8000.00', $matcha->price);
        $this->assertSame(120, $matcha->stock_quantity);
        $this->assertSame(40, $matcha->reorder_point);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $matcha->hex_color);

        foreach (self::NEW_TOPPINGS as $name) {
            $topping = $this->ingredient($name);
            $this->assertSame('topping', $topping->category);
            $this->assertTrue($topping->is_active);
            $this->assertSame('3000.00', $topping->price);
            $this->assertSame(300, $topping->stock_quantity);
            $this->assertSame(60, $topping->reorder_point);
        }

        $catalog = $this->getJson('/api/components')->assertOk();
        $this->assertContains('Matcha', collect($catalog->json('ice_cream'))->pluck('name'));

        $toppings = collect($catalog->json('topping'));
        foreach (self::NEW_TOPPINGS as $name) {
            $listed = $toppings->firstWhere('name', $name);
            $this->assertNotNull($listed);
            $this->assertGreaterThan(0, $listed['stock_quantity']);
        }
    }

    public function test_seeding_again_keeps_what_the_admin_changed(): void
    {
        $this->ingredient('Marshmallow Mini')->update(['is_active' => false, 'price' => 4500]);

        $this->seed(ComponentSeeder::class);

        $marshmallow = $this->ingredient('Marshmallow Mini');
        $this->assertFalse($marshmallow->is_active);
        $this->assertSame('4500.00', $marshmallow->price);
        $this->assertSame(1, Component::where('name', 'Marshmallow Mini')->count());
    }

    public function test_a_matcha_sandwich_can_be_ordered_and_shows_its_own_art(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $matcha = $this->ingredient('Matcha');

        $order = $this->placeOrder([$bread->id, $matcha->id], 2);

        $this->assertSame('28000.00', $order->total_price);
        $this->assertSame(118, $matcha->fresh()->stock_quantity);

        $this->get("/order/status/{$order->confirmation_token}")
            ->assertOk()
            ->assertSee('Matcha')
            ->assertSee('/img/cutout/ice-matcha.webp', false)
            ->assertDontSee('/img/cutout/ice-vanilla.webp', false);
    }

    #[DataProvider('newToppings')]
    public function test_each_new_topping_can_be_ordered(string $name, string $art): void
    {
        $bread = $this->ingredient('Whole Wheat Bread');
        $flavor = $this->ingredient('Matcha');
        $topping = $this->ingredient($name);

        $order = $this->placeOrder([$bread->id, $flavor->id, $topping->id]);

        $this->assertSame('18000.00', $order->total_price);
        $this->assertSame(299, $topping->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'component_id' => $topping->id,
            'order_id' => $order->id,
            'type' => StockMovement::TYPE_CONSUMPTION,
            'quantity_change' => -1,
        ]);

        $this->get("/order/status/{$order->confirmation_token}")
            ->assertOk()
            ->assertSee($name)
            ->assertSee("/img/cutout/{$art}.webp", false);
    }

    public function test_every_topping_on_one_sandwich_is_charged_and_drawn(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Chocolate');
        $toppings = Component::where('category', 'topping')->orderBy('id')->get();
        $this->assertCount(4, $toppings);

        $order = $this->placeOrder([$bread->id, $flavor->id, ...$toppings->pluck('id')], 3);

        // (6.000 + 8.000 + 4 x 3.000) x 3
        $this->assertSame('78000.00', $order->total_price);
        foreach ($toppings as $topping) {
            $this->assertSame(297, $topping->fresh()->stock_quantity);
        }

        $page = $this->get("/order/status/{$order->confirmation_token}")->assertOk();
        foreach (['top-sauce', 'top-sprinkles', 'top-marshmallow', 'top-gummy-bears'] as $art) {
            $page->assertSee("/img/cutout/{$art}.webp", false);
        }
    }

    public function test_an_unknown_topping_is_rejected(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Matcha');

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id, 999999], 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.component_ids.2');

        $this->assertSame(0, Order::count());
    }

    public function test_a_hidden_topping_cannot_be_ordered(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Matcha');
        $gummies = $this->ingredient('Yupi Gummy Bear Mini');
        $gummies->update(['is_active' => false]);

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id, $gummies->id], 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(300, $gummies->fresh()->stock_quantity);
    }

    public function test_the_menu_page_knows_the_new_items(): void
    {
        $this->get('/menu')
            ->assertOk()
            ->assertSee('ice-matcha', false)
            ->assertSee('top-marshmallow', false)
            ->assertSee('top-gummy-bears', false);
    }
}
