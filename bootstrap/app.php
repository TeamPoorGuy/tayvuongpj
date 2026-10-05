<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            // Kiểm tra role customer, field_owner hoặc admin.
            'api.role' => \App\Http\Middleware\EnsureUserHasRole::class,
            // Kiểm tra tài khoản có đang bị khóa không.
            // Chặn thao tác quan trọng khi email chưa xác thực.
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            // kiểm tra chủ sân đã được xác thực chưa.
            'owner.approved' => \App\Http\Middleware\EnsureFieldOwnerIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\DomainException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => (object) $exception->errors,
            ], $exception->status);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Dữ liệu gửi lên chưa hợp lệ.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Bạn cần đăng nhập để tiếp tục.'], 401);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Bạn không có quyền thực hiện thao tác này.', 'code' => 'FORBIDDEN'], 403);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() ?: 'Không thể xử lý yêu cầu.',
            ], $exception->getStatusCode());
        });
    })->create();
