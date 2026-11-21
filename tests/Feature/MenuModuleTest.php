<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->admin = User::factory()->withRole(User::ROLE_ADMIN)->create();
    }

    private function item(string $name): Component
    {
        return Component::where('name', $name)->firstOrFail();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'mrp staff' => [User::ROLE_WAREHOUSE],
            'hr staff' => [User::ROLE_HR],
            'accounting staff' => [User::ROLE_ACCOUNTANT],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_the_admin_can_open_or_change_the_menu(string $role): void
    {
        $user = User::factory()->withRole($role)->create();
        $vanilla = $this->item('Vanilla');

        $this->actingAs($user)->get('/staff/menu')->assertForbidden();
        $this->actingAs($user)->post('/staff/menu', ['category' => 'bread', 'name' => 'Intruder', 'price' => 1])->assertForbidden();
        $this->actingAs($user)->put("/staff/menu/{$vanilla->id}", ['name' => 'Intruder', 'price' => 1, 'hex_color' => '#FFFFFF'])->assertForbidden();
        $this->actingAs($user)->patch("/staff/menu/{$vanilla->id}/price", ['price' => 1])->assertForbidden();
        $this->actingAs($user)->post("/staff/menu/{$vanilla->id}/toggle")->assertForbidden();
        $this->actingAs($user)->delete("/staff/menu/{$vanilla->id}")->assertForbidden();

        $this->assertDatabaseMissing('components', ['name' => 'Intruder']);
        $this->assertSame('8000.00', $vanilla->fresh()->price);
        $this->assertTrue($vanilla->fresh()->is_active);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/staff/menu')->assertRedirect();
    }

    public function test_the_page_lists_every_kind_with_its_price(): void
    {
        $this->actingAs($this->admin)->get('/staff/menu')
            ->assertOk()
            ->assertSeeText('Menu & Prices')
            ->assertSeeText('Plain White Bread')
            ->assertSeeText('Strawberry')
            ->assertSeeText('Rainbow Sprinkles')
            ->assertSee('value="6000"', false)
            ->assertSee('value="8000"', false);
    }

    public function test_the_sidebar_shows_the_menu_link_to_the_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/staff/menu')->assertSee('href="'.route('staff.menu.index').'"', false);

        $warehouse = User::factory()->withRole(User::ROLE_WAREHOUSE)->create();
        $this->actingAs($warehouse)->get('/staff/mrp')->assertDontSee('href="'.route('staff.menu.index').'"', false);
    }

    public function test_a_new_bread_shows_up_in_the_builder_feed(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'bread', 'name' => 'Brioche Bun', 'price' => '9000'])
            ->assertSessionHasNoErrors();

        $bread = $this->item('Brioche Bun');
        $this->assertSame('bread', $bread->category);
        $this->assertSame('9000.00', $bread->price);
        $this->assertTrue($bread->is_active);
        $this->assertSame(0, $bread->stock_quantity);
        $this->assertNull($bread->hex_color);

        $this->getJson('/api/components')->assertJsonFragment(['name' => 'Brioche Bun']);
    }

    public function test_an_ice_cream_flavor_needs_a_valid_color_and_is_saved_in_capitals(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'ice_cream', 'name' => 'Taro', 'price' => '9000'])
            ->assertSessionHasErrors('hex_color');

        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'ice_cream', 'name' => 'Taro', 'price' => '9000', 'hex_color' => 'green'])
            ->assertSessionHasErrors('hex_color');

        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'ice_cream', 'name' => 'Taro', 'price' => '9000', 'hex_color' => '#a3c585'])
            ->assertSessionHasNoErrors();

        $this->assertSame('#A3C585', $this->item('Taro')->hex_color);
    }

    public function test_bread_and_toppings_never_keep_a_color(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'topping', 'name' => 'Crushed Oreo', 'price' => '4000', 'hex_color' => '#000000'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->item('Crushed Oreo')->hex_color);
    }

    public function test_starting_stock_goes_through_the_stock_ledger(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'topping', 'name' => 'Caramel Drizzle', 'price' => '3500', 'initial_stock' => '40', 'reorder_point' => '10'])
            ->assertSessionHasNoErrors();

        $topping = $this->item('Caramel Drizzle');
        $this->assertSame(40, $topping->stock_quantity);
        $this->assertSame(10, $topping->reorder_point);
        $this->assertDatabaseHas('stock_movements', [
            'component_id' => $topping->id,
            'type' => StockMovement::TYPE_RESTOCK,
            'quantity_change' => 40,
            'balance_after' => 40,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_a_name_cannot_repeat_within_one_kind_but_can_across_kinds(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'ice_cream', 'name' => 'vanilla', 'price' => '8000', 'hex_color' => '#FFFFFF'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'topping', 'name' => 'Vanilla', 'price' => '2000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Component::where('name', 'Vanilla')->count());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function badInput(): array
    {
        return [
            'negative price' => [['name' => 'Fine Name', 'price' => '-5'], 'price'],
            'decimal price' => [['name' => 'Fine Name', 'price' => '12.5'], 'price'],
            'text price' => [['name' => 'Fine Name', 'price' => 'free'], 'price'],
            'price too large' => [['name' => 'Fine Name', 'price' => '1000000'], 'price'],
            'missing price' => [['name' => 'Fine Name'], 'price'],
            'script in name' => [['name' => '<script>alert(1)</script>', 'price' => '1000'], 'name'],
            'empty name' => [['name' => '', 'price' => '1000'], 'name'],
            'name too long' => [['name' => str_repeat('a', 61), 'price' => '1000'], 'name'],
            'negative stock' => [['name' => 'Fine Name', 'price' => '1000', 'initial_stock' => '-1'], 'initial_stock'],
        ];
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('badInput')]
    public function test_bad_input_is_rejected_and_nothing_is_saved(array $input, string $field): void
    {
        $before = Component::count();

        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'bread'] + $input)
            ->assertSessionHasErrors($field);

        $this->assertSame($before, Component::count());
    }

    public function test_an_unknown_kind_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/staff/menu', ['category' => 'sauce', 'name' => 'Mystery', 'price' => '1000'])
            ->assertSessionHasErrors('category');
    }

    public function test_the_price_can_be_edited_and_the_message_names_both_prices(): void
    {
        $vanilla = $this->item('Vanilla');

        $this->actingAs($this->admin)
            ->patch("/staff/menu/{$vanilla->id}/price", ['price' => '9500'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Vanilla is now Rp 9.500 (was Rp 8.000). Orders already placed keep their old price.');

        $this->assertSame('9500.00', $vanilla->fresh()->price);
        $this->getJson('/api/components')->assertJsonFragment(['name' => 'Vanilla', 'price' => '9500.00']);
    }

    public function test_a_price_edit_does_not_touch_orders_already_placed(): void
    {
        $bread = $this->item('Plain White Bread');
        $flavor = $this->item('Strawberry');

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('total_price', '28000.00');

        $this->actingAs($this->admin)->patch("/staff/menu/{$flavor->id}/price", ['price' => '20000'])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('28000.00', $order->total_price);
        $this->assertSame('8000.00', OrderItem::where('order_id', $order->id)->where('component_id', $flavor->id)->value('unit_price'));

        // ...while the next order pays the new price.
        $this->postJson('/api/orders', [
            'customer_name' => 'Siti Aminah',
            'customer_phone' => '081234567891',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('total_price', '26000.00');
    }

    public function test_the_full_edit_renames_and_recolors_but_keeps_the_kind(): void
    {
        $strawberry = $this->item('Strawberry');

        $this->actingAs($this->admin)
            ->put("/staff/menu/{$strawberry->id}", ['name' => 'Wild Strawberry', 'price' => '8500', 'hex_color' => '#ff99aa', 'category' => 'bread'])
            ->assertSessionHasNoErrors();

        $strawberry->refresh();
        $this->assertSame('Wild Strawberry', $strawberry->name);
        $this->assertSame('8500.00', $strawberry->price);
        $this->assertSame('#FF99AA', $strawberry->hex_color);
        $this->assertSame('ice_cream', $strawberry->category);
    }

    public function test_an_item_can_be_saved_under_its_own_name_but_not_under_a_sibling(): void
    {
        $vanilla = $this->item('Vanilla');

        $this->actingAs($this->admin)
            ->put("/staff/menu/{$vanilla->id}", ['name' => 'Vanilla', 'price' => '8100', 'hex_color' => '#F3E9D2'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put("/staff/menu/{$vanilla->id}", ['name' => 'Chocolate', 'price' => '8100', 'hex_color' => '#F3E9D2'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Vanilla', $vanilla->fresh()->name);
    }

    public function test_hiding_an_item_removes_it_from_the_builder_and_blocks_new_orders_for_it(): void
    {
        $bread = $this->item('Plain White Bread');
        $flavor = $this->item('Vanilla');

        $this->actingAs($this->admin)->post("/staff/menu/{$flavor->id}/toggle")->assertSessionHasNoErrors();

        $this->assertFalse($flavor->fresh()->is_active);
        $this->getJson('/api/components')->assertJsonMissing(['name' => 'Vanilla']);
        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertStatus(422);

        $this->actingAs($this->admin)->post("/staff/menu/{$flavor->id}/toggle");
        $this->assertTrue($flavor->fresh()->is_active);
        $this->getJson('/api/components')->assertJsonFragment(['name' => 'Vanilla']);
    }

    public function test_an_item_nobody_ordered_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->post('/staff/menu', ['category' => 'topping', 'name' => 'Short Lived', 'price' => '1000', 'initial_stock' => '5']);
        $topping = $this->item('Short Lived');

        $this->actingAs($this->admin)->delete("/staff/menu/{$topping->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('components', ['id' => $topping->id]);
        $this->assertDatabaseMissing('stock_movements', ['component_id' => $topping->id]);
    }

    public function test_an_item_that_was_ordered_cannot_be_deleted_so_history_stays_intact(): void
    {
        $bread = $this->item('Plain White Bread');
        $flavor = $this->item('Vanilla');

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated();

        $this->actingAs($this->admin)
            ->delete("/staff/menu/{$flavor->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('components', ['id' => $flavor->id]);
        $this->assertSame(2, OrderItem::count());
    }

    public function test_the_deploy_seeder_no_longer_overwrites_what_the_admin_changed(): void
    {
        $vanilla = $this->item('Vanilla');
        $this->actingAs($this->admin)->patch("/staff/menu/{$vanilla->id}/price", ['price' => '9900']);
        $this->actingAs($this->admin)->post("/staff/menu/{$vanilla->id}/toggle");
        $this->actingAs($this->admin)->post('/staff/menu', ['category' => 'bread', 'name' => 'Brioche Bun', 'price' => '9000']);

        $this->seed(ComponentSeeder::class);

        $vanilla->refresh();
        $this->assertSame('9900.00', $vanilla->price);
        $this->assertFalse($vanilla->is_active);
        $this->assertTrue($this->item('Brioche Bun')->is_active);
        $this->assertSame(1, Component::where('name', 'Vanilla')->count());
    }
}
