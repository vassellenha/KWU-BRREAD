<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceivedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function order(string $status): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill([
            'status' => $status,
            'confirmation_token' => 'tok'.str_repeat('x', 29),
            'confirmed_at' => now(),
            'paid_at' => in_array($status, [Order::STATUS_PAID, Order::STATUS_COMPLETED], true) ? now() : null,
        ])->save();

        return $order->refresh();
    }

    private function receiveUrl(Order $order): string
    {
        return route('order.received', $order->confirmation_token);
    }

    public function test_a_paid_order_shows_the_received_button_and_a_confirmed_one_does_not(): void
    {
        $paid = $this->order(Order::STATUS_PAID);
        $this->get(route('order.status', $paid->confirmation_token))->assertOk()->assertSee('Pesanan Diterima');

        $paid->update(['status' => Order::STATUS_CONFIRMED]);
        $this->get(route('order.status', $paid->confirmation_token))->assertOk()->assertDontSee('Sandwich-nya sudah sampai');
    }

    public function test_the_customer_receives_a_paid_order_once(): void
    {
        $order = $this->order(Order::STATUS_PAID);

        $this->post($this->receiveUrl($order))->assertRedirect()->assertSessionHas('success');
        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_COMPLETED, $fresh->status);
        $this->assertNotNull($fresh->received_at);
        $this->assertNotNull($fresh->paid_at);

        $this->post($this->receiveUrl($order))->assertSessionHas('info');

        $this->get(route('order.status', $order->confirmation_token))
            ->assertOk()
            ->assertSee('Pesanan Diterima')
            ->assertSee('4. Diterima')
            ->assertSee('Pesanan sudah kamu terima')
            ->assertDontSee('Sandwich-nya sudah sampai');
    }

    public function test_an_order_that_is_not_paid_or_is_cancelled_cannot_be_received(): void
    {
        $order = $this->order(Order::STATUS_CONFIRMED);
        $this->post($this->receiveUrl($order))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);

        $order->update(['status' => Order::STATUS_CANCELLED]);
        $this->post($this->receiveUrl($order))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_staff_cannot_receive_an_order_for_the_customer(): void
    {
        $order = $this->order(Order::STATUS_PAID);

        $this->actingAs($this->admin)->post($this->receiveUrl($order))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_the_admin_sees_the_received_status_and_cannot_undo_it(): void
    {
        $order = $this->order(Order::STATUS_PAID);
        $this->post($this->receiveUrl($order));

        $this->actingAs($this->admin)->get(route('staff.orders.index'))->assertOk()->assertSee('Diterima');
        $this->actingAs($this->admin)->get(route('staff.orders.index', ['status' => 'completed']))->assertOk()->assertSee($order->order_number);

        // marking it paid again must not pull it back to "paid", and it cannot be cancelled any more
        $this->actingAs($this->admin)->post(route('staff.orders.paid', $order))->assertSessionHas('info');
        $this->actingAs($this->admin)->post(route('staff.orders.cancel', $order))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }
}
