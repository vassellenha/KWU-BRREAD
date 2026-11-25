<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRevision>
 */
class OrderRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creation = ['creation' => 1, 'quantity' => 1, 'bread' => 'Plain White Bread', 'flavor' => 'Vanilla', 'toppings' => []];

        return [
            'order_id' => Order::factory(),
            'user_id' => null,
            'reason' => fake()->sentence(),
            'before' => [$creation],
            'after' => [array_merge($creation, ['flavor' => 'Matcha'])],
            'total_before' => 14000,
            'total_after' => 14000,
        ];
    }
}
