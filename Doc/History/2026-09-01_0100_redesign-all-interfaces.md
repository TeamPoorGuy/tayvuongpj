# Thiết kế lại toàn bộ giao diện SportHub

- Ngày giờ: `2026-09-01 01:00` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: Toàn bộ giao diện public, authentication, customer, field owner và admin

## Mục tiêu

Thiết kế lại toàn bộ giao diện hiện có thành một hệ thống trực quan, hiện đại,
responsive, nhất quán và phù hợp sản phẩm đặt sân thể thao; giữ nguyên nghiệp vụ
và route hiện có.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: 27 Blade view/component, ba layout riêng; Tailwind CDN được nạp ở
  layout, trong khi Vite/Tailwind v4 cũng đã cấu hình nhưng chưa được dùng thống nhất.
- Ràng buộc/rủi ro: phải bảo toàn tên field, action, CSRF, method, biến Blade và
  JavaScript kiểm tra slot để không làm hỏng chức năng.
- Quyết định: xây dựng design system chung trong `resources/css/app.css`, dùng
  Blade component/layout chung và chỉnh toàn bộ page theo cùng ngôn ngữ hình ảnh.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `resources/css/app.css`, `resources/js/app.js`; toàn bộ view đang
  dùng trong `resources/views/{layouts,auth,customer,field-owner,admin}/` và các
  component trong `resources/views/components/`.
- Tệp đã tạo: `components/brand.blade.php`, `components/icon.blade.php`,
  `components/field-card.blade.php`, `components/flash-messages.blade.php`.
- Hành vi trước/sau: chức năng và dữ liệu form giữ nguyên; giao diện chuyển từ
  các khối Tailwind rời rạc/CDN sang design system tập trung qua Vite, font
  Manrope, màu xanh rừng–lime, card ảnh, bảng dữ liệu sáng, badge trạng thái,
  empty state và điều hướng responsive cho cả ba nhóm người dùng.
- Trang danh sách sân công khai bổ sung bộ lọc giá tối thiểu và trang admin đơn
  đặt sân bổ sung bộ lọc trạng thái; hai controller đã hỗ trợ sẵn tham số này.
- Điều không thay đổi: controller, model, migration, database, route và quy tắc
  nghiệp vụ. Không triển khai hosting vì dự án được người dùng chạy local bằng
  Laragon và không có `.openai/hosting.json`.

## Kiểm thử / xác minh

- `npm run build`: đạt; Vite sinh bundle production thành công. Có cảnh báo
  không chặn build về package tùy chọn `fontaine` của plugin font.
- `php artisan view:clear` và `php artisan view:cache`: đạt; toàn bộ Blade compile.
- `php artisan route:list --except-vendor`: đạt; 41 route được nhận diện.
- `php artisan test`: đạt, 5/5 test và 5 assertion.
- HTTP smoke test local tại cổng tạm `8011`: `/`, `/fields`, `/login`,
  `/register` đều trả `200`.
- `git diff --check`: đạt, không có lỗi whitespace.
- Chưa kiểm thử trực quan thủ công từng trạng thái dữ liệu sau đăng nhập; cú pháp
  view và các luồng công khai đã được xác minh tự động.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `routes/web.php`,
  `resources/views/**/*.blade.php`, `resources/css/app.css`, `resources/js/app.js`.
- Nguồn yêu cầu: Yêu cầu người dùng ngày `2026-09-01`.
- Quy trình: skill `sites-building` của môi trường Codex; dự án giữ local vì
  không có `.openai/hosting.json` và người dùng đang chạy bằng Laragon.
- Tài nguyên ngoài đã có sẵn trong dự án: Google Fonts — Manrope và ảnh Unsplash;
  xem `Doc/REFERENCES.md`.

## Việc tiếp theo

- [x] Kiểm kê cấu trúc Blade và chọn design direction.
- [x] Thiết kế shared layout/component và public customer flow.
- [x] Thiết kế field-owner/admin dashboard và data pages.
- [x] Build, kiểm tra syntax/view cache và cập nhật tài liệu.
- [ ] Người dùng xem trực tiếp trên dữ liệu Laragon và phản hồi các tinh chỉnh
  mang tính sở thích (màu, nội dung, mật độ thông tin) nếu cần.
