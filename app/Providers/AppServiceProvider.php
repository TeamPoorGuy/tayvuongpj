<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->max(72)->letters()->numbers());

        RateLimiter::for('api', fn(Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn(Request $r) => Limit::perMinute(5)->by(Str::lower($r->input('email')) . '|' . $r->ip()));
        RateLimiter::for('register', fn(Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('forgot', fn(Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('verification', fn(Request $r) => Limit::perMinute(2)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('writes', fn(Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
    }
}
