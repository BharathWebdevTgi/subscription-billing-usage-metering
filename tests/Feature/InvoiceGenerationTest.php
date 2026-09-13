<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Services\InvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscriptionData(array $planData = []): array
    {
        $merchant = Merchant::factory()->create();

        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $plan = Plan::factory()->create(array_merge([
            'merchant_id' => $merchant->id,
            'base_price' => 999,
            'included_units' => 1000,
            'overage_rate' => 10,
        ], $planData));

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        return compact(
            'merchant',
            'customer',
            'plan',
            'subscription'
        );
    }

    public function test_generates_invoice(): void
    {
        $data = $this->createSubscriptionData();

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        DailyUsage::create([
            'customer_id' => $data['customer']->id,
            'subscription_id' => $data['subscription']->id,
            'usage_date' => '2026-09-10',
            'units' => 500,
        ]);

        $invoice = app(InvoiceGenerationService::class)->generate($period);

        $this->assertInstanceOf(Invoice::class, $invoice);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'subscription_id' => $data['subscription']->id,
            'customer_id' => $data['customer']->id,
            'included_units' => 1000,
            'used_units' => 500,
            'overage_units' => 0,
        ]);
    }

    /*
        Plan price = ₹999
        Period = Sep 10 → Sep 20

        diffInDays = 11

        999 / 30 × 11
        = ₹366.3
    */
    public function test_calculates_prorated_plan_charge(): void
    {
        $data = $this->createSubscriptionData([
            'base_price' => 999,
            'included_units' => 1000,
            'overage_rate' => 10,
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-10 00:00:00',
            'ends_at' => '2026-09-20 00:00:00',
        ]);

        $invoice = app(InvoiceGenerationService::class)->generate($period);

        $this->assertEquals(366.30,(float) $invoice->base_amount);
    }    

    /*
    Included = 1,000
    Usage    = 1,500

    Overage = 500

    500 × ₹10
    = ₹5,000
    */
    public function test_calculates_overage(): void
    {
        $data = $this->createSubscriptionData([
            'base_price' => 999,
            'included_units' => 1000,
            'overage_rate' => 10,
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        DailyUsage::create([
            'customer_id' => $data['customer']->id,
            'subscription_id' => $data['subscription']->id,
            'usage_date' => '2026-09-15',
            'units' => 1500,
        ]);

        $invoice = app(InvoiceGenerationService::class)
            ->generate($period);

        $this->assertEquals(1500, $invoice->used_units);
        $this->assertEquals(1000, $invoice->included_units);
        $this->assertEquals(500, $invoice->overage_units);
        $this->assertEquals(5000, (float) $invoice->overage_amount);
    }    

    /*
    No overage
    */
    public function test_handles_no_overage(): void
    {
        $data = $this->createSubscriptionData([
            'included_units' => 1000,
            'overage_rate' => 10,
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        DailyUsage::create([
            'customer_id' => $data['customer']->id,
            'subscription_id' => $data['subscription']->id,
            'usage_date' => '2026-09-15',
            'units' => 500,
        ]);

        $invoice = app(InvoiceGenerationService::class)
            ->generate($period);

        $this->assertEquals(0, $invoice->overage_units);
        $this->assertEquals(0, (float) $invoice->overage_amount);
    }    

    /*
    Exactly included units
    */
    public function test_handles_exact_included_units(): void
    {
        $data = $this->createSubscriptionData([
            'included_units' => 1000,
            'overage_rate' => 10,
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        DailyUsage::create([
            'customer_id' => $data['customer']->id,
            'subscription_id' => $data['subscription']->id,
            'usage_date' => '2026-09-15',
            'units' => 1000,
        ]);

        $invoice = app(InvoiceGenerationService::class)
            ->generate($period);

        $this->assertEquals(0, $invoice->overage_units);
        $this->assertEquals(0, (float) $invoice->overage_amount);
    }
    
    /*
    Zero usage
    */
    public function test_handles_zero_usage(): void
    {
        $data = $this->createSubscriptionData([
            'included_units' => 1000,
            'overage_rate' => 10,
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        $invoice = app(InvoiceGenerationService::class)
            ->generate($period);

        $this->assertEquals(0, $invoice->used_units);
        $this->assertEquals(0, $invoice->overage_units);
        $this->assertEquals(0, (float) $invoice->overage_amount);
    }    

    /*
    Duplicate invoice
    */
    public function test_prevents_duplicate_invoice(): void
    {
        $data = $this->createSubscriptionData();

        $period = SubscriptionPeriod::create([
            'subscription_id' => $data['subscription']->id,
            'plan_id' => $data['plan']->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
        ]);

        $service = app(InvoiceGenerationService::class);

        $firstInvoice = $service->generate($period);

        $secondInvoice = $service->generate($period);

        $this->assertEquals(
            $firstInvoice->id,
            $secondInvoice->id
        );

        $this->assertEquals(
            1,
            Invoice::where('subscription_id', $data['subscription']->id)
                ->where('period_start', '2026-09-01')
                ->where('period_end', '2026-09-30')
                ->count()
        );
    }
    
}