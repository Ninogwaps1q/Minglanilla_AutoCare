<?php

use App\Http\Controllers\Api\ShopLocationController;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('shops', [ShopLocationController::class, 'index']);
Route::get('shops/nearby', [ShopLocationController::class, 'nearby'])->middleware('throttle:60,1');
Route::post('webhooks/paymongo', PayMongoWebhookController::class)->name('webhooks.paymongo');
