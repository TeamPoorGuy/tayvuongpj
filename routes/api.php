<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\FieldSuggestionController;
use App\Http\Controllers\Api\V1\MatchmakingController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\BookingProofController;
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
    Route::get('/fields/locations', [CatalogController::class, 'locations']);
    Route::get('/fields/{slug}', [CatalogController::class, 'field']);
    Route::get('/fields/{field}/available-slots', [CatalogController::class, 'availableSlots']);
    Route::get('/fields/{field}/schedule', [CatalogController::class, 'schedule']);
    Route::get('/fields/{slug}/suggestions', [FieldSuggestionController::class, 'suggestions']);
    Route::get('/settings/public', [SettingController::class, 'publicSettings']);
    Route::get('/matches', [MatchmakingController::class, 'index']);
    Route::get('/matches/{id}', [MatchmakingController::class, 'show']);


    // Ngân hàng báo có tiền vào tài khoản nền tảng (thu hộ).
    Route::post('/webhooks/bank', \App\Http\Controllers\Api\V1\Webhook\BankWebhookController::class)->middleware('throttle:120,1');

    Route::post('/contact', [Customer\ComplaintController::class, 'store'])->middleware('throttle:writes');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/bookings/{booking}/payment-proof', [BookingProofController::class, 'show']);
        Route::get('/me/badges', \App\Http\Controllers\Api\V1\BadgeController::class);
        // Ví SportHub: dùng chung cho khách và chủ sân.
        Route::get('/wallet', [\App\Http\Controllers\Api\V1\WalletController::class, 'show']);
        Route::get('/wallet/withdrawals', [\App\Http\Controllers\Api\V1\WalletController::class, 'withdrawals']);
        Route::post('/wallet/withdrawals', [\App\Http\Controllers\Api\V1\WalletController::class, 'withdraw'])->middleware('throttle:writes');
        Route::post('/wallet/topups', [\App\Http\Controllers\Api\V1\WalletController::class, 'topup'])->middleware('throttle:writes');
        Route::get('/complaint-attachments/{attachment}', [\App\Http\Controllers\Api\V1\ComplaintAttachmentController::class, 'show']);

        Route::prefix('customer')->middleware('api.role:customer')->group(function () {
            Route::get('/complaints', [Customer\ComplaintController::class, 'index']);
            Route::post('/complaints', [Customer\ComplaintController::class, 'store'])->middleware('throttle:writes');
            Route::post('/complaints/{complaint}/respond', [Customer\ComplaintController::class, 'respond'])->middleware('throttle:writes');
            Route::get('/bookings', [Customer\BookingController::class, 'index']);
            Route::post('/bookings', [Customer\BookingController::class, 'store'])->middleware(['verified', 'throttle:writes']);
            Route::post('/bookings/recurring', [Customer\BookingController::class, 'storeRecurring'])->middleware(['verified', 'throttle:writes']);
            Route::post('/bookings/recurring/{groupId}/cancel', [Customer\BookingController::class, 'cancelRecurring'])->middleware('throttle:writes');
            Route::get('/bookings/{booking}', [Customer\BookingController::class, 'show']);
            Route::get('/payments/{payment}', [Customer\PaymentController::class, 'show']);
            Route::patch('/payments/{payment}', [Customer\PaymentController::class, 'update'])->middleware('throttle:writes');
            Route::post('/payments/{payment}/cancel', [Customer\PaymentController::class, 'cancel'])->middleware('throttle:writes');
            Route::post('/payments/{payment}/use-wallet', [Customer\PaymentController::class, 'useWallet'])->middleware('throttle:writes');
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

        // Phase 6: Field closures / holidays
        Route::get('/fields/{field}/closures', [FieldOwner\FieldClosureController::class, 'index']);
        Route::post('/fields/{field}/closures', [FieldOwner\FieldClosureController::class, 'store'])->middleware('throttle:writes');
        Route::delete('/fields/{field}/closures/{closure}', [FieldOwner\FieldClosureController::class, 'destroy'])->middleware('throttle:writes');

        Route::patch(
            '/fields/{field}/toggle',
            [FieldOwner\FieldController::class, 'toggle']
        );

        Route::get(
            '/bookings',
            [FieldOwner\BookingController::class, 'index']
        );

        Route::patch('/bookings/{booking}/no-show', [FieldOwner\BookingController::class, 'noShow'])->middleware('throttle:writes');

        // Khiếu nại hai chiều: chủ sân khiếu nại khách và phản hồi khi bị khách khiếu nại.
        Route::get('/complaints', [FieldOwner\ComplaintController::class, 'index']);
        Route::post('/complaints', [FieldOwner\ComplaintController::class, 'store'])->middleware('throttle:writes');
        Route::post('/complaints/{complaint}/respond', [FieldOwner\ComplaintController::class, 'respond'])->middleware('throttle:writes');

        Route::patch(
            '/bookings/{booking}/status',
            [FieldOwner\BookingController::class, 'updateStatus']
        );

        Route::get(
            '/reviews',
            [FieldOwner\ReviewController::class, 'index']
        );
        Route::patch('/reviews/{review}/reply', [FieldOwner\ReviewController::class, 'reply'])->middleware('throttle:writes');

        Route::get('/statements', [FieldOwner\StatementController::class, 'index']);
        Route::get('/statements/current-summary', [FieldOwner\StatementController::class, 'currentSummary']);
        Route::get('/statements/{statement}/export-csv', [FieldOwner\StatementController::class, 'exportBookingsCsv']);
        Route::get('/statements/{statement}', [FieldOwner\StatementController::class, 'show']);
    });

        Route::prefix('admin')->middleware('api.role:admin')->group(function () {
            Route::get('/dashboard', [Admin\DashboardController::class, 'show']);
            Route::get('/payments', [Admin\PaymentController::class, 'index']);
            Route::post('/payments/{payment}/confirm', [Admin\PaymentController::class, 'confirm'])->middleware('throttle:writes');
            Route::get('/withdrawals', [Admin\WithdrawalController::class, 'index']);
            Route::post('/withdrawals/{withdrawal}/paid', [Admin\WithdrawalController::class, 'paid'])->middleware('throttle:writes');
            Route::post('/withdrawals/{withdrawal}/reject', [Admin\WithdrawalController::class, 'reject'])->middleware('throttle:writes');
            Route::get('/users', [Admin\UserController::class, 'index']);
            Route::get('/users/{user}/detail', [Admin\UserController::class, 'detail']);
            Route::get('/users/{user}/trust-score-logs', [Admin\UserController::class, 'trustScoreLogs']);
            Route::patch('/users/{user}/toggle', [Admin\UserController::class, 'toggle']);
            Route::get('/settings', [Admin\SettingController::class, 'index']);
            Route::put('/settings', [Admin\SettingController::class, 'update'])->middleware('throttle:writes');
            Route::get('/logs', [Admin\LogController::class, 'index']);
            Route::get('/field-owners', [Admin\FieldOwnerController::class, 'index']);
            Route::get('/field-owners/{profile}/documents/{document}', [Admin\FieldOwnerController::class, 'document']);
            Route::patch('/field-owners/{profile}/verify', [Admin\FieldOwnerController::class, 'verify']);
            Route::get('/fields', [Admin\FieldController::class, 'index']);
            Route::patch('/fields/{field}/verify', [Admin\FieldController::class, 'verify']);
            Route::get('/bookings', [Admin\BookingController::class, 'index']);
            Route::get('/bookings/export-csv', [Admin\BookingController::class, 'exportCsv']);
            Route::post('/bookings/{booking}/cancel', [Admin\BookingController::class, 'cancel'])->middleware('throttle:writes');
            Route::get('/matches', [Admin\MatchController::class, 'index']);
            Route::delete('/matches/{id}', [Admin\MatchController::class, 'cancel']);
            Route::get('/reviews', [Admin\ReviewController::class, 'index']);
            Route::patch('/reviews/{review}/toggle', [Admin\ReviewController::class, 'toggle']);
            Route::get('/categories', [Admin\CategoryController::class, 'index']);
            Route::post('/categories', [Admin\CategoryController::class, 'storeCategory']);
            Route::post('/field-types', [Admin\CategoryController::class, 'storeFieldType']);
            Route::put('/categories/{category}', [Admin\CategoryController::class, 'updateCategory']);
            Route::put('/field-types/{fieldType}', [Admin\CategoryController::class, 'updateFieldType']);

            Route::get('/complaints', [Admin\ComplaintController::class, 'index']);
            Route::get('/complaints/{complaint}', [Admin\ComplaintController::class, 'show']);
            Route::patch('/complaints/{complaint}/handle', [Admin\ComplaintController::class, 'handle'])->middleware('throttle:writes');
            Route::get('/statements/export-csv', [Admin\StatementController::class, 'exportCsv']);
            Route::get('/statements', [Admin\StatementController::class, 'index']);
            Route::get('/statements/{statement}/export-csv', [Admin\StatementController::class, 'exportBookingsCsv']);
            Route::get('/statements/{statement}', [Admin\StatementController::class, 'show']);
            Route::post('/statements/generate', [Admin\StatementController::class, 'generate'])->middleware('throttle:writes');
        });
    });
});
