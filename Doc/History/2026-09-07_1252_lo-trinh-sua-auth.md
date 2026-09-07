# Lộ trình sửa auth theo thứ tự

- Ngày giờ: `2026-09-07 12:52` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Sanctum SPA auth, role, owner approval, rate limit và production hardening

## Mục tiêu

Chia các cải thiện auth thành từng bước độc lập, có thứ tự triển khai và tiêu chí kiểm thử rõ ràng để người dùng sửa lần lượt.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: auth session-cookie và ba role hoạt động, 13 test hiện có đều đạt; audit trước đó phát hiện owner pending chưa bị chặn, login/register chưa throttle và logout bị middleware active chặn.
- Ràng buộc/rủi ro: không đổi sang JWT; mỗi bước phải giữ tương thích React SPA và không khóa đường cập nhật hồ sơ của owner đang chờ duyệt.
- Quyết định và lý do: sửa authorization nghiệp vụ trước, sau đó vòng đời session, chống brute-force, cấu hình production, rồi mới bổ sung email/password reset/2FA.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi đề xuất cục bộ này.
- Hành vi trước/sau: chưa thay đổi mã; cung cấp thứ tự và phạm vi sửa.
- Điều không thay đổi: Sanctum cookie/session, cấu trúc ba role và API contract hiện tại.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: tham chiếu kết quả audit auth ngày `2026-09-07 12:42`; đề xuất chạy test backend và lint/build frontend sau mỗi bước.
- Kết quả: lộ trình gồm các bước có thể triển khai và kiểm thử độc lập.
- Việc chưa xác minh và lý do: chưa sửa hoặc chạy lại test vì người dùng yêu cầu hướng dẫn.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — kiến trúc và giới hạn dự án.
- Nguồn nội bộ: `Doc/History/2026-09-07_1242_ra-soat-auth-va-role.md` — kết quả audit auth gần nhất.
- Nguồn nội bộ: `routes/api.php`, `bootstrap/app.php`, `app/Services/AuthService.php`, `app/Providers/AppServiceProvider.php`, `frontend/src/context/AuthContext.tsx`, `tests/Feature/Api/LayeredApiTest.php` — vị trí dự kiến sửa và kiểm thử.
- Nguồn ngoài: [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) — Laravel, SPA session-cookie và CSRF; truy cập `2026-09-07`.
- Nguồn ngoài: [Laravel 13 RateLimiter API](https://api.laravel.com/docs/13.x/Illuminate/Cache/RateLimiter.html) — Laravel, rate limiting; truy cập `2026-09-07`.

## Việc tiếp theo

- [ ] Bước 1: chặn owner chưa được duyệt khỏi API vận hành.
- [ ] Bước 2: cho phép tài khoản bị khóa đăng xuất sạch.
- [ ] Bước 3: thêm rate limit cho login/register.
- [ ] Bước 4: gia cố seeder và cấu hình production.
- [ ] Bước 5: bổ sung email verification/password reset và 2FA nếu triển khai công khai.
