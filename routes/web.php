<?php

use Illuminate\Support\Facades\Route;
use App\Services\InvoiceGenerationService;
use App\Http\Controllers\MerchantDashboardController;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/merchants/{id}/dashboard',[MerchantDashboardController::class, 'index']);


