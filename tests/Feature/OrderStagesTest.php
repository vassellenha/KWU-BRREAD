<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function order(string $status): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Test Tahap',
            'customer_phone' => '081200000001',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => $status, 'confirmation_token' => 'tok-'.$order->id])->save();

        return $order;
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_paid_to_preparing_to_ready_in_order(): void
    {
        $order = $this->order(Order::STATUS_PAID);
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('staff.orders.preparing', $order))->assertSessionHas('success');
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->preparing_at);

        $this->actingAs($staff)->post(route('staff.orders.ready', $order))->assertSessionHas('success');
        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $staff = $this->staff();
        $unpaid = $this->order(Order::STATUS_CONFIRMED);
        $paid = $this->order(Order::STATUS_PAID);

        $this->actingAs($staff)->post(route('staff.orders.preparing', $unpaid))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_CONFIRMED, $unpaid->fresh()->status);

        $this->actingAs($staff)->post(route('staff.orders.ready', $paid))->assertSessionHas('error');
        $this->assertSame(Order::STATUS_PAID, $paid->fresh()->status);
    }

    public function test_a_ready_order_cannot_be_cancelled(): void
    {
        $order = $this->order(Order::STATUS_READY);

        $this->actingAs($this->staff())->post(route('staff.orders.cancel', $order));

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
    }

    public function test_tabs_filter_by_the_new_stages(): void
    {
        $confirmed = $this->order(Order::STATUS_CONFIRMED);
        $processing = $this->order(Order::STATUS_PROCESSING);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('staff.orders.index', ['status' => 'pending']))
            ->assertOk()->assertSee($confirmed->order_number)->assertDontSee($processing->order_number);

        $this->actingAs($staff)->get(route('staff.orders.index', ['status' => 'processing']))
            ->assertOk()->assertSee($processing->order_number)->assertDontSee($confirmed->order_number);
    }

    public function test_a_preparing_order_still_packs_until_staff_mark_it_ready(): void
    {
        $order = $this->order(Order::STATUS_PROCESSING);
        $order->forceFill(['paid_at' => now(), 'preparing_at' => now()])->save();

        $this->get('/order/status/'.$order->confirmation_token)
            ->assertOk()
            ->assertSee('3. Pesanan disiapkan')
            ->assertSee('4. Pesanan dikemas')
            ->assertDontSee('4. Pesanan siap diambil');
    }

    public function test_tracking_page_shows_the_four_steps_and_the_ready_state(): void
    {
        $order = $this->order(Order::STATUS_READY);
        $order->forceFill(['paid_at' => now(), 'preparing_at' => now(), 'ready_at' => now()])->save();

        $this->get('/order/status/'.$order->confirmation_token)
            ->assertOk()
            ->assertSee('1. Masuk')
            ->assertSee('2. Pembayaran berhasil')
            ->assertSee('3. Pesanan disiapkan')
            ->assertSee('4. Pesanan siap diambil')
            ->assertSee('Pesananmu siap diambil!')
            ->assertDontSee('Pesanan Diterima');
    }

    public function test_the_staff_order_list_shows_toppings_and_a_detail_view(): void
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();
        $toppings = Component::where('category', 'topping')->take(2)->get();

        $this->postJson('/api/orders', [
            'customer_name' => 'Pembeli Topping',
            'customer_phone' => '081200000009',
            'items' => [['component_ids' => [$bread->id, $flavor->id, ...$toppings->pluck('id')->all()], 'quantity' => 2]],
        ])->assertCreated();

        $page = $this->actingAs($this->staff())->get(route('staff.orders.index'))->assertOk();

        $page->assertSee('Lihat detail', false)
            ->assertSee('Roti:', false)
            ->assertSee('Es krim:', false)
            ->assertSee('Topping:', false);

        foreach ($toppings as $topping) {
            $page->assertSee($topping->name, false);
        }
    }
}
