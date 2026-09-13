<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\DailyUsage;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MerchantDashboardService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function getCurrentCycleUsage($merchant): array
    {
        /*
        |--------------------------------------------------------------------------
        | Current Billing Cycle
        |--------------------------------------------------------------------------
        |
        | Example:
        | Today = 2026-09-11
        |
        | Cycle:
        | 2026-09-01 00:00:00
        | →
        | 2026-09-30 23:59:59
        |
        */

        $cycleStart = Carbon::now()->startOfMonth();
        $cycleEnd   = Carbon::now()->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Cache dashboard usage
        |--------------------------------------------------------------------------
        |
        | Usage can change frequently, but we don't need to execute the
        | aggregation query for every dashboard request.
        |
        | 5 minutes is reasonable for dashboard data.
        |
        */

        /*return Cache::remember(
            "merchant:{$merchant->id}:current-cycle-usage:" . $cycleStart->toDateString(),
            now()->addMinutes(5),
            function () use ($merchant, $cycleStart, $cycleEnd) {*/

                /*
                |--------------------------------------------------------------------------
                | Step 1: Get customer IDs belonging to this merchant
                |--------------------------------------------------------------------------
                */

                $customerIds = $merchant->customers()->pluck('id');

                if ($customerIds->isEmpty()) {
                    return [
                        'used_units'     => 0,
                        'included_units' => 0,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Step 2: Get all ACTIVE subscriptions for those customers
                |--------------------------------------------------------------------------
                */

                $subscriptionIds = Subscription::query()
                    ->whereIn('customer_id', $customerIds)
                    ->where('status', 'active')
                    ->pluck('id');

                if ($subscriptionIds->isEmpty()) {
                    return [
                        'used_units'     => 0,
                        'included_units' => 0,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Step 3: Get subscription periods belonging to the current
                | billing cycle.
                |--------------------------------------------------------------------------
                |
                | We don't load the complete models into PHP.
                |
                | Only the columns required for calculation are selected.
                |
                */

                $periods = SubscriptionPeriod::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->where('starts_at', '>=', $cycleStart)
                    ->where('ends_at', '<=', $cycleEnd)
                    ->get([
                        'id',
                        'subscription_id',
                        'plan_id',
                        'starts_at',
                        'ends_at',
                    ]);

                if ($periods->isEmpty()) {
                    return [
                        'used_units'     => 0,
                        'included_units' => 0,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Step 4: Calculate total included units
                |--------------------------------------------------------------------------
                |
                | included_units comes from plans table.
                |
                | We use ONE database query instead of loading plans
                | individually.
                |--------------------------------------------------------------------------
                */

                $totalIncludedUnits = \App\Models\Plan::query()
                    ->whereIn('id', $periods->pluck('plan_id')->unique())
                    ->sum('included_units');

                /*
                |--------------------------------------------------------------------------
                | Step 5: Calculate total usage
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | We do NOT execute one DailyUsage query inside the loop.
                |
                | Instead, one database query calculates usage for ALL
                | subscription periods.
                |--------------------------------------------------------------------------
                */

                $totalUsedUnits = 0;

                foreach ($periods as $period) {

                    $usedUnits = DailyUsage::query()
                        ->where('subscription_id', $period->subscription_id)
                        ->whereBetween('usage_date', [
                            Carbon::parse($period->starts_at)->toDateString(),
                            Carbon::parse($period->ends_at)->toDateString(),
                        ])
                        ->sum('units');

                    $totalUsedUnits += $usedUnits;
                }

                /*
                |--------------------------------------------------------------------------
                | Return dashboard values
                |--------------------------------------------------------------------------
                */

                return [
                    'used_units'     => (int) $totalUsedUnits,
                    'included_units' => (int) $totalIncludedUnits,
                ];
            //}
        //);
    }    

    public function getProjectedOverageRevenue($merchant): float
    {
        $cycleStart = Carbon::now()->startOfMonth();
        $cycleEnd   = Carbon::now()->endOfMonth();

        /*return Cache::remember(
            "merchant:{$merchant->id}:projected-overage:" . $cycleStart->toDateString(),
            now()->addMinutes(5),
            function () use ($merchant, $cycleStart, $cycleEnd) {*/

                /*
                |--------------------------------------------------------------------------
                | 1. Get all customers belonging to this merchant
                |--------------------------------------------------------------------------
                */

                $customerIds = $merchant->customers()
                    ->pluck('id');

                if ($customerIds->isEmpty()) {
                    return 0.0;
                }

                /*
                |--------------------------------------------------------------------------
                | 2. Get all active subscriptions
                |--------------------------------------------------------------------------
                */

                $subscriptionIds = Subscription::query()
                    ->whereIn('customer_id', $customerIds)
                    ->where('status', 'active')
                    ->pluck('id');

                if ($subscriptionIds->isEmpty()) {
                    return 0.0;
                }

                /*
                |--------------------------------------------------------------------------
                | 3. Get subscription periods belonging to current billing cycle
                |--------------------------------------------------------------------------
                */

                $periods = SubscriptionPeriod::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->where('starts_at', '<=', $cycleEnd)
                    ->where('ends_at', '>=', $cycleStart)
                    ->get([
                        'id',
                        'subscription_id',
                        'plan_id',
                        'starts_at',
                        'ends_at',
                    ]);

                if ($periods->isEmpty()) {
                    return 0.0;
                }
                //print_r($periods->pluck('plan_id')->unique()); exit();
                /*
                |--------------------------------------------------------------------------
                | 4. Load required plan information in one query
                |--------------------------------------------------------------------------
                */

                $plans = Plan::query()
                    ->whereIn(
                        'id',
                        $periods->pluck('plan_id')->unique()
                    )
                    ->get([
                        'id',
                        'included_units',
                        'overage_rate',
                    ])
                    ->keyBy('id');

                /*
                |--------------------------------------------------------------------------
                | 5. Get usage for ALL subscriptions in one query
                |--------------------------------------------------------------------------
                |
                | We do not query DailyUsage inside the foreach loop.
                |
                | This is important for the 50L+ scalability requirement.
                |--------------------------------------------------------------------------
                */
                
                $usageBySubscription = DailyUsage::query()
                    ->whereIn(
                        'subscription_id',
                        $periods->pluck('subscription_id')->unique()
                    )
                    ->whereBetween('usage_date', [
                        $cycleStart->toDateString(),
                        $cycleEnd->toDateString(),
                        //Carbon::today()->toDateString(),
                    ])
                    ->select(
                        'subscription_id',
                        DB::raw('SUM(units) as total_units')
                    )
                    ->groupBy('subscription_id')
                    ->pluck('total_units', 'subscription_id');

                /*
                |--------------------------------------------------------------------------
                | 6. Calculate projected overage revenue
                |--------------------------------------------------------------------------
                */
                
                $projectedRevenue = 0.0;

                $today = Carbon::today();

                foreach ($periods as $period) {

                    $plan = $plans->get($period->plan_id);

                    if (!$plan) {
                        continue;
                    }

                    $periodStart = Carbon::parse($period->starts_at);
                    $periodEnd   = Carbon::parse($period->ends_at);

                    /*
                    |--------------------------------------------------------------------------
                    | Ignore periods that have not started yet
                    |--------------------------------------------------------------------------
                    */

                    if ($periodStart->isFuture()) {
                        continue;
                    }

                    /*
                    * ---------------------------------------------------------
                    * Usage can only be considered up to today.
                    *
                    * If the period already ended:
                    *     usageEnd = periodEnd
                    *
                    * If the period is still active:
                    *     usageEnd = today
                    * ---------------------------------------------------------
                    */

                    $usageEnd = $periodEnd->lt($today)
                        ? $periodEnd
                        : $today;

                    /*
                    * ---------------------------------------------------------
                    * Calculate elapsed billable days.
                    *
                    * Example:
                    *
                    * Sep 11 → Sep 11
                    * = 1 day
                    * ---------------------------------------------------------
                    */

                    $elapsedDays = $periodStart
                        ->startOfDay()
                        ->diffInDays($usageEnd->startOfDay()) + 1;

                    /*
                    * ---------------------------------------------------------
                    * Calculate total billable days.
                    *
                    * Example:
                    *
                    * Sep 11 → Sep 30
                    * = 20 days
                    * ---------------------------------------------------------
                    */

                    $totalDays = $periodStart
                        ->startOfDay()
                        ->diffInDays($periodEnd->startOfDay()) + 1;

                    if ($elapsedDays <= 0 || $totalDays <= 0) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Actual usage so far
                    |--------------------------------------------------------------------------
                    */

                    $usedUnits = (float) (
                        $usageBySubscription[$period->subscription_id] ?? 0
                    );

                    /*
                    * ---------------------------------------------------------
                    * Project usage for the complete subscription period.
                    *
                    * Formula:
                    *
                    * Average daily usage
                    *         ×
                    * Total period days
                    *
                    * Example:
                    *
                    * 650 / 1 × 20
                    * = 13,000 projected units
                    * ---------------------------------------------------------
                    */

                    $projectedUnits =
                        ($usedUnits / $elapsedDays) * $totalDays;

                    /*
                    * ---------------------------------------------------------
                    * Calculate projected overage units.
                    *
                    * Example:
                    *
                    * Projected usage = 13,000
                    * Included       = 500
                    *
                    * Overage = 12,500
                    * ---------------------------------------------------------
                    */

                    $projectedOverageUnits = max(
                        0,
                        $projectedUnits - $plan->included_units
                    );

                    /*
                    * ---------------------------------------------------------
                    * Calculate projected revenue.
                    *
                    * Example:
                    *
                    * 12,500 × ₹10
                    * = ₹125,000
                    * ---------------------------------------------------------
                    */
                    
                    $projectedRevenue +=
                        $projectedOverageUnits * $plan->overage_rate;

                    
                                           
                }

                return round($projectedRevenue, 2);
            //}
        //);
    }


    public function getTopCustomersByUsage($merchant): array
    {
        $cycleStart = Carbon::now()->startOfMonth();
        $cycleEnd   = Carbon::now()->endOfMonth();

        /*return Cache::remember(
            "merchant:{$merchant->id}:top-customers-usage:{$cycleStart->toDateString()}",
            now()->addMinutes(5),
            function () use ($merchant, $cycleStart, $cycleEnd) {*/

                /*
                * -------------------------------------------------------------
                * 1. Get all customer IDs belonging to this merchant
                * -------------------------------------------------------------
                */
                $customerIds = $merchant->customers()
                    ->pluck('id');

                if ($customerIds->isEmpty()) {
                    return [];
                }

                /*
                * -------------------------------------------------------------
                * 2. Get active subscriptions for these customers
                * -------------------------------------------------------------
                */
                $subscriptions = Subscription::query()
                    ->whereIn('customer_id', $customerIds)
                    ->where('status', 'active')
                    ->get([
                        'id',
                        'customer_id',
                    ]);

                if ($subscriptions->isEmpty()) {
                    return [];
                }

                $subscriptionIds = $subscriptions->pluck('id');

                /*
                * -------------------------------------------------------------
                * 3. Get subscription periods belonging to current cycle
                *
                * Example:
                *
                * Current cycle:
                * Sep 01 → Sep 30
                *
                * Period:
                * Sep 11 → Sep 30
                *
                * This period is included.
                * -------------------------------------------------------------
                */
                $periods = SubscriptionPeriod::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->where('starts_at', '<=', $cycleEnd)
                    ->where('ends_at', '>=', $cycleStart)
                    ->get([
                        'subscription_id',
                        'plan_id',
                    ]);

                if ($periods->isEmpty()) {
                    return [];
                }
                
                /*
                * -------------------------------------------------------------
                * 4. Get plan included units in one query
                * -------------------------------------------------------------
                */
                $plans = Plan::query()
                    ->whereIn(
                        'id',
                        $periods->pluck('plan_id')->unique()
                    )
                    ->get([
                        'id',
                        'included_units',
                    ])
                    ->keyBy('id');

                /*
                * -------------------------------------------------------------
                * 5. Calculate allowance for each customer
                *
                * subscription_id
                *       ↓
                * customer_id
                *
                * Then:
                *
                * customer allowance =
                * SUM(plan.included_units)
                * -------------------------------------------------------------
                */
                $customerAllowances = [];

                foreach ($periods as $period) {

                    $subscription = $subscriptions->firstWhere(
                        'id',
                        $period->subscription_id
                    );

                    if (!$subscription) {
                        continue;
                    }

                    $plan = $plans->get($period->plan_id);

                    if (!$plan) {
                        continue;
                    }

                    $customerId = $subscription->customer_id;

                    if (!isset($customerAllowances[$customerId])) {
                        $customerAllowances[$customerId] = 0;
                    }

                    $customerAllowances[$customerId] +=
                        (float) $plan->included_units;
                }

                /*
                * -------------------------------------------------------------
                * 6. Get usage for all customers in ONE query
                *
                * IMPORTANT:
                *
                * Do not execute a DailyUsage query inside a foreach loop.
                *
                * This single query is much better for large datasets.
                * -------------------------------------------------------------
                */
                $usageByCustomer = DailyUsage::query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereBetween('usage_date', [
                        $cycleStart->toDateString(),
                        //Carbon::today()->toDateString(),
                        $cycleEnd->toDateString(),
                    ])
                    ->selectRaw(
                        'customer_id, SUM(units) as total_units'
                    )
                    ->groupBy('customer_id')
                    ->orderByDesc('total_units')
                    ->limit(5)
                    ->get();

                if ($usageByCustomer->isEmpty()) {
                    return [];
                }

                /*
                * -------------------------------------------------------------
                * 7. Load customer names in one query
                * -------------------------------------------------------------
                */
                $topCustomerIds = $usageByCustomer
                    ->pluck('customer_id');

                $customers = $merchant->customers()
                    ->whereIn('id', $topCustomerIds)
                    ->get([
                        'id',
                        'name',
                    ])
                    ->keyBy('id');

                /*
                * -------------------------------------------------------------
                * 8. Build dashboard response
                * -------------------------------------------------------------
                */
                return $usageByCustomer
                    ->map(function ($usage) use (
                        $customers,
                        $customerAllowances
                    ) {

                        $customer = $customers->get($usage->customer_id);

                        if (!$customer) {
                            return null;
                        }

                        $usedUnits = (float) $usage->total_units;

                        $includedUnits = (float) (
                            $customerAllowances[$usage->customer_id] ?? 0
                        );

                        /*
                        * Calculate % of allowance.
                        *
                        * Example:
                        *
                        * Usage     = 450
                        * Allowance = 500
                        *
                        * 450 / 500 × 100 = 90%
                        */
                        $percentage = $includedUnits > 0
                            ? ($usedUnits / $includedUnits) * 100
                            : 0;

                        return [
                            'customer_id'    => $customer->id,
                            'customer'       => $customer->name,
                            'usage'          => $usedUnits,
                            'included_units' => $includedUnits,
                            'percentage'     => round($percentage),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->toArray();
            //}
        //);
    }    

    public function getChurnRisk($merchant): array
    {
        /*
        |--------------------------------------------------------------------------
        | Current billing cycle
        |--------------------------------------------------------------------------
        |
        | Example:
        | Today = 2026-09-11
        |
        | Current cycle:
        | 2026-09-01 → 2026-09-30
        |
        */

        $currentCycleStart = Carbon::now()->startOfMonth();
        $currentCycleEnd   = Carbon::now()->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Previous billing cycle
        |--------------------------------------------------------------------------
        |
        | Previous cycle:
        | 2026-08-01 → 2026-08-31
        |
        */

        $previousCycleStart = $currentCycleStart->copy()->subMonth()->startOfMonth();
        $previousCycleEnd   = $currentCycleStart->copy()->subMonth()->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Cache
        |--------------------------------------------------------------------------
        */

        /*return Cache::remember(
            "merchant:{$merchant->id}:churn-risk:" . $currentCycleStart->toDateString(),
            now()->addMinutes(5),
            function () use (
                $merchant,
                $currentCycleStart,
                $currentCycleEnd,
                $previousCycleStart,
                $previousCycleEnd
            ) {*/

                /*
                |--------------------------------------------------------------------------
                | 1. Get customers belonging to merchant
                |--------------------------------------------------------------------------
                */

                $customers = $merchant->customers()
                    ->get(['id', 'name']);

                if ($customers->isEmpty()) {
                    return [];
                }

                $customerIds = $customers->pluck('id');

                /*
                |--------------------------------------------------------------------------
                | 2. Aggregate usage for both cycles in ONE query
                |--------------------------------------------------------------------------
                |
                | Instead of:
                |
                |   Customer 1 -> query daily_usage
                |   Customer 2 -> query daily_usage
                |   Customer 3 -> query daily_usage
                |   ...
                |
                | We perform one GROUP BY query.
                |
                */

                $usage = DailyUsage::query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereBetween('usage_date', [
                        $previousCycleStart->toDateString(),
                        $currentCycleEnd->toDateString(),
                    ])
                    ->select('customer_id')
                    ->selectRaw(
                        'SUM(
                            CASE
                                WHEN usage_date BETWEEN ? AND ?
                                THEN units
                                ELSE 0
                            END
                        ) AS current_usage',
                        [
                            $currentCycleStart->toDateString(),
                            $currentCycleEnd->toDateString(),
                        ]
                    )
                    ->selectRaw(
                        'SUM(
                            CASE
                                WHEN usage_date BETWEEN ? AND ?
                                THEN units
                                ELSE 0
                            END
                        ) AS previous_usage',
                        [
                            $previousCycleStart->toDateString(),
                            $previousCycleEnd->toDateString(),
                        ]
                    )
                    ->groupBy('customer_id')
                    ->get()
                    ->keyBy('customer_id');

                /*
                |--------------------------------------------------------------------------
                | 3. Find customers with >50% usage drop
                |--------------------------------------------------------------------------
                */

                $churnRisk = [];

                foreach ($customers as $customer) {

                    $customerUsage = $usage->get($customer->id);

                    /*
                    |--------------------------------------------------------------------------
                    | No usage record
                    |--------------------------------------------------------------------------
                    |
                    | If customer did not have previous usage,
                    | percentage drop cannot be calculated.
                    |
                    */

                    if (!$customerUsage) {
                        continue;
                    }

                    $currentUsage  = (int) $customerUsage->current_usage;
                    $previousUsage = (int) $customerUsage->previous_usage;

                    /*
                    |--------------------------------------------------------------------------
                    | Previous usage must be greater than zero
                    |--------------------------------------------------------------------------
                    */

                    if ($previousUsage <= 0) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate percentage drop
                    |--------------------------------------------------------------------------
                    */

                    $dropPercentage =
                        (($previousUsage - $currentUsage) / $previousUsage) * 100;

                    /*
                    |--------------------------------------------------------------------------
                    | Churn risk threshold
                    |--------------------------------------------------------------------------
                    |
                    | > 50% drop = churn risk
                    |
                    */

                    if ($dropPercentage > 50) {

                        $churnRisk[] = [
                            'customer_id'    => $customer->id,
                            'customer_name'  => $customer->name,
                            'previous_usage' => $previousUsage,
                            'current_usage'  => $currentUsage,
                            'drop_percentage' => round($dropPercentage, 2),
                        ];
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 4. Sort highest risk first
                |--------------------------------------------------------------------------
                */

                usort(
                    $churnRisk,
                    fn ($a, $b) =>
                        $b['drop_percentage'] <=> $a['drop_percentage']
                );

                /*
                |--------------------------------------------------------------------------
                | 5. Return top 5 churn-risk customers
                |--------------------------------------------------------------------------
                */

                return array_slice($churnRisk, 0, 5);
            //}
        //);
    }


    public function getDailyUsageTrend($merchant): array
    {
        /*
        |--------------------------------------------------------------------------
        | Last 30 days
        |--------------------------------------------------------------------------
        |
        | Example:
        | Today = 2026-09-12
        |
        | Start = 2026-08-14
        | End   = 2026-09-12
        |
        */

        $endDate = Carbon::today();

        $startDate = Carbon::today()
            ->subDays(29);

        /*return Cache::remember(
            "merchant:{$merchant->id}:daily-usage-trend:" . $endDate->toDateString(),
            now()->addMinutes(5),
            function () use ($merchant, $startDate, $endDate) {*/

                /*
                |--------------------------------------------------------------------------
                | 1. Get customers belonging to merchant
                |--------------------------------------------------------------------------
                */

                $customerIds = $merchant->customers()
                    ->pluck('id');

                if ($customerIds->isEmpty()) {
                    return [];
                }

                /*
                |--------------------------------------------------------------------------
                | 2. Get active subscriptions
                |--------------------------------------------------------------------------
                */

                $subscriptionIds = Subscription::query()
                    ->whereIn('customer_id', $customerIds)
                    ->where('status', 'active')
                    ->pluck('id');

                if ($subscriptionIds->isEmpty()) {
                    return [];
                }

                /*
                |--------------------------------------------------------------------------
                | 3. Aggregate daily usage
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | We use ONE query for the entire 30-day period.
                |
                | We do NOT run one query per day.
                |
                */

                $usage = DailyUsage::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->whereBetween('usage_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->select(
                        'usage_date',
                        DB::raw('SUM(units) as total_units')
                    )
                    ->groupBy('usage_date')
                    ->orderBy('usage_date')
                    ->get();

                /*
                |--------------------------------------------------------------------------
                | 4. Convert database result into date => units map
                |--------------------------------------------------------------------------
                */

                $usageByDate = $usage->mapWithKeys(function ($row) {
                    return [
                        Carbon::parse($row->usage_date)->toDateString()
                            => (int) $row->total_units
                    ];
                });

                /*
                |--------------------------------------------------------------------------
                | 5. Return all 30 days
                |--------------------------------------------------------------------------
                |
                | If there is no usage on a particular day,
                | return 0 instead of removing the day.
                |
                | This is important for the chart because we want
                | a continuous 30-day timeline.
                |
                */

                $result = [];

                for (
                    $date = $startDate->copy();
                    $date->lte($endDate);
                    $date->addDay()
                ) {

                    $dateString = $date->toDateString();

                    $result[] = [
                        'date' => $dateString,
                        'units' => $usageByDate->get($dateString, 0),
                    ];
                }

                return $result;
            //}
        //);
    }

    public function getActivePlans($merchant)
    {
        /*return Cache::remember(
            "merchant:{$merchant->id}:active-plans",
            now()->addMinutes(10),
            function () use ($merchant) {*/

                return Plan::query()
                    ->select([
                        'plans.id',
                        'plans.name',
                        'plans.billing_cycle',
                    ])
                    ->selectRaw('COUNT(subscriptions.id) as subscription_count')
                    ->join(
                        'subscriptions',
                        'subscriptions.plan_id',
                        '=',
                        'plans.id'
                    )
                    ->join(
                        'customers',
                        'customers.id',
                        '=',
                        'subscriptions.customer_id'
                    )
                    ->where('plans.merchant_id', $merchant->id)
                    ->where('customers.merchant_id', $merchant->id)
                    ->where('subscriptions.status', 'active')
                    ->groupBy(
                        'plans.id',
                        'plans.name',
                        'plans.billing_cycle'
                    )
                    ->orderBy('plans.id')
                    ->get();
            //}
        //);
    }

}
