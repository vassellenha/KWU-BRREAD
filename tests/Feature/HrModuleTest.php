<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\ShiftScheduler;
use Carbon\CarbonImmutable;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HrModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->staff = User::factory()->withRole(User::ROLE_HR)->create();
    }

    /**
     * @return list<string>
     */
    private function teamOn(string $date): array
    {
        return (new ShiftScheduler)->teamForDate(CarbonImmutable::parse($date))['employees']->pluck('name')->all();
    }

    public function test_six_employees_alternate_as_two_fixed_teams_every_three_days(): void
    {
        $this->seed(EmployeeSeeder::class);

        $firstTeam = ['Rizky Joestar', 'Evan Brando', 'Vassell Speedwagon'];
        $secondTeam = ['Jordan Yoshikage', 'Chandra Liaow', 'Gofar Hilman'];

        $this->assertSame($firstTeam, $this->teamOn('2026-11-02'));
        $this->assertSame($firstTeam, $this->teamOn('2026-11-04'));
        $this->assertSame($secondTeam, $this->teamOn('2026-11-05'));
        $this->assertSame($secondTeam, $this->teamOn('2026-11-07'));
        $this->assertSame($firstTeam, $this->teamOn('2026-11-08'));
    }

    public function test_dates_before_the_anchor_still_rotate_correctly(): void
    {
        $this->seed(EmployeeSeeder::class);

        $this->assertSame(['Jordan Yoshikage', 'Chandra Liaow', 'Gofar Hilman'], $this->teamOn('2026-11-01'));
        $this->assertSame(['Rizky Joestar', 'Evan Brando', 'Vassell Speedwagon'], $this->teamOn('2026-10-29'));
    }

    public function test_a_headcount_that_does_not_divide_evenly_still_fields_a_full_team(): void
    {
        $this->seed(EmployeeSeeder::class);
        Employee::factory()->create(['name' => 'Seventh Person', 'rotation_order' => 7]);

        $shiftsWorked = [];

        foreach (range(0, 6) as $shift) {
            $date = CarbonImmutable::parse(ShiftScheduler::ROTATION_ANCHOR)->addDays($shift * ShiftScheduler::SHIFT_LENGTH_DAYS);
            $team = $this->teamOn($date->toDateString());

            $this->assertCount(ShiftScheduler::GROUP_SIZE, $team);
            $this->assertCount(ShiftScheduler::GROUP_SIZE, array_unique($team));

            foreach ($team as $name) {
                $shiftsWorked[$name] = ($shiftsWorked[$name] ?? 0) + 1;
            }
        }

        $this->assertCount(7, $shiftsWorked);
        $this->assertSame([3], array_values(array_unique($shiftsWorked)));
    }

    public function test_a_deactivated_employee_leaves_the_rotation(): void
    {
        $this->seed(EmployeeSeeder::class);
        $employee = Employee::where('name', 'Evan Brando')->firstOrFail();

        $this->actingAs($this->staff)->post("/staff/hr/{$employee->id}/toggle")->assertSessionHasNoErrors();

        $this->assertNotContains('Evan Brando', $this->teamOn('2026-11-02'));
        $this->assertCount(ShiftScheduler::GROUP_SIZE, $this->teamOn('2026-11-02'));
    }

    public function test_fewer_employees_than_a_team_all_stay_on_duty(): void
    {
        Employee::factory()->create(['name' => 'Only Person']);

        $this->assertSame(['Only Person'], $this->teamOn('2026-11-02'));
    }

    public function test_the_page_renders_with_no_employees_at_all(): void
    {
        $this->actingAs($this->staff)->get('/staff/hr')
            ->assertOk()
            ->assertSee('No active employees to schedule yet')
            ->assertSee('No employees yet');
    }

    public function test_the_page_shows_todays_team_using_the_jakarta_calendar(): void
    {
        $this->seed(EmployeeSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-07 18:00:00', 'UTC'));

        $this->actingAs($this->staff)->get('/staff/hr')
            ->assertOk()
            ->assertSee('08 Nov – 10 Nov 2026')
            ->assertViewHas('currentShift', fn (array $shift) => $shift['employees']->first()->name === 'Rizky Joestar');
    }

    public function test_employees_can_be_added_and_edited(): void
    {
        $this->actingAs($this->staff)
            ->post('/staff/hr', ['name' => 'Rizky Joestar', 'position' => 'Crew', 'phone' => '+62 812 3456 7890'])
            ->assertSessionHasNoErrors();

        $employee = Employee::where('name', 'Rizky Joestar')->firstOrFail();
        $this->assertSame(1, $employee->rotation_order);

        $this->actingAs($this->staff)
            ->put("/staff/hr/{$employee->id}", ['name' => "Rizky O'Neil", 'position' => '', 'phone' => '', 'rotation_order' => 4])
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame("Rizky O'Neil", $employee->name);
        $this->assertSame(4, $employee->rotation_order);
        $this->assertNull($employee->phone);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidEmployees(): array
    {
        return [
            'digits in name' => [['name' => '12345'], 'name'],
            'letters in phone' => [['name' => 'Budi', 'phone' => 'abcdefgh'], 'phone'],
        ];
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidEmployees')]
    public function test_employee_details_are_validated(array $input, string $invalidField): void
    {
        $this->actingAs($this->staff)
            ->post('/staff/hr', $input)
            ->assertSessionHasErrors($invalidField);

        $this->assertSame(0, Employee::count());
    }

    public function test_seeding_twice_does_not_duplicate_employees(): void
    {
        $this->seed(EmployeeSeeder::class);
        $this->seed(EmployeeSeeder::class);

        $this->assertSame(6, Employee::count());
    }
}
