<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Runs on every deploy, so it only fills an empty table: removed expenses stay removed
 * and past dates stay put.
 */
class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        if (Expense::exists()) {
            return;
        }

        $accountant = User::where('role', User::ROLE_ACCOUNTANT)->first();

        $expenses = [
            ['description' => 'Weekly bread flour restock', 'category' => 'Ingredients', 'amount' => 850000, 'days_ago' => 2],
            ['description' => 'Ice cream base supplier invoice', 'category' => 'Ingredients', 'amount' => 1200000, 'days_ago' => 4],
            ['description' => 'PLN electricity bill', 'category' => 'Utilities', 'amount' => 650000, 'days_ago' => 6],
            ['description' => 'Staff salaries — this period', 'category' => 'Salaries', 'amount' => 4500000, 'days_ago' => 7],
            ['description' => 'Cold-chain freezer maintenance', 'category' => 'Equipment', 'amount' => 300000, 'days_ago' => 10],
            ['description' => 'Toppings & sprinkles restock', 'category' => 'Ingredients', 'amount' => 420000, 'days_ago' => 12],
            ['description' => 'Store rent — monthly', 'category' => 'Rent', 'amount' => 3000000, 'days_ago' => 15],
            ['description' => 'Packaging & insulated foil', 'category' => 'Equipment', 'amount' => 275000, 'days_ago' => 18],
        ];

        foreach ($expenses as $expense) {
            Expense::updateOrCreate(
                ['description' => $expense['description']],
                [
                    'category' => $expense['category'],
                    'amount' => $expense['amount'],
                    'expense_date' => today()->subDays($expense['days_ago']),
                    'user_id' => $accountant?->id,
                ]
            );
        }
    }
}
