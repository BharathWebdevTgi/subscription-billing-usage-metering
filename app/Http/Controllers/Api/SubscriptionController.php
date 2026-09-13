<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function changePlan(
        Request $request,
        Customer $customer,
        Subscription $subscription
    ) {
        $request->validate([
            'plan_id' => [
                'required',
                'integer',
                'exists:plans,id',
            ],
        ]);

        $newPlan = Plan::findOrFail($request->plan_id);

        /*
         * Make sure this subscription belongs
         * to the requested customer.
         */
        if ($subscription->customer_id !== $customer->id) {
            return response()->json([
                'message' => 'Subscription does not belong to this customer.',
            ], 403);
        }

        /*
         * Make sure the new plan belongs
         * to the same merchant as the customer.
         */
        if ($newPlan->merchant_id !== $customer->merchant_id) {
            return response()->json([
                'message' => 'Selected plan does not belong to this merchant.',
            ], 422);
        }

        /*
         * Don't allow changing to the same plan.
         */
        if ($subscription->plan_id === $newPlan->id) {
            return response()->json([
                'message' => 'Customer is already subscribed to this plan.',
            ], 422);
        }

        $result = DB::transaction(function () use (
            $subscription,
            $newPlan
        ) {

            $now = Carbon::now();

            /*
             * Get the current/latest subscription period.
             */
            $currentPeriod = SubscriptionPeriod::query()
                ->where('subscription_id', $subscription->id)
                ->latest('starts_at')
                ->firstOrFail();

            /*
             * Close the current period.
             */
            $currentPeriod->update([
                'ends_at' => $now,
            ]);

            /*
             * Create the new period until the end
             * of the current month.
             */
            $newPeriod = SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'plan_id' => $newPlan->id,
                'starts_at' => $now,
                'ends_at' => $now->copy()->endOfMonth(),
            ]);

            /*
             * Update subscription's current plan.
             */
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);

            return [
                'subscription' => $subscription->fresh(),
                'previous_period' => $currentPeriod->fresh(),
                'new_period' => $newPeriod,
            ];
        });

        return response()->json([
            'message' => 'Plan changed successfully.',
            'data' => $result,
        ]);
    }
}