<?php

namespace Tests\Feature;

use App\Jobs\AggregateUsageJob;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\DailyUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UsageEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_new_usage_event(): void
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $plan = Plan::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $usageEvent = UsageEvent::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 500,
            'idempotency_key' => 'test-usage-001',
        ]);

        $this->assertDatabaseHas('usage_events', [
            'id' => $usageEvent->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 500,
        ]);
    }

    public function test_aggregates_usage_events(): void
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $plan = Plan::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        UsageEvent::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 100,
            'idempotency_key' => 'test-usage-001',
        ]);

        UsageEvent::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 200,
            'idempotency_key' => 'test-usage-002',
        ]);

        UsageEvent::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 150,
            'idempotency_key' => 'test-usage-003',
        ]);

        AggregateUsageJob::dispatchSync('2026-09-10');

        $this->assertDatabaseHas('daily_usage', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 450,
        ]);
    }
}