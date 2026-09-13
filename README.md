
## Usage API & Billing Platform — Architecture Summary

	1. Application Overview

		The application is a Laravel-based usage metering and subscription billing platform.

		Its main responsibilities are:

		Manage merchants and customers
		Manage subscription plans
		Manage customer subscriptions
		Accept usage events through an API
		Aggregate daily usage
		Calculate subscription charges
		Calculate overage charges
		Handle plan changes and proration
		Generate invoices
		Identify churn-risk customers
		Provide merchant dashboard analytics
		Use Redis for caching
		Apply API rate limiting
		Process large datasets efficiently using queued jobs and chunking
		
	2. High-Level Architecture
	
                         ┌─────────────────────┐
                         │      Client/API      │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │   Laravel API       │
                         │                     │
                         │ Validation          │
                         │ Authentication      │
                         │ Rate Limiting       │
                         │ Idempotency         │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │    Usage Events     │
                         │    usage_events     │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │ AggregateUsageJob    │
                         │      Queue          │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │    Daily Usage      │
                         │    daily_usage      │
                         └──────────┬──────────┘
                                    │
                    ┌───────────────┼────────────────┐
                    ▼               ▼                ▼
              ┌──────────┐   ┌────────────┐   ┌────────────┐
              │ Dashboard│   │   Billing  │   │ Churn Risk │
              └──────────┘   └─────┬──────┘   └────────────┘
                                    │
                                    ▼
                             ┌─────────────┐
                             │   Invoice   │
                             └─────────────┘

	3. Core Database Architecture
	
		merchants
		   │
		   ├── plans
		   │
		   └── customers
				  │
				  └── subscriptions
							│
							└── subscription_periods

		usage_events
		   │
		   └── daily_usage	
		   
	4. Main relationships
		
			Merchant
			   │
			   ├───────────────┐
			   ▼               ▼
			Plans          Customers
			   │               │
			   │               ▼
			   │         Subscriptions
			   │               │
			   └───────────────┤
							   ▼
					   Subscription Periods
							   │
							   ▼
						  Usage Events
							   │
							   ▼
						  Daily Usage		
						  
	5. Usage API Flow
	
		API Request
			 │
			 ▼
		Validate Request
			 │
			 ▼
		Validate Customer / Subscription
			 │
			 ▼
		Check Idempotency Key
			 │
			 ▼
		Create UsageEvent
			 │
			 ▼
		Dispatch AggregateUsageJob
			 │
			 ▼
		Return API Response	
		
		Note : The idempotency_key prevents the same usage request from being processed multiple times.
		
	6. Usage Aggregation
	
		Raw usage events are aggregated into daily_usage.
		
		For Example
		
		usage_events will have 

			Customer 1
			2026-09-10 → 100 units
			2026-09-10 → 200 units
			2026-09-10 → 150 units		
			
		daily_usage

			Customer 1
			2026-09-10 → 450 units			
			
	7. Queue-Based Processing
	
		Instead of performing expensive aggregation directly inside the API request, the work can be processed asynchronously through Laravel's queue system.

		This is important for scalability because the API should remain responsive even when usage volume becomes very large.

		For large datasets, the aggregation process can use chunking so that records are processed in manageable batches rather than loading everything into memory at once.	
		
	8. Plan Change Flow	
	
		Existing Plan
			  │
			  ▼
		Close Current Period
			  │
			  └── ends_at = now()
			  │
			  ▼
		Create New Subscription Period
			  │
			  ├── new plan_id
			  ├── starts_at = now()
			  └── ends_at = end of current month	
			  
		Example:

		Old Period 

			Basic
			01 Sep → 13 Sep
					  ↑
				   plan change

		becomes:

			Basic
			01 Sep → 13 Sep

			Standard
			13 Sep → 30 Sep			  
			
	9. Billing Architecture
	
		Subscription Period
				│
				▼
		GenerateInvoiceJob
				│
				▼
		InvoiceGenerationService
				│
				├── Load Plan
				│
				├── Determine Billing Period
				│
				├── Calculate Daily Usage
				│
				├── Calculate Included Units
				│
				├── Calculate Overage Units
				│
				├── Calculate Overage Amount
				│
				├── Calculate Prorated Base Charge
				│
				├── Calculate Total Amount
				│
				├── Create Invoice
				│
				└── Create Invoice Items	
				
		Invoice Items

			Each invoice contains a base subscription charge as an invoice_item.

			When usage exceeds the included allowance, a second invoice item is created for the usage overage.				
			
			Invoice Items
			 ├── Basic Plan
			 │    ├── Units: subscription days
			 │    └── Amount: prorated base charge
			 │
			 └── Usage Overage
				  ├── Units: excess usage
				  └── Amount: overage charge	

	10. Dashboard Architecture
	
		Current Cycle Usage - Displays the total usage consumed by the merchant's customers during the current billing cycle compared with the total 
								included usage allowance.
								
		Projected Overage Revenue :
		
			Projected Overage Revenue estimates the additional revenue that the merchant is expected to generate from usage exceeding the plan's included allowance by the end of the current billing period.

			The calculation uses the current daily usage average and projects it across the remaining billing period.

			Calculation

			For the billing period September 11 to September 30:

			Total period: 20 days
			Elapsed days: 1 day
			Usage recorded on September 11: 650 units
			Included allowance: 500 units
			Overage rate: ₹10 per unit

			1. Calculate daily average usage

			Daily Average = Current Usage / Elapsed Days
						  = 650 / 1
						  = 650 units/day

			2. Project total usage for the billing period

			Projected Usage = Daily Average × Total Period Days
							= 650 × 20
							= 13,000 units

			3. Calculate projected overage

			Projected Overage = Projected Usage - Included Allowance
							  = 13,000 - 500
							  = 12,500 units

			4. Calculate projected overage revenue

			Projected Revenue = Projected Overage × Overage Rate
							  = 12,500 × ₹10
							  = ₹125,000
		
		Active Plans - Displays all plans currently being used by the merchant and the number of active subscriptions associated with each plan.
		
		Top 5 Customers by Usage - Identifies the merchant's highest-usage customers during the current billing cycle.
		
		
		Churn Risk - Identifies customers whose usage has dropped significantly compared with their previous billing period.
		
		Daily Usage Trend — Last 30 Days - Provides a visual representation of the merchant's daily usage over the previous 30 days.
		
		System Status - Provides operational information about the application's infrastructure and processing configuration.
		
		
## Setup / Run Instructions --------------------------------------

	1. Prerequisites

		Make sure the following are installed:

		PHP
		Composer
		MySQL
		XAMPP
		Redis / Memurai for Windows
		Laravel project
		Postman (for API testing)
		
	2. Install Project Dependencies

		Open the project directory in terminal:
		
			cd C:\xampp\htdocs\usage-api
			
		Install Composer dependencies:
		
			composer install
			
	3. Environment Configuration
	
		Copy the environment file if .env does not already exist:
		
			copy .env.example .env
			
		Generate the Laravel application key:
		
			php artisan key:generate
			
		Configure the database in .env:
		
			DB_CONNECTION=mysql
			DB_HOST=127.0.0.1
			DB_PORT=3306
			DB_DATABASE=usageapi
			DB_USERNAME=root
			DB_PASSWORD=			
			
	4. Database Setup
	
		Create the database in MySQL/phpMyAdmin and run migrations:
		
			php artisan migrate
			
		The project contains seed data for the required application entities.
		
			php artisan db:seed
			
			The seeded data provides the initial customers, merchants, plans, subscriptions, and subscription periods required for testing the application.

			DailyUsage data can be generated separately where required because actual usage is normally created through the Usage API.			
			
	5. Start the Laravel Application
	
		php artisan serve
		
		The application will normally be available at http://127.0.0.1:8000
		
	6. Start the Queue Worker
	
		php artisan queue:work
		
		The application uses queued jobs for background processing such as usage aggregation and invoice generation.	

		Keep this terminal running while testing the application.
		
	7. Usage API
	
		The usage endpoint is: POST http://127.0.0.1:8000/api/usage
		
		Sample request:
		
			{
				"customer_id": 1,
				"subscription_id": 1,
				"usage_date": "2026-09-10",
				"units": 250,
				"idempotency_key": "usage-100004"
			}		
		
		This endpoint records a usage event.

		After the request is successfully recorded, usage aggregation is handled asynchronously through AggregateUsageJob.

		Because the endpoint is designed to be idempotent, retrying the same request should not result in the same usage being counted twice.		
		
		
	8. Plan Change API
	
		POST http://127.0.0.1:8000/api/customers/{customer_id}/subscriptions/{subscription_id}/change-plan
		
		POST http://127.0.0.1:8000/api/customers/10/subscriptions/10/change-plan
		
		Sample request:
		
			{
				"plan_id": 6
			}		
		
		The plan-change flow
		
			Existing Subscription Period
					  ↓
			Close current period
			ends_at = now()
					  ↓
			Create new Subscription Period
					  ↓
			new plan_id
			starts_at = now()
			ends_at = end of current month		
			
			
	9. Invoice Generation
	
		Invoice generation is handled through the GenerateInvoices Artisan command and GenerateInvoiceJob. The system automatically generates invoices 
		for subscription periods that have ended, while a specific billing-period end date can also be supplied when an invoice needs to be generated 
		manually.		
		
		Scheduled Invoice Generation

			The invoice generation command is scheduled to run every day at 11:00 PM.

			At 11:00 PM, the scheduled task runs:	
			
			php artisan invoices:generate
			
			The command identifies subscription periods whose billing period has ended on that day and generates the corresponding invoices.

			The flow is:	
			
				Every day at 11:00 PM
						  ↓
				php artisan invoices:generate
						  ↓
				Find subscription periods
				that ended today
						  ↓
				GenerateInvoiceJob
						  ↓
				InvoiceGenerationService
						  ↓
				Calculate billing
						  ↓
				Create Invoice + Invoice Items	

		Manual Invoice Generation for a Specific Period

			If an invoice needs to be generated for a specific billing-period end date, the date can be supplied as an optional command parameter.
				
			php artisan invoices:generate 2026-09-30
			
	10. Merchant Dashboard
	
		http://127.0.0.1:8000/merchants/{merchant_id}/dashboard
		
		http://127.0.0.1:8000/merchants/1/dashboard
		
		The dashboard displays:
		
			Current Cycle Usage
			Projected Overage Revenue
			Top 5 Customers by Usage
			Churn Risk
			Daily Usage Trend
			Active Plans		
			
		Dashboard calculations are handled by:
		
			MerchantDashboardController
					↓
			MerchantDashboardService		
			
	11. Run Feature Tests
	
		Run all feature tests ----> php artisan test
		
		Run only the usage tests ----> php artisan test tests/Feature/UsageEventTest.php
		
		Run only the plan change tests ----> php artisan test tests/Feature/PlanChangeTest.php
		
		Run invoice tests ----> php artisan test tests/Feature/InvoiceGenerationTest.php		
		
## File Structure Summary ------------------------

	The application follows a Laravel MVC/service/job-based architecture. The main functionality is separated into API controllers, services, queued jobs, 
	console commands, dashboard components, and feature tests.

	app/
	├── Console/
	│   └── Commands/
	│       └── GenerateInvoices.php
	│
	├── Http/
	│   └── Controllers/
	│       ├── MerchantDashboardController.php
	│       │
	│       └── Api/
	│           ├── UsageController.php
	│           └── SubscriptionController.php
	│
	├── Jobs/
	│   ├── AggregateUsageJob.php
	│   └── GenerateInvoiceJob.php
	│
	└── Services/
		├── InvoiceGenerationService.php
		└── MerchantDashboardService.php


	routes/
	├── web.php
	└── api.php


	tests/
	└── Feature/
		├── UsageEventTest.php
		├── PlanChangeTest.php
		└── InvoiceGenerationTest.php

    Note : For testing purposes, the cache logic in app/Services/MerchantDashboardService.php is temporarily commented out. This ensures that the dashboard displays the latest data immediately during testing. The cache can be re-enabled for normal application usage.
		
## # AI-Assisted Development — Prompt Log

    ## About This Prompt Log

    AI-assisted development was used throughout the implementation of this application. I used AI tools including **chatGpt and Claude through their browser interfaces** during development.

    This document contains a **representative summary of the initial and key prompts used during development**, organized by functionality. The examples demonstrate how AI assistance was used to understand requirements,
    design the implementation, review code, troubleshoot issues, and improve scalability.

    The prompts shown below are representative examples rather than a complete history of every AI interaction. The final implementation was reviewed, tested, and adapted against the application's actual database schema,
    business requirements, and observed results.

    ## Usage API
        "Create an API for recording usage events. The endpoint should be safe to call at high throughput and idempotent so a retried request does not double-count usage."

    ## Usage Aggregation
        "I am calculating aggregation through AggregateUsageJob.php.How can I implement chunking here?"    

    ## Current Cycle Usage
        "Get all customer active subscriptions belonging to the merchant. Check subscription periods that come under the current billing cycle and calculate usage units and included units."    

    ## Projected Overage Revenue
        "Explain what Projected Overage Revenue means with an example and implement the calculation."    

    ## Top 5 Customers by Usage
        "Implement Top 5 Customers by Usage for the current billing cycle."    

    ## Daily Usage Trend
        "Implement Daily Usage Trend for the last 30 days."

    ## Active Plans
        "If a merchant has three active plans, show all plans with the number of active subscriptions."    

    ## Plan Change API
        "Create an API for changing a customer's existing plan to another plan belonging to the same merchant."    

    ## Invoice Generation
        "Implement invoice generation including proration and overage calculation."        

    ## Invoice Scheduling
        "Every day at 11 PM we need to find subscription periods that ended today and calculate the invoice. Also allow a specific date to be provided."        

    ## Invoice Edge Cases
        "Write tests for aggregation and billing calculations, including proration and overage edge cases."   

    ## Usage Event Tests
        "Explain how UsageEventTest.php works and why this Laravel/PHPUnit test pattern is used."          

    ## Plan Change Tests
        "Create PlanChangeTest.php for the plan-change API."