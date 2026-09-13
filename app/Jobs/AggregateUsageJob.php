<?php

namespace App\Jobs;

use App\Models\DailyUsage;
use App\Models\UsageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AggregateUsageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $usageDate
    ) {
    }

    public function handle(): void
    {
        UsageEvent::query()
        ->select(
            'customer_id',
            'subscription_id',
            'usage_date',
            DB::raw('SUM(units) as units')
        )
        ->whereDate('usage_date', $this->usageDate)
        ->groupBy(
            'customer_id',
            'subscription_id',
            'usage_date'
        )
        ->orderBy('customer_id')
        ->orderBy('subscription_id')
        ->chunk(5000, function ($usage) {

            foreach ($usage as $item) {

                DailyUsage::updateOrCreate(
                    [
                        'customer_id' => $item->customer_id,
                        'subscription_id' => $item->subscription_id,
                        'usage_date' => $item->usage_date,
                    ],
                    [
                        'units' => $item->units,
                    ]
                );
            }
        });   
    }
}