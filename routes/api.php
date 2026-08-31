<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PaymentController;

Route::post('/payments/{orderId}/initiate', [PaymentController::class, 'initiate']);
Route::get('/payments/{gateway}/verify', [PaymentController::class, 'verify']);
Route::post('/payments/{gateway}/callback', [PaymentController::class, 'callback']);
// Public Storefront Routes
Route::get('/public-settings', [SettingController::class, 'getPublicSettings']);
Route::get('/products', [StorefrontController::class, 'getProducts']);
Route::get('/products/featured', [StorefrontController::class, 'getFeaturedProducts']);
Route::get('/products/{id}', [StorefrontController::class, 'getProduct']);
Route::get('/categories', [StorefrontController::class, 'getCategories']);
Route::get('/checkout-settings', [StorefrontController::class, 'getCheckoutSettings']);
Route::post('/promo/validate', [StorefrontController::class, 'validatePromoCode']);
Route::post('/checkout', [StorefrontController::class, 'checkout']);
Route::post('/contact', [ContactController::class, 'storeMessage']);

// Public User Authentication
Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);

// Authenticated User Endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserAuthController::class, 'profile']);
    Route::put('/profile', [UserAuthController::class, 'updateProfile']);
    Route::post('/profile', [UserAuthController::class, 'updateProfile']);
    Route::get('/orders', [UserAuthController::class, 'orders']);
});

// Admin Control Panel
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/admins', [AdminController::class, 'index']);
        Route::post('/admins', [AdminController::class, 'store']);
        Route::put('/admins/{id}', [AdminController::class, 'update']);
        Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
        Route::post('/profile', [AdminController::class, 'updateProfile']);

        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products', ProductController::class);
        Route::patch('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);

        Route::get('/contact-messages', [ContactMessageController::class, 'index']);
        Route::patch('/contact-messages/{id}/toggle-read', [ContactMessageController::class, 'toggleRead']);
        Route::delete('/contact-messages/{id}', [ContactMessageController::class, 'destroy']);

        Route::get('/promos', [PromoCodeController::class, 'index']);
        Route::post('/promos', [PromoCodeController::class, 'store']);
        Route::put('/promos/{id}', [PromoCodeController::class, 'update']);
        Route::delete('/promos/{id}', [PromoCodeController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/send-email', [OrderController::class, 'sendProductEmail']);

        Route::get('/dashboard', [DashboardController::class, 'index']);


    });
});