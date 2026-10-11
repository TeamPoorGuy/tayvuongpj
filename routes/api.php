<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\FieldSuggestionController;
use App\Http\Controllers\Api\V1\MatchmakingController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\Customer;
use App\Http\Controllers\Api\V1\FieldOwner;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'SportHub API',
    'version' => 'v1',
    'api_base_url' => url('/api/v1'),
    'health_url' => url('/up'),
]));

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:login']);
        Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:register']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware(['guest', 'throttle:forgot']);
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware(['guest', 'throttle:forgot']);
        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification');
            Route::get('/me', [AuthController::class, 'me'])
        ->middleware('active');
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::get('/home', [CatalogController::class, 'home']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/field-types', [CatalogController::class, 'fieldTypes']);
    Route::get('/fields', [CatalogController::class, 'fields']);
    Route::get('/fields/{slug}', [CatalogController::class, 'field']);
    Route::get('/fields/{field}/available-slots', [CatalogController::class, 'availableSlots']);
    Route::get('/fields/{slug}/suggestions', [FieldSuggestionController::class, 'suggestions']);
    Route::get('/matches', [MatchmakingController::class, 'index']);
    Route::get('/matches/{id}', [MatchmakingController::class, 'show']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::prefix('customer')->middleware('api.role:customer')->group(function () {
            Route::get('/bookings', [Customer\BookingController::class, 'index']);
            Route::post('/bookings', [Customer\BookingController::class, 'store'])->middleware(['verified', 'throttle:writes']);
            Route::patch('/bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel'])->middleware('throttle:writes');
            Route::post('/reviews', [Customer\ReviewController::class, 'store']);
            Route::get('/profile', [Customer\ProfileController::class, 'show']);
            Route::put('/profile', [Customer\ProfileController::class, 'update']);
            Route::put('/profile/password', [Customer\ProfileController::class, 'updatePassword'])->middleware('throttle:writes');
            Route::get('/owner-application', [Customer\OwnerApplicationController::class, 'show']);
            Route::post('/owner-application', [Customer\OwnerApplicationController::class, 'store'])->middleware(['verified', 'throttle:writes']);
            Route::get('/owner-application/documents/{document}', [Customer\OwnerApplicationController::class, 'document']);

            // Phase 4: Waitlist & Matchmaking
            Route::get('/waitlist', [Customer\WaitlistController::class, 'index']);
            Route::post('/waitlist', [Customer\WaitlistController::class, 'store'])->middleware(['verified', 'throttle:writes']);
            Route::post('/waitlist/{id}/accept', [Customer\WaitlistController::class, 'accept'])->middleware(['verified', 'throttle:writes']);
            Route::delete('/waitlist/{id}', [Customer\WaitlistController::class, 'cancel'])->middleware(['verified', 'throttle:writes']);
            Route::post('/matches', [MatchmakingController::class, 'store'])->middleware(['verified', 'throttle:writes']);
            Route::post('/matches/{id}/join', [MatchmakingController::class, 'join'])->middleware(['verified', 'throttle:writes']);
            Route::patch('/matches/{id}/participants/{participantId}', [MatchmakingController::class, 'updateParticipant'])->middleware(['verified', 'throttle:writes']);
            Route::delete('/matches/{id}/participation', [MatchmakingController::class, 'leave'])->middleware('throttle:writes');
            Route::delete('/matches/{id}', [MatchmakingController::class, 'cancel'])->middleware(['verified', 'throttle:writes']);
        });

        Route::prefix('field-owner')
    // Chỉ chủ sân đã được admin duyệt mới vào được khu này.
    // (đơn xin làm chủ sân nằm ở customer/owner-application, không cần duyệt để xem/nộp)
    ->middleware('owner.approved')
    ->group(function () {
        Route::post('/check-in', [FieldOwner\CheckInController::class, 'checkIn'])->middleware('throttle:writes');
        Route::patch('/bookings/{booking}/check-in', [FieldOwner\CheckInController::class, 'checkInBooking'])->middleware('throttle:writes');
        Route::get(
            '/profile',
            [FieldOwner\ProfileController::class, 'show']
        );

        Route::put(
            '/profile',
            [FieldOwner\ProfileController::class, 'update']
        );

        Route::get(
            '/dashboard',
            [FieldOwner\DashboardController::class, 'show']
        );

        Route::get(
            '/fields',
            [FieldOwner\FieldController::class, 'index']
        );

        Route::post(
            '/fields',
            [FieldOwner\FieldController::class, 'store']
        );

        Route::put(
            '/fields/{field}',
            [FieldOwner\FieldController::class, 'update']
        );

        Route::delete('/fields/{field}', [FieldOwner\FieldController::class, 'destroy']);
        Route::post('/fields/{field}/images', [FieldOwner\FieldController::class, 'addImages'])->middleware('throttle:writes');
        Route::delete('/fields/{field}/images/{image}', [FieldOwner\FieldController::class, 'deleteImage']);
        Route::patch('/fields/{field}/images/{image}/primary', [FieldOwner\FieldController::class, 'primaryImage']);
        Route::post('/fields/{field}/slots', [FieldOwner\SlotController::class, 'store']);
        Route::put('/fields/{field}/slots/{slot}', [FieldOwner\SlotController::class, 'update']);
        Route::delete('/fields/{field}/slots/{slot}', [FieldOwner\SlotController::class, 'destroy']);

        Route::patch(
            '/fields/{field}/toggle',
            [FieldOwner\FieldController::class, 'toggle']
        );

        Route::get(
            '/bookings',
            [FieldOwner\BookingController::class, 'index']
        );

        Route::patch(
            '/bookings/{booking}/status',
            [FieldOwner\BookingController::class, 'updateStatus']
        );

        Route::get(
            '/reviews',
            [FieldOwner\ReviewController::class, 'index']
        );
    });

        Route::prefix('admin')->middleware('api.role:admin')->group(function () {
            Route::get('/dashboard', [Admin\DashboardController::class, 'show']);
            Route::get('/users', [Admin\UserController::class, 'index']);
            Route::get('/users/{user}/trust-score-logs', [Admin\UserController::class, 'trustScoreLogs']);
            Route::patch('/users/{user}/toggle', [Admin\UserController::class, 'toggle']);
            Route::get('/field-owners', [Admin\FieldOwnerController::class, 'index']);
            Route::get('/field-owners/{profile}/documents/{document}', [Admin\FieldOwnerController::class, 'document']);
            Route::patch('/field-owners/{profile}/verify', [Admin\FieldOwnerController::class, 'verify']);
            Route::get('/fields', [Admin\FieldController::class, 'index']);
            Route::patch('/fields/{field}/verify', [Admin\FieldController::class, 'verify']);
            Route::get('/bookings', [Admin\BookingController::class, 'index']);
            Route::get('/matches', [Admin\MatchController::class, 'index']);
            Route::delete('/matches/{id}', [Admin\MatchController::class, 'cancel']);
            Route::get('/reviews', [Admin\ReviewController::class, 'index']);
            Route::patch('/reviews/{review}/toggle', [Admin\ReviewController::class, 'toggle']);
            Route::get('/categories', [Admin\CategoryController::class, 'index']);
            Route::post('/categories', [Admin\CategoryController::class, 'storeCategory']);
            Route::post('/field-types', [Admin\CategoryController::class, 'storeFieldType']);
            Route::put('/categories/{category}', [Admin\CategoryController::class, 'updateCategory']);
            Route::put('/field-types/{fieldType}', [Admin\CategoryController::class, 'updateFieldType']);
        });
    });
});
