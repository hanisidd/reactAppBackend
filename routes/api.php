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
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\UserAuthController;

// Public Store Endpoints
Route::get('/store/products', [StorefrontController::class, 'getProducts']);
Route::get('/store/products/{id}', [StorefrontController::class, 'getProduct']);
Route::get('/store/categories', [StorefrontController::class, 'getCategories']);
Route::get('/store/checkout-settings', [StorefrontController::class, 'getCheckoutSettings']);
Route::post('/store/promo/validate', [StorefrontController::class, 'validatePromoCode']);
Route::post('/store/checkout', [StorefrontController::class, 'checkout']);

// Public User Authentication
Route::post('/user/register', [UserAuthController::class, 'register']);
Route::post('/user/login', [UserAuthController::class, 'login']);

// Authenticated User Endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/profile', [UserAuthController::class, 'profile']);
    Route::put('/user/profile', [UserAuthController::class, 'updateProfile']);
});



Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/store/public-settings', [SettingController::class, 'getPublicSettings']);
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
        Route::get('/settings/email-template', [SettingController::class, 'getEmailTemplate']);
        Route::post('/settings/email-template', [SettingController::class, 'updateEmailTemplate']);
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);
        Route::get('/promos', [PromoCodeController::class, 'index']);
        Route::post('/promos', [PromoCodeController::class, 'store']);
        Route::put('/promos/{id}', [PromoCodeController::class, 'update']);
        Route::delete('/promos/{id}', [PromoCodeController::class, 'destroy']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::patch('/orders/{id}/toggle-status', [OrderController::class, 'toggleStatus']);
        Route::post('/orders/{id}/send-email', [OrderController::class, 'sendProductEmail']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });
});