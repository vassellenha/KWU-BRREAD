<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'position' => 'Store Crew',
            'phone' => null,
            'rotation_order' => fake()->unique()->numberBetween(100, 9999),
            'is_active' => true,
        ];
    }
}
