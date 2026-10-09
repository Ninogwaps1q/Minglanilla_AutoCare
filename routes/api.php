<?php

use App\Http\Controllers\Api\ShopLocationController;
use Illuminate\Support\Facades\Route;

Route::get('shops', [ShopLocationController::class, 'index']);
Route::get('shops/nearby', [ShopLocationController::class, 'nearby'])->middleware('throttle:60,1');
