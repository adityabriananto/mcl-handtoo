<?php

use App\Http\Controllers\HandoverCancellationController;
use App\Http\Controllers\DataCruncherController;
use App\Http\Controllers\InboundOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/crunch', [DataCruncherController::class, 'crunch']);

Route::middleware(['api.key'])->group(function () {
    Route::post('/cancel', [HandoverCancellationController::class, 'cancel']);
    Route::post('/CreateInboundOrder', [InboundOrderController::class, 'api']);
});
