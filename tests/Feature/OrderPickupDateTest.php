<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPickupDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function order(string $status = Order::STATUS_PAID): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Test Ambil',
            'customer_phone' => '081200000002',
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

    public function test_staff_set_a_pickup_date_and_the_tracking_page_shows_it(): void
    {
        $order = $this->order();
        $date = now()->addDays(5)->toDateString();

        $this->actingAs($this->staff())
            ->post(route('staff.orders.pickup-date', $order), ['pickup_on' => $date])
            ->assertSessionHas('success');

        $this->assertSame($date, $order->fresh()->pickup_on->toDateString());

        auth()->logout();
        $this->get(route('order.status', $order->confirmation_token))
            ->assertOk()
            ->assertSee('Diambil pada')
            ->assertSee(now()->addDays(5)->locale('id')->translatedFormat('l, j F Y'));
    }

    public function test_the_tracking_page_shows_nothing_until_a_date_is_set(): void
    {
        $order = $this->order();

        $this->get(route('order.status', $order->confirmation_token))
            ->assertOk()
            ->assertDontSee('Diambil pada')
            ->assertDontSee('Jadwal pengambilan');
    }

    public function test_an_empty_date_takes_it_off_again(): void
    {
        $order = $this->order();
        $order->forceFill(['pickup_on' => now()->addDays(2)->toDateString()])->save();

        $this->actingAs($this->staff())
            ->post(route('staff.orders.pickup-date', $order), ['pickup_on' => ''])
            ->assertSessionHas('success');

        $this->assertNull($order->fresh()->pickup_on);
    }

    public function test_a_past_date_or_junk_is_refused(): void
    {
        $order = $this->order();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('staff.orders.pickup-date', $order), ['pickup_on' => now()->subDay()->toDateString()])
            ->assertSessionHas('error');
        $this->actingAs($staff)->post(route('staff.orders.pickup-date', $order), ['pickup_on' => 'besok'])
            ->assertSessionHas('error');
        $this->actingAs($staff)->post(route('staff.orders.pickup-date', $order), ['pickup_on' => now()->addYears(2)->toDateString()])
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->pickup_on);
    }

    public function test_a_cancelled_order_takes_no_date(): void
    {
        $order = $this->order(Order::STATUS_CANCELLED);

        $this->actingAs($this->staff())
            ->post(route('staff.orders.pickup-date', $order), ['pickup_on' => now()->addDay()->toDateString()])
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->pickup_on);
    }

    public function test_guests_cannot_set_a_date(): void
    {
        $order = $this->order();

        $this->post(route('staff.orders.pickup-date', $order), ['pickup_on' => now()->addDay()->toDateString()])
            ->assertRedirect();

        $this->assertNull($order->fresh()->pickup_on);
    }

    public function test_saving_a_date_changes_the_tracking_version_so_an_open_page_refreshes(): void
    {
        $order = $this->order();
        $before = $this->getJson(route('order.version', $order->confirmation_token))->json('version');

        $this->travel(2)->seconds();
        $this->actingAs($this->staff())->post(route('staff.orders.pickup-date', $order), ['pickup_on' => now()->addDay()->toDateString()]);
        auth()->logout();

        $after = $this->getJson(route('order.version', $order->confirmation_token))->json('version');

        $this->assertNotSame($before, $after);
    }

    public function test_the_atmint_chicarron_migration_appends_once_and_leaves_an_empty_shop_alone(): void
    {
        $migration = require base_path('database/migrations/2026_11_26_130100_add_atmint_chicarron_to_contact_whatsapp.php');

        // Nothing saved yet: nothing to append to.
        $migration->up();
        $this->assertNull(SiteSetting::query()->where('key', 'contact_whatsapp')->value('value'));

        SiteSetting::put(['contact_whatsapp' => 'Atmint Amba: 0821 3601 9367, Atmint Sandi: 0811 6362 805']);
        $migration->up();
        $migration->up();

        $stored = SiteSetting::query()->where('key', 'contact_whatsapp')->value('value');
        $this->assertSame('Atmint Amba: 0821 3601 9367, Atmint Sandi: 0811 6362 805, Atmint Chicarron: +62 896-0275-1180', $stored);

        $this->get('/menu')->assertOk()
            ->assertSee('Atmint Chicarron', false)
            ->assertSee('https:\/\/wa.me\/6289602751180', false);
    }

    public function test_the_kuno_migration_styles_chicarron_once(): void
    {
        $migration = require base_path('database/migrations/2026_11_26_140000_give_atmint_chicarron_the_kuno_style.php');

        SiteSetting::put(['contact_whatsapp' => 'Atmint Amba: 0821 3601 9367, Atmint Chicarron: +62 896-0275-1180']);
        $migration->up();
        $migration->up();

        $this->assertSame(
            'Atmint Amba: 0821 3601 9367, Atmint Chicarron: +62 896-0275-1180 (kuno)',
            SiteSetting::query()->where('key', 'contact_whatsapp')->value('value'),
        );
    }
}
