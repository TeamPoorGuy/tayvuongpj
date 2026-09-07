<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureFieldOwnerIsApproved
{
   // hàm chạy trước khi request được đưa vô controller
   public function handle(Request $request, Closure $next): Response
   {
    // lấy người dùng
    $user=$request->user();
    if(!$user)
        {
            return response()->json([
                'message' => 'Chưa đăng nhập',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }
    // lấy hồ sơ chủ sân
    $profile=$user->fieldOwnerProfile;
    if(!$profile||$profile-> verified !=='approved')
        {
            return response()->json([
                'message' => 'Chủ sân chưa được xác thực',
                'code' => 'FORBIDDEN',
            ], 403);
        }
    return $next($request);
   }
}
?>