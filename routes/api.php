<?php

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\GraphQLController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::post('/graphql', GraphQLController::class)->middleware('iae.key');

Route::middleware('iae.key')->prefix('v1')->group(function (): void {
    Route::post('/graphql', GraphQLController::class);

    Route::get('/checkouts', [CheckoutController::class, 'index']);
    Route::post('/checkouts', [CheckoutController::class, 'store']);
    Route::get('/checkouts/{checkout}', [CheckoutController::class, 'show']);

    Route::get('/payment/methods', [PaymentController::class, 'methods']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments/{payment}/status', [PaymentController::class, 'status']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
});

Route::fallback(fn () => ApiResponse::error('Resource not found', null, 404))->middleware('iae.key');
