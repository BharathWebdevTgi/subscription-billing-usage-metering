<?php

namespace App\Services;

use App\Models\SubscriptionPeriod;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceGenerationService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generate(SubscriptionPeriod $period)
    {
        return DB::transaction(function () use ($period) {

            // -------------------------------------------------
            // 1. Get plan
            // -------------------------------------------------

            $plan = $period->plan;

            // -------------------------------------------------
            // 2. Calculate period dates
            // -------------------------------------------------

            $start = Carbon::parse($period->starts_at)->startOfDay();
            $end   = Carbon::parse($period->ends_at)->startOfDay();

            $existingInvoice = Invoice::where('subscription_id', $period->subscription_id)
                ->where('period_start', $start->toDateString())
                ->where('period_end', $end->toDateString())
                ->first();

            if ($existingInvoice) {
                return $existingInvoice;
            }            

            // Number of billable days in this subscription period
            $days = $start->diffInDays($end) + 1;

            // -------------------------------------------------
            // 3. Calculate total usage
            // -------------------------------------------------

            $totalUnits = DailyUsage::where(
                'subscription_id',
                $period->subscription_id
            )
                ->whereBetween('usage_date', [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->sum('units');

            // -------------------------------------------------
            // 4. Included units
            // -------------------------------------------------

            $includedUnits = $plan->included_units;

            // -------------------------------------------------
            // 5. Calculate overage units
            // -------------------------------------------------

            $overageUnits = max(
                0,
                $totalUnits - $includedUnits
            );

            // -------------------------------------------------
            // 6. Calculate overage amount
            // -------------------------------------------------

            $overageAmount = $overageUnits * $plan->overage_rate;

            // -------------------------------------------------
            // 7. Calculate prorated base amount
            // -------------------------------------------------

            /*
             * Monthly plan price is prorated according
             * to the number of days in this period.
             *
             * Example:
             *
             * Plan price = 999
             * Billing period = 30 days
             * Subscription period = 10 days
             *
             * Base amount = 999 / 30 * 10
             */

            $billingDays = 30;

            $baseAmount = ($plan->base_price / $billingDays) * $days;

            // -------------------------------------------------
            // 8. Total amount
            // -------------------------------------------------

            $totalAmount = $baseAmount + $overageAmount;
            
            // -------------------------------------------------
            // 9. Create invoice
            // -------------------------------------------------

            $invoice = Invoice::create([
                'subscription_id' => $period->subscription_id,
                'customer_id' => $period->subscription->customer_id,

                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),

                'base_amount' => round($baseAmount, 2),

                'included_units' => $includedUnits,
                'used_units' => $totalUnits,
                'overage_units' => $overageUnits,

                'overage_amount' => round($overageAmount, 2),

                'total_amount' => round($totalAmount, 2),

                'status' => 'pending',

                'issued_at' => now(),
            ]);

            // -------------------------------------------------
            // 10. Create base charge invoice item
            // -------------------------------------------------

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $plan->name . ' plan',
                'units' => $days,
                'unit_price' => round(
                    $plan->base_price / $billingDays,
                    4
                ),
                'amount' => round($baseAmount, 2),
            ]);

            // -------------------------------------------------
            // 11. Create overage invoice item
            // -------------------------------------------------

            if ($overageUnits > 0) {

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Usage overage',
                    'units' => $overageUnits,
                    'unit_price' => $plan->overage_rate,
                    'amount' => round($overageAmount, 2),
                ]);
            }

            return $invoice;
        });
    }    
}
