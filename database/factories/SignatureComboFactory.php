<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\SignatureCombo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the starter menu (ComponentSeeder) to be in place: a combo only points at existing menu items.
 *
 * @extends Factory<SignatureCombo>
 */
class SignatureComboFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'bread_id' => Component::where('category', 'bread')->value('id'),
            'flavor_id' => Component::where('category', 'ice_cream')->value('id'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
