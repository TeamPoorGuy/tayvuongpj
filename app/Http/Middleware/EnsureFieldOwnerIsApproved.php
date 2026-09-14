<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFieldOwnerIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'message' => 'Chưa đăng nhập',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        if (! $user->isApprovedOwner()) {
            return new JsonResponse([
                'message' => 'Chủ sân chưa được xác thực',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
