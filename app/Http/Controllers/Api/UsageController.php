<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use Illuminate\Http\Request;
use App\Jobs\AggregateUsageJob;

class UsageController extends Controller
{
    public function store(Request $request)
    {
        
        /*
        |--------------------------------------------------------------------------
        | 1. Basic request validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'subscription_id' => [
                'required',
                'integer',
                'exists:subscriptions,id',
            ],

            'usage_date' => [
                'required',
                'date',
            ],

            'units' => [
                'required',
                'integer',
                'min:1',
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:255',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | 2. Check customer
        |--------------------------------------------------------------------------
        */

        $customer = Customer::findOrFail(
            $validated['customer_id']
        );


        /*
        |--------------------------------------------------------------------------
        | 3. Check subscription
        |--------------------------------------------------------------------------
        */

        $subscription = Subscription::findOrFail(
            $validated['subscription_id']
        );


        /*
        |--------------------------------------------------------------------------
        | 4. Subscription must belong to customer
        |--------------------------------------------------------------------------
        */

        if ($subscription->customer_id !== $customer->id) {
            return response()->json([
                'message' => 'Subscription does not belong to the customer.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Subscription must be active
        |--------------------------------------------------------------------------
        */

        if ($subscription->status !== 'active') {
            return response()->json([
                'message' => 'Usage cannot be recorded for an inactive subscription.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Find subscription period for usage date
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Subscription Period 1
        | 2026-09-10 -> 2026-09-20
        | Plan 1
        |
        | Subscription Period 2
        | 2026-09-20 -> 2026-09-30
        | Plan 2
        |
        */

        $usageDate = $validated['usage_date'];

        $subscriptionPeriod = SubscriptionPeriod::where(
                'subscription_id',
                $subscription->id
            )
            ->whereDate('starts_at', '<=', $usageDate)
            ->whereDate('ends_at', '>=', $usageDate)
            ->first();


        /*
        |--------------------------------------------------------------------------
        | 7. Usage date must belong to a subscription period
        |--------------------------------------------------------------------------
        */

        if (!$subscriptionPeriod) {
            return response()->json([
                'message' => 'Usage date does not fall within any subscription period.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | 8. Validate plan
        |--------------------------------------------------------------------------
        |
        | The plan applicable for this usage date comes from
        | subscription_periods.
        |
        */

        if ($subscriptionPeriod->plan_id !== $subscription->plan_id) {
            return response()->json([
                'message' => 'Subscription plan does not match the subscription period.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | 9. Idempotency check
        |--------------------------------------------------------------------------
        */

        $existingEvent = UsageEvent::where(
            'idempotency_key',
            $validated['idempotency_key']
        )->first();

        if ($existingEvent) {
            return response()->json([
                'message' => 'Usage event already recorded.',
                'data' => $existingEvent,
            ], 200);
        }


        /*
        |--------------------------------------------------------------------------
        | 10. Create usage event
        |--------------------------------------------------------------------------
        */

        $usageEvent = UsageEvent::create([
            'customer_id' => $validated['customer_id'],
            'subscription_id' => $validated['subscription_id'],
            'usage_date' => $validated['usage_date'],
            'units' => $validated['units'],
            'idempotency_key' => $validated['idempotency_key'],
        ]);

        AggregateUsageJob::dispatch(
            $usageEvent->usage_date->format('Y-m-d')
        );
        /*
        |--------------------------------------------------------------------------
        | 11. Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => 'Usage recorded successfully.',
            'data' => $usageEvent,
        ], 201);
    }
}