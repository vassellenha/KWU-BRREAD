<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function placeOrder(): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Test QA Hapus',
            'customer_phone' => '081200000000',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 2]],
        ])->assertCreated()->json('order_number');

        return Order::where('order_number', $number)->firstOrFail();
    }

    public function test_the_admin_deletes_a_received_order_and_the_stock_comes_back(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $bread = Component::where('category', 'bread')->firstOrFail();
        $before = $bread->stock_quantity;

        $order = $this->placeOrder();
        $order->forceFill(['status' => Order::STATUS_COMPLETED, 'paid_at' => now(), 'received_at' => now()])->save();
        $this->assertSame($before - 2, $bread->fresh()->stock_quantity);

        $this->actingAs($admin)->delete(route('staff.orders.destroy', $order))->assertSessionHas('success');

        $this->assertModelMissing($order);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertSame($before, $bread->fresh()->stock_quantity);
    }

    public function test_deleting_a_cancelled_order_does_not_return_the_stock_twice(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $bread = Component::where('category', 'bread')->firstOrFail();
        $before = $bread->stock_quantity;

        $order = $this->placeOrder();
        $this->actingAs($admin)->post(route('staff.orders.cancel', $order))->assertSessionHas('success');
        $this->assertSame($before, $bread->fresh()->stock_quantity);

        $this->actingAs($admin)->delete(route('staff.orders.destroy', $order))->assertSessionHas('success');

        $this->assertModelMissing($order);
        $this->assertSame($before, $bread->fresh()->stock_quantity);
    }

    public function test_other_staff_cannot_delete_an_order(): void
    {
        $warehouse = User::factory()->create(['role' => User::ROLE_WAREHOUSE]);
        $order = $this->placeOrder();

        $this->actingAs($warehouse)->delete(route('staff.orders.destroy', $order))->assertForbidden();

        $this->assertModelExists($order);
    }

    public function test_only_the_admin_sees_the_delete_button(): void
    {
        $order = $this->placeOrder();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('staff.orders.index'))->assertOk()->assertSee('Hapus pesanan permanen');

        $warehouse = User::factory()->create(['role' => User::ROLE_WAREHOUSE]);
        $this->actingAs($warehouse)->get(route('staff.orders.index'))->assertOk()->assertDontSee('Hapus pesanan permanen');
    }
}
