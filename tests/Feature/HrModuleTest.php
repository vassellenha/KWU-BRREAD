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

    public function test_nine_employees_rotate_as_three_fixed_teams_every_three_days(): void
    {
        $this->seed(EmployeeSeeder::class);

        $first = ['Rizky Sandi Pratama', 'Vassell Enha Atthayya', 'Evan Octavianus Haryo Handoko'];
        $second = ['Muhammad Jordan', 'Bhisma Abimanyu Putra Setiawan', 'Dhimas Aqillah Syah'];
        $third = ['Herlandito Ibnu Chandra', 'Muhammad Rafa Irhamsyah', 'Rafy Aderahma Putra'];

        $this->assertSame($first, $this->teamOn('2026-11-02'));
        $this->assertSame($first, $this->teamOn('2026-11-04'));
        $this->assertSame($second, $this->teamOn('2026-11-05'));
        $this->assertSame($second, $this->teamOn('2026-11-07'));
        $this->assertSame($third, $this->teamOn('2026-11-08'));
        $this->assertSame($first, $this->teamOn('2026-11-11'));
    }

    public function test_the_seeded_roster_has_rizky_as_leader_and_eight_members(): void
    {
        $this->seed(EmployeeSeeder::class);

        $this->assertSame(9, Employee::count());
        $this->assertSame(['Rizky Sandi Pratama'], Employee::where('position', 'Ketua')->pluck('name')->all());
        $this->assertSame(8, Employee::where('position', 'Anggota')->count());
        $this->assertSame(['Rizky Sandi Pratama', 'Vassell Enha Atthayya'], Employee::orderBy('rotation_order')->limit(2)->pluck('name')->all());
    }

    public function test_dates_before_the_anchor_still_rotate_correctly(): void
    {
        $this->seed(EmployeeSeeder::class);

        $this->assertSame(['Herlandito Ibnu Chandra', 'Muhammad Rafa Irhamsyah', 'Rafy Aderahma Putra'], $this->teamOn('2026-11-01'));
        $this->assertSame(['Muhammad Jordan', 'Bhisma Abimanyu Putra Setiawan', 'Dhimas Aqillah Syah'], $this->teamOn('2026-10-29'));
    }

    public function test_the_placeholder_crew_is_replaced_by_the_real_team_on_deploy(): void
    {
        foreach (['Rizky Joestar', 'Evan Brando', 'Vassell Speedwagon', 'Jordan Yoshikage', 'Chandra Liaow', 'Gofar Hilman'] as $i => $name) {
            Employee::create(['name' => $name, 'position' => 'Store Crew', 'rotation_order' => $i + 1]);
        }
        Employee::create(['name' => 'Added By Hand', 'position' => 'Crew', 'rotation_order' => 7]);

        (require base_path('database/migrations/2026_11_16_110000_set_real_team_roster_in_employees.php'))->up();

        $this->assertSame(10, Employee::count());
        $this->assertNull(Employee::where('name', 'Rizky Joestar')->first());
        $this->assertSame('Ketua', Employee::where('name', 'Rizky Sandi Pratama')->value('position'));
        $this->assertSame(10, Employee::where('name', 'Added By Hand')->value('rotation_order'));
        $this->assertSame(9, Employee::where('name', 'Rafy Aderahma Putra')->value('rotation_order'));

        // Running it again changes nothing.
        (require base_path('database/migrations/2026_11_16_110000_set_real_team_roster_in_employees.php'))->up();
        $this->assertSame(10, Employee::count());
    }

    public function test_a_headcount_that_does_not_divide_evenly_still_fields_a_full_team(): void
    {
        $this->seed(EmployeeSeeder::class);
        Employee::factory()->create(['name' => 'Tenth Person', 'rotation_order' => 10]);

        $shiftsWorked = [];

        foreach (range(0, 9) as $shift) {
            $date = CarbonImmutable::parse(ShiftScheduler::ROTATION_ANCHOR)->addDays($shift * ShiftScheduler::SHIFT_LENGTH_DAYS);
            $team = $this->teamOn($date->toDateString());

            $this->assertCount(ShiftScheduler::GROUP_SIZE, $team);
            $this->assertCount(ShiftScheduler::GROUP_SIZE, array_unique($team));

            foreach ($team as $name) {
                $shiftsWorked[$name] = ($shiftsWorked[$name] ?? 0) + 1;
            }
        }

        $this->assertCount(10, $shiftsWorked);
        $this->assertSame([3], array_values(array_unique($shiftsWorked)));
    }

    public function test_a_deactivated_employee_leaves_the_rotation(): void
    {
        $this->seed(EmployeeSeeder::class);
        $employee = Employee::where('name', 'Evan Octavianus Haryo Handoko')->firstOrFail();

        $this->actingAs($this->staff)->post("/staff/hr/{$employee->id}/toggle")->assertSessionHasNoErrors();

        $this->assertNotContains('Evan Octavianus Haryo Handoko', $this->teamOn('2026-11-02'));
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
            ->assertViewHas('currentShift', fn (array $shift) => $shift['employees']->first()->name === 'Herlandito Ibnu Chandra');
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

        $this->assertSame(9, Employee::count());
    }
}
