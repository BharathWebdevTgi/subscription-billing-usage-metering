<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GenerateInvoiceJob;
use App\Models\SubscriptionPeriod;
use Carbon\Carbon;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate
                        {end_date? : Billing period end date (YYYY-MM-DD)}';

    protected $description = 'Generate invoices for completed subscription periods';

    public function handle(): int
    {

        $endDate = $this->argument('end_date') ? Carbon::parse($this->argument('end_date'))->endOfDay()  : today();

        $periods = SubscriptionPeriod::whereDate('ends_at', $endDate)->get();

        if ($periods->isEmpty()) {
            $this->info('No subscription periods ending today.');

            return self::SUCCESS;
        }

        foreach ($periods as $period) {
            GenerateInvoiceJob::dispatch($period->id);

            $this->info(
                "Invoice generation job dispatched for subscription period #{$period->id}"
            );
        }

        $this->info(
            "{$periods->count()} invoice generation job(s) dispatched."
        );

        return self::SUCCESS;
    }
}
