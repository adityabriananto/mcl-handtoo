<?php

use App\Http\Controllers\HandoverCancellationController;
use App\Http\Controllers\DataCruncherController;
use App\Http\Controllers\InboundOrderApiController;
use App\Http\Controllers\InboundOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/crunch', [DataCruncherController::class, 'crunch']);

Route::middleware([])->group(function () {
    Route::post('/fbl/fulfillment_order/cancel', [HandoverCancellationController::class, 'cancel']);
    Route::post('/fbl/inbound_order/create', [InboundOrderApiController::class, 'createInboundOrder']);
    Route::get('/fbl/inbound_order_detail/get', [InboundOrderApiController::class, 'getInboundOrderDetail']);
    Route::get('/fbl/inbound_orders/get', [InboundOrderApiController::class, 'getInboundOrders']);
    Route::post('/fbl/inbound_order/cancel', [InboundOrderApiController::class, 'cancel']);
});
