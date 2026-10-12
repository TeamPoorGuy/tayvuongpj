<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\FieldOwner;
use App\Models\MatchRequest;
use Illuminate\Support\Facades\Route;

Route::model('match', MatchRequest::class);

// Trang công khai: MVC controller lấy dữ liệu từ Eloquent model rồi trả Blade view.
Route::get('/', [Customer\HomeController::class, 'index'])->name('home');
Route::get('/fields', [Customer\FieldController::class, 'index'])->name('customer.fields.index');
Route::get('/fields/{slug}', [Customer\FieldController::class, 'show'])->name('customer.fields.show');
Route::get('/ajax/fields/{field}/check-slots', [Customer\FieldController::class, 'checkAvailableSlots'])
    ->name('ajax.fields.check-slots');
Route::get('/ajax/fields/{field}/suggestions', [Customer\FieldController::class, 'getSuggestions'])
    ->name('ajax.fields.suggestions');

Route::get('/matches', [Customer\MatchController::class, 'index'])->name('customer.matches.index');
Route::get('/matches/{match}', [Customer\MatchController::class, 'show'])->name('customer.matches.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:6,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Người dùng đăng ký mặc định là customer. Hồ sơ chủ sân là một quy trình duyệt riêng.
Route::middleware(['auth', 'active', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/bookings', [Customer\BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [Customer\BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/reviews', [Customer\ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/waitlists', [Customer\WaitlistController::class, 'index'])->name('waitlists.index');
    Route::post('/waitlists', [Customer\WaitlistController::class, 'store'])->name('waitlists.store');
    Route::post('/waitlists/{waitlist}/accept', [Customer\WaitlistController::class, 'accept'])->name('waitlists.accept');
    Route::post('/waitlists/{waitlist}/cancel', [Customer\WaitlistController::class, 'cancel'])->name('waitlists.cancel');

    Route::get('/matches-create', [Customer\MatchController::class, 'create'])->name('matches.create');
    Route::post('/matches', [Customer\MatchController::class, 'store'])->name('matches.store');
    Route::post('/matches/{match}/join', [Customer\MatchController::class, 'join'])->name('matches.join');
    Route::post('/matches/{match}/leave', [Customer\MatchController::class, 'leave'])->name('matches.leave');
    Route::post('/matches/{match}/participants/{participant}', [Customer\MatchController::class, 'updateParticipant'])->name('matches.participants.update');
    Route::post('/matches/{match}/cancel', [Customer\MatchController::class, 'cancel'])->name('matches.cancel');

    Route::get('/profile', [Customer\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Customer\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Customer\ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/owner-application', [Customer\OwnerApplicationController::class, 'edit'])
        ->name('owner-application.edit');
    Route::post('/owner-application', [Customer\OwnerApplicationController::class, 'store'])
        ->name('owner-application.store');
});

// Quyền chủ sân được suy ra từ hồ sơ approved, không đổi role customer trong database.
Route::middleware(['auth', 'active', 'owner.approved'])->prefix('field-owner')->name('field-owner.')->group(function () {
    Route::get('/dashboard', [FieldOwner\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/fields', [FieldOwner\FieldController::class, 'index'])->name('fields.index');
    Route::get('/fields/create', [FieldOwner\FieldController::class, 'create'])->name('fields.create');
    Route::post('/fields', [FieldOwner\FieldController::class, 'store'])->name('fields.store');
    Route::get('/fields/{field}/edit', [FieldOwner\FieldController::class, 'edit'])->name('fields.edit');
    Route::put('/fields/{field}', [FieldOwner\FieldController::class, 'update'])->name('fields.update');
    Route::post('/fields/{field}/toggle', [FieldOwner\FieldController::class, 'toggle'])->name('fields.toggle');

    Route::get('/bookings', [FieldOwner\BookingController::class, 'index'])->name('bookings.index');
    Route::put('/bookings/{booking}', [FieldOwner\BookingController::class, 'updateStatus'])->name('bookings.update');
    Route::post('/bookings/check-in', [FieldOwner\BookingController::class, 'checkIn'])->name('bookings.check-in');
    Route::get('/reviews', [FieldOwner\ReviewController::class, 'index'])->name('reviews.index');

    Route::get('/profile', [FieldOwner\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [FieldOwner\ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');

    Route::get('/field-owners', [Admin\FieldOwnerController::class, 'index'])->name('field-owners.index');
    Route::post('/field-owners/{profile}/verify', [Admin\FieldOwnerController::class, 'verify'])->name('field-owners.verify');

    Route::get('/sport-categories', [Admin\SportCategoryController::class, 'index'])->name('sport-categories.index');
    Route::post('/sport-categories', [Admin\SportCategoryController::class, 'storeCategory'])->name('sport-categories.store');
    Route::post('/field-types', [Admin\SportCategoryController::class, 'storeType'])->name('field-types.store');

    Route::get('/fields', [Admin\FieldController::class, 'index'])->name('fields.index');
    Route::post('/fields/{field}/verify', [Admin\FieldController::class, 'verify'])->name('fields.verify');

    Route::get('/bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/toggle', [Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');

    Route::get('/matches', [Admin\MatchController::class, 'index'])->name('matches.index');
    Route::post('/matches/{match}/cancel', [Admin\MatchController::class, 'cancel'])->name('matches.cancel');
});
