<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            return new JsonResponse([
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
