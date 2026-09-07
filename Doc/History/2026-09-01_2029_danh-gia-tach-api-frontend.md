# Đánh giá chuyển sang API và frontend tách riêng

- Ngày giờ: `2026-09-01 20:29` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: kiến trúc toàn hệ thống SportHub

## Mục tiêu

Đánh giá độ phức tạp nếu chuyển ứng dụng Laravel MVC server-rendered hiện tại
thành backend API và frontend độc lập.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: 41 route, 9 model, 19 controller nghiệp vụ và 31 Blade view; có ba
  vai trò, upload ảnh, booking/slot, review và các luồng kiểm duyệt.
- Frontend hiện chưa có framework SPA; `package.json` chỉ có Vite và Tailwind.
- Ràng buộc/rủi ro: phải thay session/redirect/flash/validation Blade bằng API
  response, authentication cho SPA, CORS/CSRF, state management và xử lý lỗi phía
  frontend; đồng thời phải viết lại toàn bộ 31 view bằng framework frontend.
- Quyết định và lý do: đánh giá mức trung bình đến cao; chưa thay đổi kiến trúc.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ tạo bản ghi đánh giá này.
- Hành vi trước/sau: không thay đổi.
- Đề xuất: nếu mục tiêu chính là hoàn thành đồ án web đặt sân, nên giữ MVC hiện
  tại. Chỉ tách API/frontend khi có yêu cầu rõ về SPA/mobile app, tái sử dụng API
  hoặc muốn thể hiện kiến trúc client-server độc lập.
- Điều không thay đổi: toàn bộ mã ứng dụng hiện tại.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đếm route/model/controller/view và kiểm tra dependencies
  trong `composer.json`, `package.json`.
- Kết quả: chưa có React/Vue, Sanctum/Passport/JWT hay API resource; việc chuyển
  đổi là tái cấu trúc đáng kể chứ không phải đổi nhỏ.
- Việc chưa xác minh và lý do: chưa chọn framework frontend và cơ chế auth nên
  chưa thể lập kế hoạch triển khai chi tiết.

## Nguồn tham khảo

- Nguồn nội bộ: `routes/web.php`, `app/Models/*.php`,
  `app/Http/Controllers/**/*.php`, `resources/views/**/*.blade.php` — quy mô hiện tại.
- Nguồn nội bộ: `composer.json`, `package.json` — dependency backend/frontend.
- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — luồng nghiệp vụ và giới hạn.
- Nguồn yêu cầu: câu hỏi của người dùng ngày `2026-09-01`.

## Việc tiếp theo

- [ ] Chỉ lập kế hoạch migration khi người dùng quyết định mục tiêu frontend,
  framework và phương thức xác thực.
