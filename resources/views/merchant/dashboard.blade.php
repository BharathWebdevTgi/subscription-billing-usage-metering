<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Merchant Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f5f7fa;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        .dashboard {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            background: #1f2937;
            color: white;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .header small {
            color: #9ca3af;
        }

        /* Summary cards */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border: 1px solid #9ca3af;
            padding: 16px;
            min-height: 95px;
        }

        .summary-card.blue {
            border-left: 5px solid #2563eb;
        }

        .summary-card.orange {
            border-left: 5px solid #f59e0b;
        }

        .summary-card.green {
            border-left: 5px solid #16a34a;
        }

        .summary-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 20px;
            font-weight: bold;
        }

        /* Main content */

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Customer table */

        .customer-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #94a3b8;
        }

        .customer-table th {
            background: #e2e8f0;
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .customer-table td {
            padding: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 13px;
        }

        /* Active Plans */
        .active-plans {
            margin-top: 8px;
        }

        .active-plan-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid #e5e9ef;
        }

        .active-plan-row:last-child {
            border-bottom: none;
        }

        .active-plan-info {
            display: flex;
            align-items: center;
        }

        .active-plan-name {
            font-size: 17px;
            font-weight: 600;
            color: #172033;
        }

        .active-plan-cycle {
            margin-left: 4px;
            font-size: 15px;
            color: #4f6685;
        }

        .active-plan-count {
            font-size: 14px;
            color: #315d91;
            white-space: nowrap;
        }        

        /* Churn */

        .churn-box {
            background: #fff5f5;
            border: 1px solid #ef4444;
            padding: 15px;
            min-height: 125px;
        }

        .churn-title {
            color: #dc2626;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .risk-item {
            font-size: 12px;
            margin-bottom: 8px;
        }

        /* System status */

        .status-box {
            background: #eff6ff;
            border: 1px solid #0284c7;
            padding: 15px;
            margin-top: 15px;
        }

        .status-title {
            color: #0369a1;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .status-item {
            font-size: 12px;
            margin-bottom: 8px;
        }

        /* Chart */

        .chart-box {
            width: 100%;
            border: 1px solid #9aaec5;
            padding: 20px 20px 22px 20px;
            box-sizing: border-box;
            background: #fff;
        }

        .usage-chart {
            display: flex;
            width: 100%;
        }

        .chart-y-labels {
            width: 55px;
            height: 240px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
            padding-right: 10px;
            box-sizing: border-box;
            font-size: 13px;
            color: #315d91;
        }

        .chart-content {
            flex: 1;
            min-width: 0;
        }

        .chart {
            display: block;
            width: 100%;
            height: 240px;
            overflow: visible;
        }

        /*
        |--------------------------------------------------------------------------
        | Grid lines
        |--------------------------------------------------------------------------
        */

        .chart-grid {
            stroke: #e1e6ec;
            stroke-width: 0.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Vertical grid lines
        |--------------------------------------------------------------------------
        */

        .chart-grid-vertical {
            stroke: #d9e0e8;
            stroke-width: 0.4;
        }

        /*
        |--------------------------------------------------------------------------
        | Usage line
        |--------------------------------------------------------------------------
        */

        .chart-line {
            fill: none;
            stroke: #2864e8;
            stroke-width: 0.5;
            stroke-linejoin: round;
            stroke-linecap: butt;
        }

        /*
        |--------------------------------------------------------------------------
        | Date labels
        |--------------------------------------------------------------------------
        */

        .chart-dates {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding: 0 2px;
            font-size: 13px;
            color: #315d91;
        }

        .chart-dates span {
            white-space: nowrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .empty {
            padding: 30px;
            text-align: center;
            color: #777;
        }        

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .main-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    {{-- Header --}}
    <div class="header">
        <h1>
            Merchant Dashboard
            @if(isset($merchant))
                — {{ $merchant->name }}
            @endif
        </h1>

        <small>Dashboard</small>
    </div>


    {{-- ============================= --}}
    {{-- SUMMARY CARDS                 --}}
    {{-- ============================= --}}

    <div class="summary-grid">

        {{-- Current Cycle Usage --}}
        <div class="summary-card blue">

            <div class="summary-label">
                Current Cycle Usage
            </div>

            <div class="summary-value">
                {{ number_format($dashboard['current_cycle_usage'] ?? 0) }}
                /
                {{ number_format($dashboard['included_units'] ?? 0) }}
                units
            </div>

        </div>


        {{-- Projected Overage Revenue --}}
        <div class="summary-card orange">

            <div class="summary-label">
                Projected Overage Revenue
            </div>

            <div class="summary-value">
                ₹ {{ number_format($dashboard['projected_overage_revenue'] ?? 0, 2) }}
            </div>

        </div>


        {{-- Active Plan --}}
        <div class="summary-card green">

            <div class="summary-label">
                Active Plans
            </div>

            @if(!empty($dashboard['active_plans']) && $dashboard['active_plans']->isNotEmpty())

                <div class="active-plans">

                    @foreach($dashboard['active_plans'] as $plan)

                        <div class="active-plan-row">

                            <div class="active-plan-info">

                                <span class="active-plan-name">
                                    {{ $plan->name }}
                                </span>

                                <span class="active-plan-cycle">
                                    — {{ ucfirst($plan->billing_cycle) }}
                                </span>

                            </div>

                            <div class="active-plan-count">

                                {{ number_format($plan->subscription_count) }}

                                subscription{{ $plan->subscription_count == 1 ? '' : 's' }}

                            </div>

                        </div>

                    @endforeach

                </div>

            @else
                <div class="empty">
                    No active subscriptions.
                </div>
            @endif

        </div>

    </div>


    {{-- ============================= --}}
    {{-- MAIN CONTENT                  --}}
    {{-- ============================= --}}

    <div class="main-grid">

        {{-- LEFT COLUMN --}}
        <div>

            {{-- Top Customers --}}
            <div class="section">

                <div class="section-title">
                    Top 5 Customers by Usage (this cycle)
                </div>

                @if(!empty($dashboard['top_customers']))

                    <table class="customer-table">

                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Usage</th>
                            <th>Included Units</th>
                            <th>% of Allowance</th>
                        </tr>
                        </thead>

                        <tbody>

                        @foreach($dashboard['top_customers'] as $customer)

                            <tr>

                                <td>
                                    {{ $customer['customer'] ?? '-' }}
                                </td>

                                <td>
                                    {{ number_format($customer['usage'] ?? 0) }}
                                </td>
                                <td>
                                    {{ number_format($customer['included_units'] ?? 0) }}
                                </td>
                                <td>
                                    {{ number_format($customer['percentage'] ?? 0) }}%
                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                @else

                    <div class="empty">
                        No customer usage data available.
                    </div>

                @endif

            </div>


            {{-- Daily Usage Trend --}}
            <div class="section">

                <div class="section-title">
                    Daily Usage Trend (last 30 days)
                </div>

                <div class="chart-box">

                    @if(!empty($dashboard['daily_usage']))

                        @php
                            /*
                            |--------------------------------------------------------------------------
                            | Prepare daily usage data
                            |--------------------------------------------------------------------------
                            */

                            $dailyUsage = collect($dashboard['daily_usage'])
                                ->map(function ($item) {
                                    return [
                                        'date'  => $item['date'],
                                        'units' => (float) $item['units'],
                                    ];
                                })
                                ->values();

                            $dateCount = $dailyUsage->count();

                            /*
                            |--------------------------------------------------------------------------
                            | Maximum usage
                            |--------------------------------------------------------------------------
                            */

                            $maxUsage = max(
                                $dailyUsage->max('units') ?? 0,
                                1
                            );

                            /*
                            |--------------------------------------------------------------------------
                            | Build polyline points
                            |--------------------------------------------------------------------------
                            */

                            $points = [];

                            foreach ($dailyUsage as $index => $item) {

                                $x = $dateCount > 1
                                    ? ($index / ($dateCount - 1)) * 100
                                    : 50;

                                /*
                                | Keep some space from the top and bottom
                                */
                                $y = 100 - (($item['units'] / $maxUsage) * 90);

                                $points[] =
                                    round($x, 2) . ',' .
                                    round($y, 2);
                            }

                            $polyline = implode(' ', $points);

                            /*
                            |--------------------------------------------------------------------------
                            | Date labels
                            | Maximum 10 labels
                            |--------------------------------------------------------------------------
                            */

                            $labelCount = min(10, $dateCount);

                            $dateLabels = [];
                            $dateIndexes = [];

                            if ($labelCount === 1) {

                                $dateLabels[] = $dailyUsage->first()['date'];
                                $dateIndexes[] = 0;

                            } else {

                                for ($i = 0; $i < $labelCount; $i++) {

                                    $index = round(
                                        $i * ($dateCount - 1) / ($labelCount - 1)
                                    );

                                    $dateLabels[] = $dailyUsage[$index]['date'];
                                    $dateIndexes[] = $index;
                                }
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | 5 Unit labels
                            |
                            | Example if maximum usage = 1500:
                            |
                            | 1500
                            | 1125
                            | 750
                            | 375
                            | 0
                            |--------------------------------------------------------------------------
                            */

                            $unitLabels = [];

                            for ($i = 0; $i < 5; $i++) {

                                $value = $maxUsage * (1 - ($i / 4));

                                $unitLabels[] = round($value);
                            }

                        @endphp


                        <div class="usage-chart">

                            {{-- ==========================================================
                                Y AXIS UNIT LABELS
                                ========================================================== --}}

                            <div class="chart-y-labels">

                                @foreach($unitLabels as $unit)

                                    <span>
                                        {{ number_format($unit) }}
                                    </span>

                                @endforeach

                            </div>


                            {{-- ==========================================================
                                CHART AREA
                                ========================================================== --}}

                            <div class="chart-content">

                                <svg
                                    class="chart"
                                    viewBox="0 0 100 100"
                                    preserveAspectRatio="none"
                                >

                                    {{-- ==================================================
                                        Horizontal Grid Lines - 5
                                        ================================================== --}}

                                    <line
                                        x1="0"
                                        y1="0"
                                        x2="100"
                                        y2="0"
                                        class="chart-grid"
                                    />

                                    <line
                                        x1="0"
                                        y1="25"
                                        x2="100"
                                        y2="25"
                                        class="chart-grid"
                                    />

                                    <line
                                        x1="0"
                                        y1="50"
                                        x2="100"
                                        y2="50"
                                        class="chart-grid"
                                    />

                                    <line
                                        x1="0"
                                        y1="75"
                                        x2="100"
                                        y2="75"
                                        class="chart-grid"
                                    />

                                    <line
                                        x1="0"
                                        y1="100"
                                        x2="100"
                                        y2="100"
                                        class="chart-grid"
                                    />


                                    {{-- ==================================================
                                        Vertical Grid Lines
                                        One for each of the 10 date labels
                                        ================================================== --}}

                                    @foreach($dateIndexes as $index)

                                        @php

                                            $x = $dateCount > 1
                                                ? ($index / ($dateCount - 1)) * 100
                                                : 50;

                                        @endphp

                                        <line
                                            x1="{{ round($x, 2) }}"
                                            y1="0"
                                            x2="{{ round($x, 2) }}"
                                            y2="100"
                                            class="chart-grid chart-grid-vertical"
                                        />

                                    @endforeach


                                    {{-- ==================================================
                                        Usage Line
                                        ================================================== --}}

                                    <polyline
                                        points="{{ $polyline }}"
                                        class="chart-line"
                                    />

                                </svg>


                                {{-- ======================================================
                                    X AXIS DATE LABELS
                                    ====================================================== --}}

                                <div class="chart-dates">

                                    @foreach($dateLabels as $date)

                                        <span>
                                            {{ \Carbon\Carbon::parse($date)->format('d M') }}
                                        </span>

                                    @endforeach

                                </div>

                            </div>

                        </div>


                    @else

                        <div class="empty">
                            No daily usage data available.
                        </div>

                    @endif

                </div>

            </div>
        </div>


        {{-- RIGHT COLUMN --}}
        <div>

            {{-- Churn Risk --}}
            <div class="churn-box">

                <div class="churn-title">
                    ⚠ Churn Risk
                </div>

                @if(!empty($dashboard['churn_risk']))

                    @foreach($dashboard['churn_risk'] as $risk)

                        <div class="risk-item">

                            • {{ $risk['customer_name'] ?? '-' }}

                            —
                            {{ number_format($risk['drop_percentage'] ?? 0) }}%
                            drop

                        </div>

                    @endforeach

                @else

                    <div class="risk-item">
                        No customers currently identified as churn risk.
                    </div>

                @endif

            </div>


            {{-- System Status --}}
            <div class="status-box">

                <div class="status-title">
                    System status (informational)
                </div>

                <div class="status-item">
                    Plan pricing cache:
                    {{ $dashboard['system_status']['plan_pricing_cache'] ?? 'OK' }}
                </div>

                <div class="status-item">
                    Nightly aggregation job:
                    {{ $dashboard['system_status']['aggregation_job'] ?? 'OK' }}
                </div>

                <div class="status-item">
                    Usage endpoint:
                    {{ $dashboard['system_status']['usage_endpoint'] ?? 'OK' }}
                </div>

                <div class="status-item">
                    Usage endpoint rate-limit:
                    {{ $dashboard['system_status']['rate_limit'] ?? '-' }}
                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>