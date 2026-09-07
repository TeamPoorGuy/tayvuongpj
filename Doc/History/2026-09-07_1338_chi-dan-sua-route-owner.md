# Chỉ dẫn sửa route chủ sân

- Ngày giờ: `2026-09-07 13:38` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Bước 1 owner approval, `routes/api.php`

## Mục tiêu

Chỉ rõ chính xác khối route nào cần thay để owner pending/rejected chỉ được xem và sửa hồ sơ, còn API vận hành yêu cầu approved.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: middleware `EnsureFieldOwnerIsApproved.php` đã được tạo và alias `owner.approved` đã được thêm vào `bootstrap/app.php`; khối field-owner trong `routes/api.php` chưa được sửa.
- Ràng buộc/rủi ro: không sửa nhầm route customer, admin hoặc auth; không chặn route profile của owner chưa duyệt.
- Quyết định và lý do: thay duy nhất khối bắt đầu bằng `Route::prefix('field-owner')`, giữ hai route profile ở ngoài nhóm `owner.approved` và đặt các route vận hành vào trong.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi hướng dẫn cục bộ này.
- Hành vi trước/sau: chưa sửa mã; cung cấp đoạn thay thế chính xác.
- Điều không thay đổi: các route customer, admin, public và auth.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: kiểm tra Git diff/status và đọc nội dung middleware, bootstrap cùng khối route field-owner hiện tại.
- Kết quả: xác nhận alias đã được thêm, middleware đã được tạo và `routes/api.php` chưa đổi. Phát hiện middleware đang đọc nhầm thuộc tính `$profile->verified`; cột đúng là `$profile->verification_status`, nếu không sửa thì owner sẽ luôn bị chặn.
- Việc chưa xác minh và lý do: chưa chạy test vì người dùng chưa hoàn tất sửa route/middleware.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md` — kiến trúc và quy trình.
- Nguồn nội bộ: `routes/api.php` — khối route cần sửa.
- Nguồn nội bộ: `bootstrap/app.php`, `app/Http/Middleware/EnsureFieldOwnerIsApproved.php` — phần người dùng đã thực hiện.
- Nguồn nội bộ: `Doc/History/2026-09-07_1327_huong-dan-auth-cho-nguoi-moi.md` — hướng dẫn bước trước.

## Việc tiếp theo

- [ ] Người dùng thay đúng khối field-owner trong `routes/api.php`.
- [ ] Kiểm tra cú pháp route và chạy test.
