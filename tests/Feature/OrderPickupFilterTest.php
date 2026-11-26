<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPickupFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function order(string $name, string $status, ?string $pickup = null, int $quantity = 1): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => $name,
            'customer_phone' => '081200000003',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => $quantity]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => $status, 'confirmation_token' => 'tok-'.$order->id, 'pickup_on' => $pickup])->save();

        return $order;
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_the_pickup_filter_lists_only_that_days_orders(): void
    {
        $friday = now()->addDays(1)->toDateString();
        $wednesday = now()->addDays(6)->toDateString();
        $this->order('Pembeli Jumat', Order::STATUS_PAID, $friday);
        $this->order('Pembeli Rabu', Order::STATUS_PAID, $wednesday);
        $this->order('Pembeli Tanpa Tanggal', Order::STATUS_CONFIRMED);

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['pickup' => $wednesday]))
            ->assertOk()
            ->assertSee('Pembeli Rabu')
            ->assertDontSee('Pembeli Jumat')
            ->assertDontSee('Pembeli Tanpa Tanggal');

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['pickup' => 'none']))
            ->assertOk()
            ->assertSee('Pembeli Tanpa Tanggal')
            ->assertDontSee('Pembeli Rabu');
    }

    public function test_the_summary_counts_orders_and_sandwiches_per_day_and_skips_cancelled(): void
    {
        $day = now()->addDays(3)->toDateString();
        $this->order('Pembeli Satu', Order::STATUS_PAID, $day, 2);
        $this->order('Pembeli Dua', Order::STATUS_CONFIRMED, $day, 1);
        $this->order('Pembeli Batal', Order::STATUS_CANCELLED, $day, 5);

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['pickup' => $day]))
            ->assertOk()
            ->assertSee('2 pesanan, 3 sandwich')
            ->assertSee('Pembeli Satu (2)')
            ->assertSee('Pembeli Dua (1)')
            ->assertDontSee('Pembeli Batal (5)');
    }

    public function test_a_junk_pickup_value_is_ignored(): void
    {
        $this->order('Pembeli Biasa', Order::STATUS_PAID, now()->addDay()->toDateString());

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['pickup' => 'besok; drop table']))
            ->assertOk()
            ->assertSee('Pembeli Biasa');
    }

    public function test_confirmed_and_unconfirmed_orders_have_their_own_filters(): void
    {
        $this->order('Belum Konfirmasi', Order::STATUS_PENDING);
        $this->order('Sudah Konfirmasi', Order::STATUS_CONFIRMED);
        $this->order('Sudah Bayar', Order::STATUS_PAID);

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['status' => 'unconfirmed']))
            ->assertOk()
            ->assertSee('Belum Konfirmasi')
            ->assertDontSee('Sudah Konfirmasi')
            ->assertDontSee('Sudah Bayar');

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['status' => 'confirmed']))
            ->assertOk()
            ->assertSee('Sudah Konfirmasi')
            ->assertDontSee('Belum Konfirmasi')
            ->assertDontSee('Sudah Bayar');

        // The old "Masuk" view (?status=pending) still holds both, though it no longer has a tab.
        $this->actingAs($this->staff())->get(route('staff.orders.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Belum Konfirmasi')
            ->assertSee('Sudah Konfirmasi')
            ->assertDontSee('Sudah Bayar');
    }
}
