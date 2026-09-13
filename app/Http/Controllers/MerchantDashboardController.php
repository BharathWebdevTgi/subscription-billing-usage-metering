<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MerchantDashboardService;
use App\Models\Merchant;

class MerchantDashboardController extends Controller
{

    public function __construct(MerchantDashboardService $dashboardService) {
        $this->dashboardService = $dashboardService;
    }


    public function index(int $id ) {

        //$dashboardData = $dashboardService->getDashboard($id);
        //return response()->json($dashboardData);

        $merchant = Merchant::findOrFail($id);

        $currentCycleUsage =        $this->dashboardService->getCurrentCycleUsage($merchant);
        $projectedOverageRevenue =  $this->dashboardService->getProjectedOverageRevenue($merchant);
        $topCustomers =             $this->dashboardService->getTopCustomersByUsage($merchant);
        $churn_risk =               $this->dashboardService->getChurnRisk($merchant);
        $dailyUsage =               $this->dashboardService->getDailyUsageTrend($merchant);
        $active_plans =             $this->dashboardService->getActivePlans($merchant);

        $dashboard = [
            'current_cycle_usage' => $currentCycleUsage['used_units'],

            'included_units' => $currentCycleUsage['included_units'],

            'projected_overage_revenue' => $projectedOverageRevenue,

            'active_plans' => $active_plans,

            'top_customers' => $topCustomers,

            'churn_risk' => $churn_risk,

            'daily_usage' => $dailyUsage,

            'system_status' => [
                'plan_pricing_cache' => 'Database, TTL 10m',
                'aggregation_job' => 'queued and chunked (5k rows/batch)',
                'usage_endpoint' => 'rate-limited',
                'rate_limit' => '120 req/min per API key',
            ],
        ];

        return view('merchant.dashboard', ['merchant' => $merchant,'dashboard' => $dashboard,]);

    }
}
