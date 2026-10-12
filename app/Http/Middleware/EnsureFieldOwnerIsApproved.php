<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFieldOwnerIsApproved
{
    /**
     * Chỉ cho chủ sân đã được admin duyệt vào khu vực quản lý.
     * Tài khoản chưa được duyệt sẽ quay về trang theo dõi hồ sơ đăng ký.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isApprovedOwner()) {
            return redirect()
                ->route('customer.owner-application.edit')
                ->with('error', 'Hồ sơ chủ sân của bạn chưa được quản trị viên phê duyệt.');
        }

        return $next($request);
    }
}
