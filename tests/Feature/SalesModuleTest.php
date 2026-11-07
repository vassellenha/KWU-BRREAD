<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use App\Services\SalesReport;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\ExpenseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SalesModuleTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-20 10:00:00';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->admin = User::factory()->withRole(User::ROLE_ADMIN)->create();
        $this->travelTo(self::NOW);
    }

    /**
     * @param  list<string>  $ingredients
     * @return array{component_ids: list<int>, quantity: int}
     */
    private function sandwich(array $ingredients, int $quantity = 1): array
    {
        return [
            'component_ids' => array_map(fn (string $name) => Component::where('name', $name)->firstOrFail()->id, $ingredients),
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  list<array{component_ids: list<int>, quantity: int}>  $items
     */
    private function orderAt(string $moment, array $items): void
    {
        $this->travelTo($moment);

        $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => $items,
        ])->assertCreated();

        $this->travelTo(self::NOW);
    }

    private function salesPage(string $query = ''): TestResponse
    {
        return $this->actingAs($this->admin)->get('/staff/sales'.$query)->assertOk();
    }

    public function test_checkout_records_the_price_each_ingredient_sold_at(): void
    {
        $this->orderAt('2026-11-19 12:00:00', [$this->sandwich(['Plain White Bread', 'Strawberry'], 2)]);

        $this->assertDatabaseHas('order_items', [
            'component_id' => Component::where('name', 'Strawberry')->value('id'),
            'unit_price' => 8000,
        ]);
    }

    public function test_a_later_price_change_does_not_rewrite_past_revenue(): void
    {
        $this->orderAt('2026-11-19 12:00:00', [$this->sandwich(['Plain White Bread', 'Strawberry'], 2)]);

        Component::where('name', 'Strawberry')->update(['price' => 50000]);

        $report = $this->salesPage()->viewData('report');

        $this->assertSame(28000.0, $report['combinations'][0]['revenue']);
        $this->assertSame(28000.0, array_sum(array_column($report['trend'], 'revenue')));
    }

    public function test_the_chart_axis_tops_out_just_above_the_tallest_column(): void
    {
        $this->assertSame(1.0, (float) SalesReport::axisCeiling(0));
        $this->assertSame(1.0, (float) SalesReport::axisCeiling(1));
        $this->assertSame(30.0, (float) SalesReport::axisCeiling(27));
        $this->assertSame(100.0, (float) SalesReport::axisCeiling(95));
        $this->assertSame(500000.0, (float) SalesReport::axisCeiling(450000));
    }

    public function test_the_same_ingredients_in_any_order_count_as_one_combination(): void
    {
        $this->orderAt('2026-11-18 12:00:00', [
            $this->sandwich(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles', 'Chocolate Sauce'], 2),
            $this->sandwich(['Whole Wheat Bread', 'Vanilla']),
        ]);
        $this->orderAt('2026-11-19 12:00:00', [
            $this->sandwich(['Strawberry', 'Chocolate Sauce', 'Plain White Bread', 'Rainbow Sprinkles'], 3),
        ]);

        $report = $this->salesPage()->viewData('report');

        $this->assertSame(2, $report['combination_count']);
        $this->assertSame([
            'label' => 'Strawberry + Plain White Bread',
            'toppings' => 'Chocolate Sauce, Rainbow Sprinkles',
            'units' => 5,
            'revenue' => 100000.0,
        ], $report['combinations'][0]);
        $this->assertSame('No toppings', $report['combinations'][1]['toppings']);
    }

    public function test_only_the_top_combinations_are_listed(): void
    {
        $toppingChoices = [[], ['Chocolate Sauce'], ['Rainbow Sprinkles'], ['Chocolate Sauce', 'Rainbow Sprinkles']];
        $items = [];

        foreach (['Plain White Bread', 'Whole Wheat Bread'] as $bread) {
            foreach (['Vanilla', 'Strawberry'] as $flavor) {
                foreach ($toppingChoices as $toppings) {
                    $items[] = $this->sandwich([$bread, $flavor, ...$toppings]);
                }
            }
        }

        $this->orderAt('2026-11-19 12:00:00', $items);

        $report = $this->salesPage()->viewData('report');

        $this->assertSame(16, $report['combination_count']);
        $this->assertCount(10, $report['combinations']);
    }

    public function test_ingredient_rankings_include_what_has_not_sold(): void
    {
        $this->orderAt('2026-11-19 12:00:00', [
            $this->sandwich(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles'], 3),
            $this->sandwich(['Plain White Bread', 'Vanilla']),
        ]);

        $report = $this->salesPage()->viewData('report');

        $this->assertSame(['Strawberry' => 3, 'Vanilla' => 1, 'Chocolate' => 0], $report['flavors']->pluck('units', 'name')->all());
        $this->assertSame(75.0, $report['flavors'][0]['share']);
        $this->assertSame(['Rainbow Sprinkles' => 3, 'Chocolate Sauce' => 0], $report['toppings']->pluck('units', 'name')->all());
        $this->assertSame(75.0, $report['topping_rate']);
    }

    public function test_revenue_matches_income_in_accounting(): void
    {
        $this->orderAt('2026-11-10 09:00:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 4)]);
        $this->orderAt('2026-11-12 09:00:00', [$this->sandwich(['Whole Wheat Bread', 'Chocolate', 'Chocolate Sauce'])]);

        $query = '?from=2026-11-01&to=2026-11-20';
        $sales = $this->salesPage($query)->viewData('report');
        $accounting = $this->actingAs($this->admin)->get('/staff/accounting'.$query)->viewData('stats');

        $this->assertSame($accounting['total_income'], $sales['stats']['revenue']);
        $this->assertSame((float) Order::sum('total_price'), array_sum(array_column($sales['trend'], 'revenue')));
    }

    public function test_orders_outside_the_range_only_count_toward_the_previous_period(): void
    {
        $this->orderAt('2026-11-05 12:00:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 2)]);
        $this->orderAt('2026-11-15 12:00:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 3)]);
        $this->orderAt('2026-10-01 12:00:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 9)]);

        $report = $this->salesPage('?from=2026-11-11&to=2026-11-20')->viewData('report');

        $this->assertSame(10, $report['days']);
        $this->assertSame(3, $report['stats']['sandwiches']);
        $this->assertSame(1, $report['stats']['orders']);
        $this->assertSame(2, $report['previous']['sandwiches']);
        $this->assertSame(28000.0, $report['previous']['revenue']);
    }

    public function test_an_order_on_the_last_day_of_the_range_is_counted(): void
    {
        $this->orderAt('2026-11-20 09:59:00', [$this->sandwich(['Plain White Bread', 'Vanilla'])]);
        $this->orderAt('2026-11-10 23:59:00', [$this->sandwich(['Plain White Bread', 'Vanilla'])]);

        $report = $this->salesPage('?from=2026-11-10&to=2026-11-10')->viewData('report');

        $this->assertSame(1, $report['stats']['orders']);
    }

    public function test_the_trend_fills_empty_days_and_widens_its_buckets_for_long_ranges(): void
    {
        $this->orderAt('2026-11-18 12:00:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 2)]);

        $thirtyDays = $this->salesPage()->viewData('report')['trend'];
        $this->assertCount(30, $thirtyDays);
        $this->assertSame(['label' => '18 Nov', 'units' => 2, 'revenue' => 28000.0], $thirtyDays[27]);
        $this->assertSame(0, $thirtyDays[28]['units']);

        $ninetyDays = $this->salesPage('?from=2026-08-23&to=2026-11-20')->viewData('report')['trend'];
        $this->assertSame('23 Aug', $ninetyDays[0]['label']);
        $this->assertLessThanOrEqual(15, count($ninetyDays));
        $this->assertSame(2, array_sum(array_column($ninetyDays, 'units')));

        $aYear = $this->salesPage('?from=2025-11-21&to=2026-11-20')->viewData('report')['trend'];
        $this->assertCount(13, $aYear);
        $this->assertSame('Nov 2026', $aYear[12]['label']);
        $this->assertSame(2, $aYear[12]['units']);
    }

    public function test_busiest_days_and_hours_use_local_time(): void
    {
        $this->orderAt('2026-11-15 18:30:00', [$this->sandwich(['Plain White Bread', 'Vanilla'], 4)]);
        $this->orderAt('2026-11-16 09:15:00', [$this->sandwich(['Plain White Bread', 'Vanilla'])]);

        $report = $this->salesPage()->viewData('report');

        $this->assertSame(['label' => 'Sun', 'units' => 4], $report['weekdays'][6]);
        $this->assertSame(['label' => 'Mon', 'units' => 1], $report['weekdays'][0]);
        $this->assertSame(['label' => '18:00', 'units' => 4], $report['hours'][18]);
        $this->assertSame(['label' => '09:00', 'units' => 1], $report['hours'][9]);
    }

    public function test_the_page_shows_the_results(): void
    {
        $this->orderAt('2026-11-19 12:00:00', [$this->sandwich(['Plain White Bread', 'Strawberry', 'Rainbow Sprinkles'], 2)]);

        $this->salesPage()
            ->assertSee('Strawberry + Plain White Bread')
            ->assertSee('Rainbow Sprinkles')
            ->assertSee('Rp 34.000')
            ->assertSee('Peak: Thu')
            ->assertSee('Peak: 12:00')
            ->assertDontSee('No sales in this period');
    }

    public function test_a_period_without_sales_shows_an_empty_state(): void
    {
        $this->salesPage()
            ->assertSee('No sales in this period')
            ->assertDontSee('Best-selling combinations');
    }

    public function test_dates_outside_the_allowed_window_are_clamped(): void
    {
        $farFuture = $this->salesPage('?from=2026-11-01&to=2099-01-01');
        $this->assertSame('2026-11-20', $farFuture->viewData('to')->toDateString());

        $ancient = $this->salesPage('?from=0001-01-01&to=2026-11-20');
        $this->assertSame('2020-01-01', $ancient->viewData('from')->toDateString());

        $swapped = $this->salesPage('?from=2026-11-20&to=2026-11-01');
        $this->assertSame('2026-11-01', $swapped->viewData('from')->toDateString());
        $this->assertSame('2026-11-20', $swapped->viewData('to')->toDateString());
    }

    public function test_staff_cannot_open_the_sales_page(): void
    {
        foreach ([User::ROLE_WAREHOUSE, User::ROLE_HR, User::ROLE_ACCOUNTANT] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->get('/staff/sales')
                ->assertForbidden();
        }
    }

    public function test_redeploying_keeps_live_stock_but_still_updates_the_catalog(): void
    {
        $vanilla = Component::where('name', 'Vanilla')->firstOrFail();
        $vanilla->update(['stock_quantity' => 7, 'price' => 1]);

        $this->seed(ComponentSeeder::class);

        $vanilla->refresh();
        $this->assertSame(7, $vanilla->stock_quantity);
        $this->assertSame('8000.00', $vanilla->price);
    }

    public function test_retiring_an_ingredient_keeps_its_sales_history(): void
    {
        $retired = Component::create(['name' => 'Matcha', 'category' => 'ice_cream', 'price' => 9000, 'stock_quantity' => 10, 'unit' => 'pcs', 'reorder_point' => 1, 'is_active' => true]);
        $this->orderAt('2026-11-19 12:00:00', [$this->sandwich(['Plain White Bread', 'Matcha'], 2)]);

        $this->seed(ComponentSeeder::class);

        $this->assertFalse($retired->fresh()->is_active);
        $report = $this->salesPage()->viewData('report');
        $this->assertSame('Matcha + Plain White Bread', $report['combinations'][0]['label']);
        $this->assertSame(2, $report['flavors']->firstWhere('name', 'Matcha')['units']);
    }

    public function test_an_unsold_retired_ingredient_drops_out_of_the_rankings(): void
    {
        Component::create(['name' => 'Matcha', 'category' => 'ice_cream', 'price' => 9000, 'stock_quantity' => 10, 'unit' => 'pcs', 'reorder_point' => 1, 'is_active' => false]);

        $report = $this->salesPage()->viewData('report');

        $this->assertNull($report['flavors']->firstWhere('name', 'Matcha'));
    }

    public function test_redeploying_keeps_changes_made_in_hr(): void
    {
        $this->seed(EmployeeSeeder::class);
        $employee = Employee::firstOrFail();
        $employee->update(['is_active' => false, 'position' => 'Manager']);

        $this->seed(EmployeeSeeder::class);

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertSame('Manager', $employee->position);
    }

    public function test_redeploying_does_not_bring_back_removed_expenses_or_move_their_dates(): void
    {
        $this->seed(ExpenseSeeder::class);
        $kept = Expense::firstOrFail();
        $originalDate = $kept->expense_date->toDateString();
        Expense::whereKeyNot($kept->id)->delete();

        $this->travel(5)->days();
        $this->seed(ExpenseSeeder::class);

        $this->assertSame(1, Expense::count());
        $this->assertSame($originalDate, $kept->fresh()->expense_date->toDateString());
    }
}
