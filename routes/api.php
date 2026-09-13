<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\SubscriptionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/usage', [UsageController::class, 'store'])->middleware('throttle:usage-api');

Route::post(
    '/customers/{customer}/subscriptions/{subscription}/change-plan',
    [SubscriptionController::class, 'changePlan']
)->middleware('throttle:usage-api');