<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlanChangeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customer can change their subscription to another
     * plan belonging to the same merchant.
     */
    public function test_changes_plan(): void
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $oldPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Old Plan',
            'base_price' => 1000,
            'billing_cycle' => 'monthly',
            'included_units' => 500,
            'overage_rate' => 5,
        ]);

        $newPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'New Plan',
            'base_price' => 1999,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 5,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $oldPlan->id,
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $oldPlan->id,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $response = $this->postJson(
            "/api/customers/{$customer->id}/subscriptions/{$subscription->id}/change-plan",
            [
                'plan_id' => $newPlan->id,
            ]
        );

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Plan changed successfully.',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $newPlan->id,
        ]);
    }

    /**
     * The previous subscription period should be closed
     * at the time of the plan change.
     */
    public function test_closes_previous_period(): void
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $oldPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Old Plan',
            'base_price' => 1000,
            'billing_cycle' => 'monthly',
            'included_units' => 500,
            'overage_rate' => 5,
        ]);

        $newPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'New Plan',
            'base_price' => 1999,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 5,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $oldPlan->id,
            'status' => 'active',
        ]);

        $oldPeriod = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $oldPlan->id,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $this->postJson(
            "/api/customers/{$customer->id}/subscriptions/{$subscription->id}/change-plan",
            [
                'plan_id' => $newPlan->id,
            ]
        )->assertStatus(200);

        $oldPeriod->refresh();

        $this->assertTrue(
            $oldPeriod->ends_at->equalTo(
                now()->setSeconds(0)
            ) ||
            $oldPeriod->ends_at->lessThanOrEqualTo(now())
        );
    }

    /**
     * A new subscription period should be created
     * using the new plan.
     */
    public function test_creates_new_period(): void
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $oldPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Old Plan',
            'base_price' => 1000,
            'billing_cycle' => 'monthly',
            'included_units' => 500,
            'overage_rate' => 5,
        ]);

        $newPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'New Plan',
            'base_price' => 1999,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 5,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $oldPlan->id,
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $oldPlan->id,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $response = $this->postJson(
            "/api/customers/{$customer->id}/subscriptions/{$subscription->id}/change-plan",
            [
                'plan_id' => $newPlan->id,
            ]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscription_periods', [
            'subscription_id' => $subscription->id,
            'plan_id' => $newPlan->id,
        ]);
    }

    /**
     * A customer cannot change to a plan belonging
     * to another merchant.
     */
    public function test_rejects_plan_from_different_merchant(): void
    {
        $merchant = Merchant::factory()->create();

        $anotherMerchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $currentPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Currrent Plan',
            'base_price' => 1000,
            'billing_cycle' => 'monthly',
            'included_units' => 500,
            'overage_rate' => 5,
        ]);        

        $foreignPlan = Plan::create([
            'merchant_id' => $anotherMerchant->id,
            'name' => 'Another Plan',
            'base_price' => 1999,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 5,
        ]);        

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $currentPlan->id,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $response = $this->postJson(
            "/api/customers/{$customer->id}/subscriptions/{$subscription->id}/change-plan",
            [
                'plan_id' => $foreignPlan->id,
            ]
        );

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Selected plan does not belong to this merchant.',
            ]);

        // Subscription must remain unchanged.
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $currentPlan->id,
        ]);
    }
}