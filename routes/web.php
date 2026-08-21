<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\FieldOwner;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes & Authentication
|--------------------------------------------------------------------------
*/

// Public Customer Routes
Route::get('/', [Customer\HomeController::class, 'index'])->name('home');
Route::get('/fields', [Customer\FieldController::class, 'index'])->name('customer.fields.index');
Route::get('/fields/{slug}', [Customer\FieldController::class, 'show'])->name('customer.fields.show');

// Auth Routes (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

// Authenticated Logout
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Customer Protected Routes (role: customer)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    // Bookings
    Route::get('/bookings', [Customer\BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [Customer\BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{id}/cancel', [Customer\BookingController::class, 'cancel'])->name('bookings.cancel');

    // Reviews
    Route::post('/reviews', [Customer\ReviewController::class, 'store'])->name('reviews.store');

    // Profile
    Route::get('/profile', [Customer\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Customer\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Customer\ProfileController::class, 'updatePassword'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Field Owner Protected Routes (role: field_owner)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:field_owner'])->prefix('field-owner')->name('field-owner.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [FieldOwner\DashboardController::class, 'index'])->name('dashboard');

    // Fields Management
    Route::get('/fields', [FieldOwner\FieldController::class, 'index'])->name('fields.index');
    Route::get('/fields/create', [FieldOwner\FieldController::class, 'create'])->name('fields.create');
    Route::post('/fields', [FieldOwner\FieldController::class, 'store'])->name('fields.store');
    Route::get('/fields/{id}/edit', [FieldOwner\FieldController::class, 'edit'])->name('fields.edit');
    Route::put('/fields/{id}', [FieldOwner\FieldController::class, 'update'])->name('fields.update');
    Route::post('/fields/{id}/toggle', [FieldOwner\FieldController::class, 'toggleStatus'])->name('fields.toggle');

    // Bookings Management
    Route::get('/bookings', [FieldOwner\BookingController::class, 'index'])->name('bookings.index');
    Route::put('/bookings/{id}', [FieldOwner\BookingController::class, 'updateStatus'])->name('bookings.update');

    // Reviews View
    Route::get('/reviews', [FieldOwner\ReviewController::class, 'index'])->name('reviews.index');

    // Profile
    Route::get('/profile', [FieldOwner\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [FieldOwner\ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Admin Protected Routes (role: admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // User Management
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users/{id}/toggle', [Admin\UserController::class, 'toggleStatus'])->name('users.toggle');

    // Field Owner Verification
    Route::get('/field-owners', [Admin\FieldOwnerController::class, 'index'])->name('field-owners.index');
    Route::post('/field-owners/{id}/verify', [Admin\FieldOwnerController::class, 'verify'])->name('field-owners.verify');

    // Categories & Types
    Route::get('/sport-categories', [Admin\SportCategoryController::class, 'index'])->name('sport-categories.index');
    Route::post('/sport-categories', [Admin\SportCategoryController::class, 'storeCategory'])->name('sport-categories.store');
    Route::post('/field-types', [Admin\SportCategoryController::class, 'storeType'])->name('field-types.store');

    // Fields Management & Verification
    Route::get('/fields', [Admin\FieldController::class, 'index'])->name('fields.index');
    Route::post('/fields/{id}/verify', [Admin\FieldController::class, 'verify'])->name('fields.verify');

    // Bookings Management
    Route::get('/bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');

    // Reviews Management
    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{id}/toggle', [Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');
});

/*
|--------------------------------------------------------------------------
| AJAX Public / Shared Endpoints
|--------------------------------------------------------------------------
*/
Route::get('/api/fields/{id}/check-slots', [Customer\FieldController::class, 'checkAvailableSlots'])->name('api.fields.check-slots');
