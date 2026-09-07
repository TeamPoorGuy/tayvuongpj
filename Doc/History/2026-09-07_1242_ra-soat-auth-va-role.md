# Rà soát xác thực và phân quyền

- Ngày giờ: `2026-09-07 12:42` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Laravel Sanctum, session cookie, CSRF, role và middleware

## Mục tiêu

Đánh giá hệ thống đăng nhập hiện tại, cách chia role, công nghệ đang dùng, việc có cần token hay không và các điểm nên cải thiện.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: tài liệu dự án mô tả React SPA xác thực với Laravel Sanctum bằng cookie phiên `web`, kết hợp CSRF, middleware trạng thái tài khoản và role.
- Ràng buộc/rủi ro: cần đối chiếu mã nguồn thực tế, không chỉ dựa vào tài liệu; lần rà soát này không tự ý sửa auth.
- Quyết định và lý do: kiểm tra route, middleware, controller/request/service, model/migration và frontend client/auth context trước khi kết luận.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi cục bộ này.
- Hành vi trước/sau: không thay đổi mã nguồn hoặc hành vi xác thực. Kết luận: kiến trúc auth hiện tại phù hợp cho bản demo/đồ án nhưng cần gia cố trước production.
- Điều không thay đổi: tài khoản, session, role và dữ liệu hiện có.

### Kết luận kỹ thuật

- Dùng Laravel Sanctum 4 với guard `web`, session lưu phía server, cookie phiên HttpOnly và CSRF; React Axios bật `withCredentials`/`withXSRFToken` và gọi `/sanctum/csrf-cookie` trước đăng nhập/đăng ký/đăng xuất.
- Không phát JWT hoặc bearer token cho SPA first-party. Bảng `personal_access_tokens` và trait `HasApiTokens` tồn tại nhưng chưa có endpoint tạo token.
- Ba role loại trừ nhau: `customer`, `field_owner`, `admin`. Public register chỉ nhận customer/field owner; admin chỉ được tạo qua seed/quy trình nội bộ.
- Backend bảo vệ endpoint theo thứ tự `auth:sanctum` -> `active` -> `api.role:*`; service tiếp tục kiểm ownership. `ProtectedRoute` phía React chỉ phục vụ UX, không phải lớp bảo mật chính.

### Điểm nên sửa theo ưu tiên

1. Cao: thêm middleware kiểm `field_owner_profiles.verification_status = approved` cho các API vận hành của chủ sân; vẫn cho phép xem/sửa hồ sơ khi pending/rejected. Hiện owner pending vẫn truy cập toàn bộ API chủ sân.
2. Cao: thêm rate limit riêng cho login/register theo email + IP. Route list hiện không có middleware throttle ở hai endpoint này.
3. Cao trước production: không dùng mật khẩu admin mẫu `password` từ seeder; production phải tạo admin bằng quy trình an toàn và cấu hình HTTPS, `APP_DEBUG=false`, secure cookie, CORS/stateful domain chính xác.
4. Trung bình: cho phép endpoint logout chạy với `auth:sanctum` mà không bị middleware `active` chặn, hoặc chủ động hủy phiên khi phát hiện tài khoản bị khóa. Hiện tài khoản bị khóa nhận 403 cả khi gọi logout.
5. Trung bình: bổ sung test register không thể tự cấp admin, logout/invalidate session, owner pending/rejected, login throttle và lỗi CSRF/session hết hạn.
6. Trước khi mở công khai: bổ sung xác minh email, quên/đặt lại mật khẩu và cân nhắc 2FA cho admin.
7. Tùy nghiệp vụ: mô hình một cột role là đủ nếu mỗi tài khoản chỉ có một vai trò; nếu một chủ sân cũng cần đặt sân như customer bằng cùng tài khoản thì chuyển sang bảng role/permission nhiều-nhiều.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đọc route, middleware, controller/request/service, model/migration, Sanctum/CORS/session config, Axios/AuthContext/ProtectedRoute; chạy `php artisan route:list -v --path=api/v1`, `php artisan test`, và quét `rg` cho auth/role/throttle/verification.
- Kết quả: đủ 40 route; mọi route nghiệp vụ riêng đều có auth + active + role phù hợp; 13/13 test và 32 assertion đạt. Không thấy rate limiter trên login/register và không thấy kiểm trạng thái duyệt owner ở middleware/service route.
- Việc chưa xác minh và lý do: chưa pentest bằng trình duyệt thật, chưa thử cấu hình production HTTPS/domain vì người dùng chỉ yêu cầu rà soát.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — kiến trúc và trạng thái auth đã ghi nhận.
- Nguồn nội bộ: `Doc/README.md` — quy trình tài liệu.
- Nguồn nội bộ: mã nguồn backend/frontend và test auth/role hiện tại — bằng chứng triển khai.
- Nguồn ngoài: [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) — Laravel, SPA session-cookie, CSRF, bearer token cho mobile/third-party; truy cập `2026-09-07`.
- Nguồn ngoài: [Laravel 13 RateLimiter API](https://api.laravel.com/docs/13.x/Illuminate/Cache/RateLimiter.html) — Laravel, cơ chế giới hạn lượt thử; truy cập `2026-09-07`.

## Việc tiếp theo

- [x] Kiểm tra luồng đăng ký, đăng nhập, đăng xuất và lấy user hiện tại.
- [x] Kiểm tra middleware auth, active và role.
- [x] Kiểm tra bảo vệ phía frontend và test hiện có.
- [x] Ghi kết luận cùng mức ưu tiên cải thiện.
