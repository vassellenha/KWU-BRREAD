<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        SiteSetting::put(['contact_whatsapp' => 'Atmint Amba: 0821 3601 9367, Atmint Chicarron: 0896 0275 1180']);
    }

    private function place(string $name, ?string $admin = null): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', array_filter([
            'customer_name' => $name,
            'customer_phone' => '081200000004',
            'admin_name' => $admin,
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ], fn ($value) => $value !== null))->assertCreated()->json('order_number');

        return Order::where('order_number', $number)->firstOrFail();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_checkout_records_the_atmint_the_customer_chose(): void
    {
        $this->assertSame('Atmint Chicarron', $this->place('Pembeli Satu', 'Atmint Chicarron')->chat_admin);
    }

    public function test_a_name_that_is_not_on_the_list_is_not_recorded(): void
    {
        $this->assertNull($this->place('Pembeli Dua', 'Atmint Palsu')->chat_admin);
        $this->assertNull($this->place('Pembeli Tiga')->chat_admin);
    }

    public function test_staff_can_set_and_clear_atmint_and_sales_person(): void
    {
        $order = $this->place('Pembeli Empat');

        $this->actingAs($this->staff())->post(route('staff.orders.attribution', $order), ['chat_admin' => 'Atmint Amba', 'sold_by' => ' Jordan '])
            ->assertSessionHas('success');
        $this->assertSame('Atmint Amba', $order->fresh()->chat_admin);
        $this->assertSame('Jordan', $order->fresh()->sold_by);

        $this->actingAs($this->staff())->post(route('staff.orders.attribution', $order), ['chat_admin' => '', 'sold_by' => ''])
            ->assertSessionHas('success');
        $this->assertNull($order->fresh()->chat_admin);
        $this->assertNull($order->fresh()->sold_by);
    }

    public function test_an_unknown_atmint_is_refused(): void
    {
        $order = $this->place('Pembeli Lima');

        $this->actingAs($this->staff())->post(route('staff.orders.attribution', $order), ['chat_admin' => 'Atmint Palsu'])
            ->assertSessionHas('error');
        $this->assertNull($order->fresh()->chat_admin);
    }

    public function test_the_orders_page_filters_by_atmint(): void
    {
        $this->place('Lewat Amba', 'Atmint Amba');
        $this->place('Lewat Chicarron', 'Atmint Chicarron');
        $this->place('Tanpa Atmint');

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['atmint' => 'Atmint Chicarron']))
            ->assertOk()
            ->assertSee('Lewat Chicarron')
            ->assertDontSee('Lewat Amba')
            ->assertDontSee('Tanpa Atmint');

        $this->actingAs($this->staff())->get(route('staff.orders.index', ['atmint' => 'none']))
            ->assertOk()
            ->assertSee('Tanpa Atmint')
            ->assertDontSee('Lewat Amba');
    }

    public function test_the_sales_report_ranks_atmints_and_sales_people(): void
    {
        $this->place('A', 'Atmint Amba')->update(['sold_by' => 'Jordan']);
        $this->place('B', 'Atmint Amba')->update(['sold_by' => 'Jordan']);
        $this->place('C', 'Atmint Chicarron');

        $today = CarbonImmutable::today();
        $report = app(SalesReport::class)->build($today, $today);

        $this->assertSame('Atmint Amba', $report['atmints'][0]['name']);
        $this->assertSame(2, $report['atmints'][0]['orders']);
        $this->assertSame('Jordan', $report['sellers'][0]['name']);
        $this->assertNull($report['sellers']->last()['name']);

        $this->actingAs($this->staff())->get(route('staff.sales.index'))
            ->assertOk()
            ->assertSee('Orders by atmint')
            ->assertSee('Jordan');
    }

    public function test_the_migration_credits_the_26_nov_orders_to_jordan(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00', 'Asia/Jakarta'));
        $onTheDay = $this->place('Hari Itu');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Asia/Jakarta'));
        $before = $this->place('Sehari Sebelumnya');
        $this->travelBack();

        $migration = require database_path('migrations/2026_11_26_150000_add_chat_admin_and_sold_by_to_orders_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('Jordan', DB::table('orders')->where('id', $onTheDay->id)->value('sold_by'));
        $this->assertNull(DB::table('orders')->where('id', $before->id)->value('sold_by'));
    }

    public function test_the_backfill_migration_fills_known_customers_and_skips_the_rest(): void
    {
        SiteSetting::put(['contact_whatsapp' => 'Atmint Amba: 0821 3601 9367, Atmint Rusdi: 0811 6362 805']);
        $hanif = $this->place('Hanif Pratama');
        $komang = $this->place('Komang jjie');
        $afif = $this->place('Afif Nur Sena');
        $already = $this->place('Tegar Wibowo', 'Atmint Rusdi');

        $migration = require database_path('migrations/2026_11_26_160000_fill_chat_admin_for_existing_orders.php');
        $migration->up();

        $this->assertSame('Atmint Amba', $hanif->fresh()->chat_admin);
        $this->assertSame('Atmint Rusdi', $komang->fresh()->chat_admin);
        $this->assertNull($afif->fresh()->chat_admin);
        $this->assertSame('Atmint Rusdi', $already->fresh()->chat_admin);
    }
}
