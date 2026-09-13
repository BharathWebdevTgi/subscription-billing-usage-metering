<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreatePlanChangeScenario extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:plan-change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a demo subscription plan-change scenario';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {

            // Use existing Subscription #1
            $subscription = Subscription::findOrFail(1);

            // Existing Basic plan
            $basicPlan = Plan::findOrFail(1);

            // New Standard plan
            $standardPlan = Plan::findOrFail(2);

            // Demo dates
            $periodStart = $subscription->started_at;
            $planChangeDate = $periodStart->copy()->addDays(10);
            $periodEnd = $periodStart->copy()->endOfMonth();

            /*
             * Remove existing demo periods for this subscription
             * so the command can be safely executed again.
             */
            SubscriptionPeriod::where(
                'subscription_id',
                $subscription->id
            )->delete();

            // Period 1 - Basic
            SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'plan_id' => $basicPlan->id,
                'starts_at' => $periodStart,
                'ends_at' => $planChangeDate,
            ]);

            // Period 2 - Standard
            SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'plan_id' => $standardPlan->id,
                'starts_at' => $planChangeDate,
                'ends_at' => $periodEnd,
            ]);

            // Update current subscription to Standard
            $subscription->update([
                'plan_id' => $standardPlan->id,
            ]);
        });

        $this->info('Plan change scenario created successfully.');

        $this->info('Subscription #1: Basic -> Standard');

        return self::SUCCESS;
    }
}
