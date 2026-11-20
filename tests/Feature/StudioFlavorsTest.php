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
 * The multi-layer flavors of the Studio (Neapolitan, Durian Musang King, Es Teler): seeded ready to sell,
 * orderable like any other flavor, and drawn with their own art on the order status page.
 */
class StudioFlavorsTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_FLAVORS = ['Neapolitan', 'Durian Musang King', 'Es Teler'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function newFlavors(): array
    {
        return [
            'neapolitan' => ['Neapolitan', 'ice-neapolitan'],
            'durian musang king' => ['Durian Musang King', 'ice-durian-musang-king'],
            'es teler' => ['Es Teler', 'ice-es-teler'],
        ];
    }

    private function ingredient(string $name): Component
    {
        return Component::where('name', $name)->firstOrFail();
    }

    public function test_the_seeder_puts_the_three_new_flavors_on_sale(): void
    {
        foreach (self::NEW_FLAVORS as $name) {
            $flavor = $this->ingredient($name);

            $this->assertSame('ice_cream', $flavor->category);
            $this->assertTrue($flavor->is_active);
            $this->assertSame('8000.00', $flavor->price);
            $this->assertSame(120, $flavor->stock_quantity);
            $this->assertSame(40, $flavor->reorder_point);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $flavor->hex_color);
        }

        $listed = collect($this->getJson('/api/components')->assertOk()->json('ice_cream'))->pluck('name');

        foreach (self::NEW_FLAVORS as $name) {
            $this->assertContains($name, $listed);
        }
    }

    public function test_seeding_again_keeps_what_the_admin_changed(): void
    {
        $this->ingredient('Durian Musang King')->update(['is_active' => false, 'price' => 9500]);

        $this->seed(ComponentSeeder::class);

        $durian = $this->ingredient('Durian Musang King');
        $this->assertFalse($durian->is_active);
        $this->assertSame('9500.00', $durian->price);
        $this->assertSame(1, Component::where('name', 'Durian Musang King')->count());
        $this->assertNotContains('Durian Musang King', collect($this->getJson('/api/components')->json('ice_cream'))->pluck('name'));
    }

    #[DataProvider('newFlavors')]
    public function test_a_sandwich_with_a_new_flavor_can_be_ordered(string $name, string $art): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient($name);
        $stock = $flavor->stock_quantity;

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('total_price', '28000.00')->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $this->assertSame($stock - 2, $flavor->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'component_id' => $flavor->id,
            'order_id' => $order->id,
            'type' => StockMovement::TYPE_CONSUMPTION,
            'quantity_change' => -2,
        ]);

        $order->forceFill(['status' => Order::STATUS_CONFIRMED, 'confirmation_token' => Str::random(40), 'confirmed_at' => now()])->save();

        $this->get("/order/status/{$order->confirmation_token}")
            ->assertOk()
            ->assertSee($name)
            ->assertSee("/img/cutout/{$art}.webp", false)
            ->assertDontSee('/img/cutout/ice-vanilla.webp', false);
    }

    public function test_an_unknown_flavor_is_rejected(): void
    {
        $bread = $this->ingredient('Plain White Bread');

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, 999999], 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.component_ids.1');

        $this->assertSame(0, Order::count());
    }

    public function test_a_hidden_flavor_cannot_be_ordered(): void
    {
        $bread = $this->ingredient('Plain White Bread');
        $flavor = $this->ingredient('Es Teler');
        $flavor->update(['is_active' => false]);

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(120, $flavor->fresh()->stock_quantity);
    }

    public function test_the_menu_page_and_the_builder_know_the_new_flavors(): void
    {
        $this->get('/menu')
            ->assertOk()
            ->assertSee('ice-neapolitan', false)
            ->assertSee('ice-durian-musang-king', false)
            ->assertSee('ice-es-teler', false);

        $this->get('/builder')
            ->assertOk()
            ->assertSee('data-flavor-overrides', false);
    }
}
