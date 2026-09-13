<?php

namespace App\Jobs;

use App\Models\SubscriptionPeriod;
use App\Services\InvoiceGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $subscriptionPeriodId
    ) {
    }

    public function handle(InvoiceGenerationService $invoiceService): void
    {
        $period = SubscriptionPeriod::findOrFail(
            $this->subscriptionPeriodId
        );

        $invoiceService->generate($period);
    }
}