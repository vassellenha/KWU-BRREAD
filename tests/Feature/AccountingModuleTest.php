<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ExpenseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->staff = User::factory()->withRole(User::ROLE_ACCOUNTANT)->create();
        $this->travelTo('2026-11-20 10:00:00');
    }

    private function orderOn(string $date, int $total): Order
    {
        $order = Order::create([
            'order_number' => 'BRREAD-'.fake()->unique()->numerify('####'),
            'total_price' => $total,
            'status' => 'pending',
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
        ]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    public function test_the_report_totals_the_period_and_carries_the_opening_balance(): void
    {
        $this->orderOn('2026-10-15 12:00:00', 100000);
        Expense::factory()->create(['amount' => 30000, 'expense_date' => '2026-10-20']);
        $this->orderOn('2026-11-05 12:00:00', 50000);
        Expense::factory()->create(['amount' => 20000, 'category' => 'Rent', 'expense_date' => '2026-11-06']);
        Expense::factory()->create(['amount' => 5000, 'category' => 'Utilities', 'expense_date' => '2026-11-20']);

        $response = $this->actingAs($this->staff)
            ->get('/staff/accounting?from=2026-11-01&to=2026-11-20')
            ->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(70000.0, $stats['opening_balance']);
        $this->assertSame(50000.0, $stats['total_income']);
        $this->assertSame(25000.0, $stats['total_expenses']);
        $this->assertSame(25000.0, $stats['net']);
        $this->assertSame(95000.0, $stats['closing_balance']);
        $this->assertSame(3, $stats['transaction_count']);
        $this->assertSame(['Rent' => 20000.0, 'Utilities' => 5000.0], $stats['expenses_by_category']->all());

        $ledger = $response->viewData('ledger');
        $this->assertSame(95000.0, $ledger->first()['balance_after']);
        $this->assertSame(120000.0, $ledger->last()['balance_after']);
    }

    public function test_an_expense_on_the_last_day_of_the_range_is_included(): void
    {
        Expense::factory()->create(['description' => 'Last day', 'expense_date' => '2026-11-10']);

        $this->actingAs($this->staff)
            ->get('/staff/accounting?from=2026-11-01&to=2026-11-10')
            ->assertSee('Last day');
    }

    public function test_a_reversed_date_range_is_swapped_instead_of_showing_nothing(): void
    {
        Expense::factory()->create(['description' => 'In range', 'expense_date' => '2026-11-05']);

        $this->actingAs($this->staff)
            ->get('/staff/accounting?from=2026-11-10&to=2026-11-01')
            ->assertSee('In range');
    }

    public function test_an_invalid_date_is_reported_back(): void
    {
        $this->actingAs($this->staff)
            ->from('/staff/accounting')
            ->get('/staff/accounting?from=not-a-date')
            ->assertRedirect('/staff/accounting')
            ->assertSessionHasErrors('from');
    }

    public function test_an_expense_can_be_recorded_and_removed(): void
    {
        $this->actingAs($this->staff)
            ->post('/staff/accounting/expenses', [
                'description' => 'Flour',
                'category' => 'Ingredients',
                'amount' => '85000',
                'expense_date' => '2026-11-19',
            ])
            ->assertSessionHasNoErrors();

        $expense = Expense::where('description', 'Flour')->firstOrFail();
        $this->assertSame($this->staff->id, $expense->user_id);

        $this->actingAs($this->staff)->delete("/staff/accounting/expenses/{$expense->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, Expense::count());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidExpenses(): array
    {
        return [
            'letters in amount' => [['amount' => 'abc'], 'amount'],
            'zero amount' => [['amount' => '0'], 'amount'],
            'unknown category' => [['category' => 'Bribes'], 'category'],
            'future date' => [['expense_date' => '2026-11-21'], 'expense_date'],
            'blank description' => [['description' => ''], 'description'],
        ];
    }

    /**
     * @param  array<string, string>  $override
     */
    #[DataProvider('invalidExpenses')]
    public function test_expenses_are_validated(array $override, string $invalidField): void
    {
        $this->actingAs($this->staff)
            ->post('/staff/accounting/expenses', $override + [
                'description' => 'Flour',
                'category' => 'Ingredients',
                'amount' => '85000',
                'expense_date' => '2026-11-19',
            ])
            ->assertSessionHasErrors($invalidField);

        $this->assertSame(0, Expense::count());
    }

    private function downloadSpreadsheet(string $url): Spreadsheet
    {
        $response = $this->actingAs($this->staff)->get($url)->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        return tap(IOFactory::load($path), fn () => unlink($path));
    }

    public function test_the_excel_export_matches_the_report(): void
    {
        $this->orderOn('2026-11-05 12:00:00', 50000);
        Expense::factory()->create(['description' => 'Flour', 'category' => 'Ingredients', 'amount' => 20000, 'expense_date' => '2026-11-06']);

        $spreadsheet = $this->downloadSpreadsheet('/staff/accounting/export?from=2026-11-01&to=2026-11-20');

        $this->assertSame(['Summary', 'Cashflow'], $spreadsheet->getSheetNames());

        $summary = $spreadsheet->getSheetByName('Summary');
        $this->assertSame('Opening Balance', $summary->getCell('A4')->getValue());
        $this->assertEquals(50000, $summary->getCell('B5')->getValue());
        $this->assertEquals(20000, $summary->getCell('B6')->getValue());
        $this->assertEquals(30000, $summary->getCell('B7')->getValue());
        $this->assertEquals(30000, $summary->getCell('B8')->getValue());
        $this->assertSame('Ingredients', $summary->getCell('A12')->getValue());

        $cashflow = $spreadsheet->getSheetByName('Cashflow');
        $this->assertSame('Flour', $cashflow->getCell('C2')->getValue());
        $this->assertEquals(-20000, $cashflow->getCell('E2')->getValue());
        $this->assertEquals(50000, $cashflow->getCell('E3')->getValue());
    }

    public function test_a_description_that_looks_like_a_formula_is_exported_as_plain_text(): void
    {
        Expense::factory()->create(['description' => '=HYPERLINK("http://evil.test","click")', 'expense_date' => '2026-11-06']);

        $cell = $this->downloadSpreadsheet('/staff/accounting/export?from=2026-11-01&to=2026-11-20')
            ->getSheetByName('Cashflow')
            ->getCell('C2');

        $this->assertFalse($cell->isFormula());
        $this->assertSame('=HYPERLINK("http://evil.test","click")', $cell->getValue());
    }

    public function test_the_export_works_for_an_empty_period(): void
    {
        $spreadsheet = $this->downloadSpreadsheet('/staff/accounting/export?from=2020-01-01&to=2020-01-31');

        $this->assertSame('Date', $spreadsheet->getSheetByName('Cashflow')->getCell('A1')->getValue());
    }

    public function test_seeding_twice_does_not_duplicate_expenses(): void
    {
        $this->seed(ExpenseSeeder::class);
        $count = Expense::count();

        $this->travel(3)->days();
        $this->seed(ExpenseSeeder::class);

        $this->assertSame($count, Expense::count());
    }
}
