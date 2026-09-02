<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\Customer;
use App\Http\Controllers\Api\V1\FieldOwner;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'SportHub API',
    'version' => 'v1',
    'api_base_url' => url('/api/v1'),
    'health_url' => url('/up'),
]));

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
        Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::get('/home', [CatalogController::class, 'home']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/field-types', [CatalogController::class, 'fieldTypes']);
    Route::get('/fields', [CatalogController::class, 'fields']);
    Route::get('/fields/{slug}', [CatalogController::class, 'field']);
    Route::get('/fields/{field}/available-slots', [CatalogController::class, 'availableSlots']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::prefix('customer')->middleware('api.role:customer')->group(function () {
            Route::get('/bookings', [Customer\BookingController::class, 'index']);
            Route::post('/bookings', [Customer\BookingController::class, 'store']);
            Route::patch('/bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel']);
            Route::post('/reviews', [Customer\ReviewController::class, 'store']);
            Route::get('/profile', [Customer\ProfileController::class, 'show']);
            Route::put('/profile', [Customer\ProfileController::class, 'update']);
            Route::put('/profile/password', [Customer\ProfileController::class, 'updatePassword']);
        });

        Route::prefix('field-owner')->middleware('api.role:field_owner')->group(function () {
            Route::get('/dashboard', [FieldOwner\DashboardController::class, 'show']);
            Route::get('/fields', [FieldOwner\FieldController::class, 'index']);
            Route::post('/fields', [FieldOwner\FieldController::class, 'store']);
            Route::put('/fields/{field}', [FieldOwner\FieldController::class, 'update']);
            Route::patch('/fields/{field}/toggle', [FieldOwner\FieldController::class, 'toggle']);
            Route::get('/bookings', [FieldOwner\BookingController::class, 'index']);
            Route::patch('/bookings/{booking}/status', [FieldOwner\BookingController::class, 'updateStatus']);
            Route::get('/reviews', [FieldOwner\ReviewController::class, 'index']);
            Route::get('/profile', [FieldOwner\ProfileController::class, 'show']);
            Route::put('/profile', [FieldOwner\ProfileController::class, 'update']);
        });

        Route::prefix('admin')->middleware('api.role:admin')->group(function () {
            Route::get('/dashboard', [Admin\DashboardController::class, 'show']);
            Route::get('/users', [Admin\UserController::class, 'index']);
            Route::patch('/users/{user}/toggle', [Admin\UserController::class, 'toggle']);
            Route::get('/field-owners', [Admin\FieldOwnerController::class, 'index']);
            Route::patch('/field-owners/{profile}/verify', [Admin\FieldOwnerController::class, 'verify']);
            Route::get('/fields', [Admin\FieldController::class, 'index']);
            Route::patch('/fields/{field}/verify', [Admin\FieldController::class, 'verify']);
            Route::get('/bookings', [Admin\BookingController::class, 'index']);
            Route::get('/reviews', [Admin\ReviewController::class, 'index']);
            Route::patch('/reviews/{review}/toggle', [Admin\ReviewController::class, 'toggle']);
            Route::get('/categories', [Admin\CategoryController::class, 'index']);
            Route::post('/categories', [Admin\CategoryController::class, 'storeCategory']);
            Route::post('/field-types', [Admin\CategoryController::class, 'storeFieldType']);
        });
    });
});
