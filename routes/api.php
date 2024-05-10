<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::middleware(['throttle:api'])->group(function() {
    Route::get('v1/health-check', \App\Http\Controllers\Api\V1\HealthCheckController::class)->name('health-check');
});
