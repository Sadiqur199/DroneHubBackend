<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TutorialController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SettingController;

/*
|--------------------------------------------------------------------------
| API Routes — DroneHub Australia (Prefix: /api/v1)
|--------------------------------------------------------------------------
*/

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'engine'    => 'Laravel 11 REST API',
        'database'  => 'MySQL (dron_project)',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Products & Catalog
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// Tutorials & Flight Academy
Route::get('/tutorials', [TutorialController::class, 'index']);

// Checkout & Orders
Route::post('/checkout', [OrderController::class, 'checkout']);

// Settings & Dynamic Banner
Route::get('/settings/banner', [SettingController::class, 'getBanner']);
Route::get('/settings', [SettingController::class, 'getSettings']);

// Admin Operations
Route::prefix('admin')->group(function () {
    // Products Management
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Tutorials Management
    Route::post('/tutorials', [TutorialController::class, 'store']);
    Route::delete('/tutorials/{id}', [TutorialController::class, 'destroy']);

    // Orders Management & Document Review
    Route::get('/orders', [OrderController::class, 'index']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Banner Settings Upload (Video / Image)
    Route::post('/settings/banner', [SettingController::class, 'updateBanner']);
});
