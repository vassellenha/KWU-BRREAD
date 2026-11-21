<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The "save all" endpoints of the Stock, Menu and HR pages: many rows in one atomic request,
 * errors per row, and a row somebody else changed in the meantime is refused instead of overwritten.
 */
class StaffBatchSaveTest extends TestCase
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
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function stockRow(Component $component, array $fields = []): array
    {
        return $fields + ['version' => $component->stockVersion(), 'direction' => 'in', 'quantity' => 5];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function menuRow(Component $component, array $fields = []): array
    {
        return $fields + [
            'version' => $component->menuVersion(),
            'name' => $component->name,
            'price' => (int) $component->price,
            'hex_color' => $component->hex_color,
            'is_active' => $component->is_active,
        ];
    }

    // ---- stock (MRP) ----------------------------------------------------------------------------------

    public function test_stock_save_all_changes_every_row_and_logs_one_movement_per_row(): void
    {
        $vanilla = $this->item('Vanilla');
        $chocolate = $this->item('Chocolate');
        $vanillaBefore = $vanilla->stock_quantity;
        $chocolateBefore = $chocolate->stock_quantity;

        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'shared_note' => 'weekly restock',
            'rows' => [
                $vanilla->id => $this->stockRow($vanilla, ['quantity' => 10, 'cost' => 250000]),
                $chocolate->id => $this->stockRow($chocolate, ['direction' => 'out', 'quantity' => 2, 'note' => 'dropped tray']),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('saved', 2)
            ->assertJsonPath("rows.{$vanilla->id}.cells.stock", ($vanillaBefore + 10).' '.$vanilla->unit)
            ->assertJsonStructure(['message', 'rows', 'stats' => ['total_ingredients', 'low_stock', 'total_units', 'stock_value']]);

        $this->assertSame($vanillaBefore + 10, $vanilla->fresh()->stock_quantity);
        $this->assertSame($chocolateBefore - 2, $chocolate->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', ['component_id' => $vanilla->id, 'type' => StockMovement::TYPE_RESTOCK, 'quantity_change' => 10, 'note' => 'weekly restock', 'user_id' => $this->admin->id]);
        $this->assertDatabaseHas('stock_movements', ['component_id' => $chocolate->id, 'type' => StockMovement::TYPE_ADJUSTMENT, 'quantity_change' => -2, 'note' => 'dropped tray']);
        $this->assertDatabaseHas('expenses', ['amount' => 250000, 'category' => 'Ingredients']);
    }

    public function test_one_invalid_stock_row_saves_nothing_and_the_error_comes_back_per_row(): void
    {
        $vanilla = $this->item('Vanilla');
        $chocolate = $this->item('Chocolate');
        $before = [$vanilla->stock_quantity, $chocolate->stock_quantity];

        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'rows' => [
                $vanilla->id => $this->stockRow($vanilla, ['quantity' => 10, 'cost' => 5000]),
                $chocolate->id => $this->stockRow($chocolate, ['direction' => 'out', 'quantity' => 99999]),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["rows.{$chocolate->id}.quantity"])
            ->assertJsonMissingValidationErrors(["rows.{$vanilla->id}.quantity"]);

        $this->assertSame($before, [$vanilla->fresh()->stock_quantity, $chocolate->fresh()->stock_quantity]);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_a_stock_row_that_is_not_valid_input_is_reported_on_its_field(): void
    {
        $vanilla = $this->item('Vanilla');

        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'rows' => [$vanilla->id => $this->stockRow($vanilla, ['quantity' => 0])],
        ])->assertJsonValidationErrors(["rows.{$vanilla->id}.quantity"]);

        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'rows' => [$vanilla->id => $this->stockRow($vanilla, ['direction' => 'out', 'quantity' => 1, 'cost' => 100])],
        ])->assertJsonValidationErrors(["rows.{$vanilla->id}.cost"]);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_stock_row_changed_by_someone_else_is_refused_and_not_overwritten(): void
    {
        $vanilla = $this->item('Vanilla');
        $staleVersion = $vanilla->stockVersion();
        $vanilla->recordStockMovement(StockMovement::TYPE_RESTOCK, 7, $this->admin->id);
        $current = $vanilla->fresh()->stock_quantity;

        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'rows' => [$vanilla->id => ['version' => $staleVersion, 'direction' => 'in', 'quantity' => 50]],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$vanilla->id}.version"]);

        $this->assertSame($current, $vanilla->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_sending_no_changes_is_refused_and_changes_nothing(): void
    {
        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), ['rows' => []])->assertUnprocessable();

        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_row_key_that_is_not_an_id_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson(route('staff.mrp.bulk'), [
            'rows' => ['abc' => ['version' => 'x', 'direction' => 'in', 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('rows');
    }

    // ---- menu -----------------------------------------------------------------------------------------

    public function test_menu_save_all_updates_name_price_color_and_availability_together(): void
    {
        $vanilla = $this->item('Vanilla');
        $bread = $this->item('Plain White Bread');
        $oldBreadPrice = (int) $bread->price;

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), [
            'rows' => [
                $vanilla->id => $this->menuRow($vanilla, ['name' => 'Vanilla Bean', 'price' => 9500, 'hex_color' => '#f5e6c8']),
                $bread->id => $this->menuRow($bread, ['is_active' => false]),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('saved', 2)
            ->assertJsonPath("rows.{$bread->id}.flags.active", false)
            ->assertJsonStructure(['stats' => ['total', 'active', 'hidden']]);

        $vanilla->refresh();
        $this->assertSame('Vanilla Bean', $vanilla->name);
        $this->assertSame('9500.00', $vanilla->price);
        $this->assertSame('#F5E6C8', $vanilla->hex_color);
        $this->assertFalse($bread->fresh()->is_active);
        $this->assertSame($oldBreadPrice, (int) $bread->fresh()->price);
    }

    public function test_one_invalid_menu_row_saves_nothing(): void
    {
        $vanilla = $this->item('Vanilla');
        $chocolate = $this->item('Chocolate');
        $price = $vanilla->price;

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), [
            'rows' => [
                $vanilla->id => $this->menuRow($vanilla, ['price' => 12000]),
                $chocolate->id => $this->menuRow($chocolate, ['name' => 'Vanilla Bean', 'price' => -1]),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$chocolate->id}.price"]);

        $this->assertSame($price, $vanilla->fresh()->price);
        $this->assertSame('Chocolate', $chocolate->fresh()->name);
    }

    public function test_menu_names_may_be_swapped_but_not_duplicated(): void
    {
        $vanilla = $this->item('Vanilla');
        $chocolate = $this->item('Chocolate');

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), [
            'rows' => [
                $vanilla->id => $this->menuRow($vanilla, ['name' => 'Chocolate']),
                $chocolate->id => $this->menuRow($chocolate, ['name' => 'Vanilla']),
            ],
        ])->assertOk();

        $this->assertSame('Chocolate', $vanilla->fresh()->name);
        $this->assertSame('Vanilla', $chocolate->fresh()->name);

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), [
            'rows' => [$vanilla->id => $this->menuRow($vanilla->fresh(), ['name' => 'vanilla'])],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$vanilla->id}.name"]);
    }

    public function test_an_ice_cream_flavor_still_needs_a_color(): void
    {
        $vanilla = $this->item('Vanilla');

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), [
            'rows' => [$vanilla->id => $this->menuRow($vanilla, ['hex_color' => null])],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$vanilla->id}.hex_color"]);
    }

    public function test_a_menu_row_changed_by_someone_else_is_refused_and_not_overwritten(): void
    {
        $vanilla = $this->item('Vanilla');
        $row = $this->menuRow($vanilla, ['price' => 15000]);
        $vanilla->update(['price' => 11000]);

        $this->actingAs($this->admin)->postJson(route('staff.menu.bulk'), ['rows' => [$vanilla->id => $row]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["rows.{$vanilla->id}.version"]);

        $this->assertSame('11000.00', $vanilla->fresh()->price);
    }

    // ---- HR -------------------------------------------------------------------------------------------

    public function test_hr_save_all_updates_every_changed_employee(): void
    {
        $first = Employee::factory()->create(['name' => 'Rizky Joestar', 'rotation_order' => 1]);
        $second = Employee::factory()->create(['name' => 'Putri Ayu', 'rotation_order' => 2]);

        $this->actingAs($this->admin)->postJson(route('staff.hr.bulk'), [
            'rows' => [
                $first->id => ['version' => $first->rowVersion(), 'name' => 'Rizky Joestar', 'position' => 'Supervisor', 'phone' => '0812 3456 7890', 'rotation_order' => 3, 'is_active' => true],
                $second->id => ['version' => $second->rowVersion(), 'name' => 'Putri Ayu', 'position' => null, 'phone' => null, 'rotation_order' => 1, 'is_active' => false],
            ],
        ])->assertOk()->assertJsonPath('saved', 2);

        $this->assertSame('Supervisor', $first->fresh()->position);
        $this->assertSame(3, $first->fresh()->rotation_order);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertNull($second->fresh()->position);
    }

    public function test_one_invalid_hr_row_saves_nothing(): void
    {
        $first = Employee::factory()->create(['name' => 'Rizky Joestar', 'position' => 'Crew']);
        $second = Employee::factory()->create(['name' => 'Putri Ayu']);

        $this->actingAs($this->admin)->postJson(route('staff.hr.bulk'), [
            'rows' => [
                $first->id => ['version' => $first->rowVersion(), 'name' => 'Rizky Joestar', 'position' => 'Supervisor', 'phone' => null, 'rotation_order' => 1, 'is_active' => true],
                $second->id => ['version' => $second->rowVersion(), 'name' => 'Putri Ayu', 'position' => null, 'phone' => 'not a phone', 'rotation_order' => 2, 'is_active' => true],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$second->id}.phone"]);

        $this->assertSame('Crew', $first->fresh()->position);
    }

    public function test_an_hr_row_changed_by_someone_else_is_refused(): void
    {
        $employee = Employee::factory()->create(['name' => 'Rizky Joestar']);
        $version = $employee->rowVersion();
        $employee->update(['position' => 'Changed meanwhile']);

        $this->actingAs($this->admin)->postJson(route('staff.hr.bulk'), [
            'rows' => [$employee->id => ['version' => $version, 'name' => 'Rizky Joestar', 'position' => 'Mine', 'phone' => null, 'rotation_order' => 1, 'is_active' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors(["rows.{$employee->id}.version"]);

        $this->assertSame('Changed meanwhile', $employee->fresh()->position);
    }

    // ---- access ---------------------------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function bulkRoutes(): array
    {
        return [
            'stock' => ['staff.mrp.bulk', User::ROLE_HR],
            'menu' => ['staff.menu.bulk', User::ROLE_WAREHOUSE],
            'hr' => ['staff.hr.bulk', User::ROLE_WAREHOUSE],
        ];
    }

    #[DataProvider('bulkRoutes')]
    public function test_a_role_without_the_module_is_refused(string $route, string $role): void
    {
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)->postJson(route($route), ['rows' => [1 => ['version' => 'x']]])->assertForbidden();
    }

    #[DataProvider('bulkRoutes')]
    public function test_a_guest_is_sent_to_sign_in(string $route): void
    {
        $this->postJson(route($route), ['rows' => [1 => ['version' => 'x']]])->assertUnauthorized();
        $this->post(route($route))->assertRedirect();
    }

    public function test_the_pages_carry_the_save_all_bar_and_row_versions(): void
    {
        $vanilla = $this->item('Vanilla');
        Employee::factory()->create();

        $this->actingAs($this->admin)->get(route('staff.mrp.index'))
            ->assertOk()->assertSee('batchForm', false)->assertSee('data-version="'.$vanilla->stockVersion().'"', false)->assertSee('Save all changes');
        $this->actingAs($this->admin)->get(route('staff.menu.index'))
            ->assertOk()->assertSee('batchForm', false)->assertSee('data-version="'.$vanilla->menuVersion().'"', false);
        $this->actingAs($this->admin)->get(route('staff.hr.index'))
            ->assertOk()->assertSee('batchForm', false);
        $this->actingAs($this->admin)->get(route('staff.orders.index'))
            ->assertOk()->assertSee('x-grid-nav.list', false);
    }
}
