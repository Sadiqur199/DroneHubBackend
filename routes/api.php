<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TutorialController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\CategoryController;

/*
|--------------------------------------------------------------------------
| API Routes — DroneHub Australia (Prefix: /api/v1)
|--------------------------------------------------------------------------
|
| Public endpoints resolve the caller's customer group (guest => retail) and
| only ever return products & prices that group is entitled to see.
|
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
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth.token');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.token');
});

// Products & Catalog (group aware)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// Categories & Subcategories
Route::get('/categories', [CategoryController::class, 'index']);

// Tutorials & Flight Academy
Route::get('/tutorials', [TutorialController::class, 'index']);

// Checkout & Orders (signed in customers only)
Route::middleware('auth.token')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders/mine', [OrderController::class, 'mine']);
});

// Settings & Dynamic Banner
Route::get('/settings/banner', [SettingController::class, 'getBanner']);
Route::get('/settings', [SettingController::class, 'getSettings']);

// Admin Operations — signed in administrators only
Route::prefix('admin')->middleware(['auth.token', 'role.admin'])->group(function () {
    // Products Management
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::patch('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/import', [ProductController::class, 'import']);

    // Categories Management
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    Route::post('/categories/{id}/subcategories', [CategoryController::class, 'storeSubcategory']);
    Route::delete('/categories/{id}/subcategories/{slug}', [CategoryController::class, 'destroySubcategory']);

    // Tutorials Management
    Route::post('/tutorials', [TutorialController::class, 'store']);
    Route::delete('/tutorials/{id}', [TutorialController::class, 'destroy']);

    // Orders Management & Document Review
    Route::get('/orders', [OrderController::class, 'index']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Banner Settings Upload (Video / Image)
    Route::post('/settings/banner', [SettingController::class, 'updateBanner']);
});
