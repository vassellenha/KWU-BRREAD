<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An order row only. Items come from the checkout API in the feature tests, or are added by the test itself.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'BRREAD-'.strtoupper(Str::random(8)),
            'total_price' => 14000,
            'status' => Order::STATUS_PENDING,
            'customer_name' => fake()->firstName().' '.fake()->lastName(),
            'customer_phone' => '0812'.fake()->numerify('########'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_CONFIRMED,
            'confirmation_token' => Str::random(32),
            'confirmed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->confirmed()->state(fn () => ['status' => Order::STATUS_PAID, 'paid_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }
}
