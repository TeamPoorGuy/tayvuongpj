<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return new JsonResponse([
                'message' => 'Vui lòng xác thực email trước khi thực hiện thao tác này.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        return $next($request);
    }
}
