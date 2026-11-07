<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'category' => fake()->randomElement(Expense::CATEGORIES),
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'expense_date' => today(),
        ];
    }
}
