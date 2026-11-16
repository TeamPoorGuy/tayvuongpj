<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

$frontend = fn (string $path) => redirect(rtrim(config('frontend.url'), '/').$path);

Route::get('/login', fn () => $frontend('/login'))->name('login');

// Liên kết trong email xác thực: không cần đăng nhập, bảo vệ bằng chữ ký URL + hash email.
Route::get('/email/verify/{id}/{hash}', function (string $id, string $hash) use ($frontend) {
    $user = User::find($id);

    if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
        return $frontend('/email-verified?status=invalid');
    }

    $user->markEmailAsVerified();

    return $frontend('/email-verified?status=success');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware('throttle:10,1')->prefix('auth/google')->group(function () use ($frontend) {
    Route::get('/redirect', fn () => Socialite::driver('google')->redirect());

    Route::get('/callback', function () use ($frontend) {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return $frontend('/login?error=google');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $google->getEmail())->first();

        // Luôn yêu cầu Google xác nhận email — kể cả khi liên kết vào tài khoản có sẵn
        // (nếu không, kẻ tấn công có thể chiếm tài khoản bằng email chưa được xác thực).
        if (! ($google->user['email_verified'] ?? false)) {
            return $frontend('/login?error=google');
        }

        if ($user && ! $user->is_active) {
            return $frontend('/login?error=disabled');
        }

        $isNewUser = ! $user;
        $user ??= new User(['name' => $google->getName() ?: $google->getEmail(), 'email' => $google->getEmail()]);
        $fill = ['google_id' => $google->getId(), 'avatar' => $user->avatar ?? $google->getAvatar()];
        if ($isNewUser || ! $user->terms_accepted_at) {
            $fill['terms_accepted_at'] = now();
        }
        $user->forceFill($fill);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified(); // save() nằm trong markEmailAsVerified
        }
        $user->save();

        Auth::login($user, true);
        request()->session()->regenerate();

        return $frontend($user->isAdmin() ? '/admin' : '/');
    });
});
