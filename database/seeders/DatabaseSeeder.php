<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        /*User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);*/

        // Create merchants
        $merchants = Merchant::factory()
            ->count(2)
            ->create();

        foreach ($merchants as $merchant) {

            // Create plans for this merchant
            $plans = collect();

            foreach (['Basic', 'Standard', 'Premium'] as $planName) {

                $plans->push(
                    Plan::updateOrCreate(
                        [
                            'merchant_id' => $merchant->id,
                            'name' => $planName,
                        ],
                        [
                            'base_price' => match ($planName) {
                                'Basic' => 499,
                                'Standard' => 999,
                                'Premium' => 1999,
                            },

                            'billing_cycle' => 'monthly',

                            'included_units' => match ($planName) {
                                'Basic' => 100,
                                'Standard' => 500,
                                'Premium' => 1000,
                            },

                            'overage_rate' => match ($planName) {
                                'Basic' => 15,
                                'Standard' => 10,
                                'Premium' => 5,
                            },
                        ]
                    )
                );
            }

            // Create customers for this merchant
            $customers = Customer::factory()
                ->count(5)
                ->for($merchant)
                ->create();

            // Create subscription + initial subscription period
            foreach ($customers as $customer) {

                /*$plan = $plans->random();

                // Create subscription
                $subscription = Subscription::factory()
                    ->for($customer)
                    ->for($plan)
                    ->create();

                // Create initial subscription period
                SubscriptionPeriod::create([
                    'subscription_id' => $subscription->id,
                    'plan_id'         => $plan->id,
                    'starts_at'       => $subscription->started_at,
                    'ends_at'         => $subscription->started_at->copy()->endOfMonth(),
                ]);*/

                /*
                |--------------------------------------------------------------------------
                | Select a plan
                |--------------------------------------------------------------------------
                */

                $plan = $plans->random();

                /*
                |--------------------------------------------------------------------------
                | Subscription starts in August
                |--------------------------------------------------------------------------
                */

                $subscriptionStart = Carbon::create(2026,8,1,0,0,0);

                /*
                |--------------------------------------------------------------------------
                | Create subscription
                |--------------------------------------------------------------------------
                */

                $subscription = Subscription::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                    ],
                    [
                        'plan_id'    => $plan->id,
                        'status'     => 'active',
                        'started_at' => $subscriptionStart,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | August billing period
                |--------------------------------------------------------------------------
                */

                $augustStart = Carbon::create(2026,8,1,0,0,0);

                $augustEnd = Carbon::create(2026,8,31,23,59,59);

                SubscriptionPeriod::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'starts_at'       => $augustStart,
                    ],
                    [
                        'plan_id' => $plan->id,
                        'ends_at' => $augustEnd,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | September billing period
                |--------------------------------------------------------------------------
                */

                $septemberStart = Carbon::create(2026,9,1,0,0,0);

                $septemberEnd = Carbon::create(2026,9,30,23,59,59);

                SubscriptionPeriod::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'starts_at'       => $septemberStart,
                    ],
                    [
                        'plan_id' => $plan->id,
                        'ends_at' => $septemberEnd,
                    ]
                );                
            }
        }
    }
}
