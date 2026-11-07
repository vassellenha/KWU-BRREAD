<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const MODULE_PAGES = [
        User::MODULE_MRP => ['/staff/mrp', '/staff/mrp/movements'],
        User::MODULE_HR => ['/staff/hr'],
        User::MODULE_ACCOUNTING => ['/staff/accounting', '/staff/accounting/export'],
    ];

    private const ALLOWED_MODULES = [
        User::ROLE_ADMIN => [User::MODULE_MRP, User::MODULE_HR, User::MODULE_ACCOUNTING],
        User::ROLE_WAREHOUSE => [User::MODULE_MRP],
        User::ROLE_HR => [User::MODULE_HR],
        User::ROLE_ACCOUNTANT => [User::MODULE_ACCOUNTING],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'mrp staff' => [User::ROLE_WAREHOUSE],
            'hr staff' => [User::ROLE_HR],
            'accounting staff' => [User::ROLE_ACCOUNTANT],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_opens_only_its_own_module_pages(string $role): void
    {
        $this->seed(ComponentSeeder::class);
        $user = User::factory()->withRole($role)->create();

        foreach (self::MODULE_PAGES as $module => $pages) {
            $expectedStatus = in_array($module, self::ALLOWED_MODULES[$role], true) ? 200 : 403;

            foreach ($pages as $page) {
                $this->actingAs($user)->get($page)->assertStatus($expectedStatus);
            }
        }
    }

    #[DataProvider('roles')]
    public function test_the_sidebar_lists_only_the_modules_the_role_can_open(string $role): void
    {
        $this->seed(ComponentSeeder::class);
        $user = User::factory()->withRole($role)->create();
        $allowed = self::ALLOWED_MODULES[$role];

        $response = $this->actingAs($user)->get(self::MODULE_PAGES[$allowed[0]][0])->assertOk();

        $links = [
            User::MODULE_MRP => route('staff.mrp.index'),
            User::MODULE_HR => route('staff.hr.index'),
            User::MODULE_ACCOUNTING => route('staff.accounting.index'),
        ];

        foreach ($links as $module => $href) {
            in_array($module, $allowed, true)
                ? $response->assertSee('href="'.$href.'"', false)
                : $response->assertDontSee('href="'.$href.'"', false);
        }
    }

    public function test_mrp_staff_cannot_change_hr_or_accounting_data(): void
    {
        $user = User::factory()->withRole(User::ROLE_WAREHOUSE)->create();
        $employee = Employee::factory()->create();
        $expense = Expense::factory()->create();

        $this->actingAs($user)->post('/staff/hr', ['name' => 'Intruder'])->assertForbidden();
        $this->actingAs($user)->put("/staff/hr/{$employee->id}", ['name' => 'Intruder', 'rotation_order' => 1])->assertForbidden();
        $this->actingAs($user)->post("/staff/hr/{$employee->id}/toggle")->assertForbidden();
        $this->actingAs($user)->post('/staff/accounting/expenses', ['description' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete("/staff/accounting/expenses/{$expense->id}")->assertForbidden();

        $this->assertDatabaseMissing('employees', ['name' => 'Intruder']);
        $this->assertTrue($employee->fresh()->is_active);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_hr_and_accounting_staff_cannot_adjust_stock(): void
    {
        $this->seed(ComponentSeeder::class);
        $component = Component::first();
        $stockBefore = $component->stock_quantity;

        foreach ([User::ROLE_HR, User::ROLE_ACCOUNTANT] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->post("/staff/mrp/{$component->id}/adjust", ['direction' => 'in', 'quantity' => 5])
                ->assertForbidden();
        }

        $this->assertSame($stockBefore, $component->fresh()->stock_quantity);
    }

    public function test_a_forbidden_page_offers_a_way_back_to_the_users_module(): void
    {
        $user = User::factory()->withRole(User::ROLE_HR)->create();

        $this->actingAs($user)->get('/staff/mrp')
            ->assertForbidden()
            ->assertSee('Back to my module')
            ->assertSee(route('staff.hr.index'), false);
    }
}
