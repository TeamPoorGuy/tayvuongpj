<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            return new JsonResponse([
                'message' => 'Tài khoản của bạn đã bị khóa.',
                'code' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        return $next($request);
    }
}
