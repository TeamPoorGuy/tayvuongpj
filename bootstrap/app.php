<?php

use App\Http\Middleware\EnsureFieldOwnerIsApproved;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'owner.approved' => EnsureFieldOwnerIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Dùng trình xử lý exception mặc định của Laravel.
    })
    ->create();
