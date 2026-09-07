# Xác nhận kiến trúc MVC hiện tại

- Ngày giờ: `2026-09-01 20:28` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: kiến trúc Laravel, route, controller và Blade view

## Mục tiêu

Xác nhận dự án đang dùng Laravel MVC server-rendered hay mô hình backend API và
frontend tách thành hai ứng dụng độc lập.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: repository có model Eloquent, controller Laravel, Blade view và
  route web dùng session.
- Ràng buộc/rủi ro: có một endpoint mang tiền tố `/api/` để kiểm tra slot, nhưng
  một endpoint JSON đơn lẻ không làm dự án trở thành kiến trúc API tách frontend.
- Quyết định và lý do: kết luận đây là ứng dụng MVC server-rendered; không thay
  đổi mã.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ tạo bản ghi này.
- Hành vi trước/sau: không thay đổi.
- Điều không thay đổi: kiến trúc, route và nghiệp vụ.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: liệt kê `routes/`, `app/Http/Controllers/`, Blade view và
  tìm dấu hiệu của `apiResource`, Sanctum, Passport hoặc JWT.
- Kết quả: chỉ có `routes/web.php` và `routes/console.php`; có 31 Blade view;
  không tìm thấy API resource hoặc cơ chế xác thực API tách riêng.
- Việc chưa xác minh và lý do: không có.

## Nguồn tham khảo

- Nguồn nội bộ: `routes/web.php` — route web và endpoint kiểm tra slot.
- Nguồn nội bộ: `app/Http/Controllers/**/*.php` — controller theo nhóm vai trò.
- Nguồn nội bộ: `app/Models/*.php`, `resources/views/**/*.blade.php` — Model và View.
- Nguồn yêu cầu: câu hỏi của người dùng ngày `2026-09-01`.

## Việc tiếp theo

- [ ] Giữ kiến trúc MVC hiện tại trừ khi có yêu cầu chuyển sang frontend/backend tách riêng.
