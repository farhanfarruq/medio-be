<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ShippingController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\DiscountController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/xendit', [WebhookController::class, 'xendit']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

Route::get('/categories', [CategoryController::class, 'index']);

Route::prefix('products')->group(function () {
    Route::get('/brands', [ProductController::class, 'brands']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{slug}', [ProductController::class, 'show']);
});

Route::prefix('shipping')->group(function () {
    Route::get('/provinces', [ShippingController::class, 'provinces']);
    Route::get('/cities', [ShippingController::class, 'cities']);
    Route::get('/districts', [ShippingController::class, 'districts']);
    Route::post('/cost', [ShippingController::class, 'cost']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/{id}/sync-payment', [OrderController::class, 'syncPayment']);
    });

    Route::prefix('addresses')->group(function () {
        Route::get('/', [ShippingController::class, 'getAddresses']);
        Route::post('/', [ShippingController::class, 'storeAddress']);
        Route::put('/{id}', [ShippingController::class, 'updateAddress']);
        Route::delete('/{id}', [ShippingController::class, 'destroyAddress']);
    });
});

