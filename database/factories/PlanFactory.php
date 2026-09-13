<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),

            'name' => fake()->randomElement([
                'Basic',
                'Standard',
                'Premium',
            ]),

            'base_price' => fake()->randomElement([
                499.00,
                999.00,
                1999.00,
            ]),

            'billing_cycle' => 'monthly',

            'included_units' => fake()->randomElement([
                100,
                500,
                1000,
                5000,
            ]),

            'overage_rate' => fake()->randomElement([
                10.0000,
                8.5000,
                5.0000,
                2.5000,
            ]),
        ];
    }
}