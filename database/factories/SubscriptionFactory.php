<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),

            'plan_id' => Plan::factory(),

            'status' => 'active',

            'started_at' => now(),

            'canceled_at' => null,
        ];
    }
}