# Khởi tạo tài liệu AI và audit project

- Ngày giờ: `2026-08-31 00:00` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: Thiết lập tài liệu sống cho project Tây Vương / SportHub

## Mục tiêu

Đọc codebase hiện tại và tạo tài liệu để một AI khác hiểu mục tiêu, kiến trúc,
luồng nghiệp vụ, dữ liệu, giới hạn kỹ thuật; đồng thời áp dụng quy tắc lưu lịch
sử và nguồn tham khảo cho các lần làm việc sau.

## Bối cảnh và đánh giá trước khi làm

- Project là Laravel 13 cho nền tảng đặt/quản lý sân thể thao, có vai trò customer,
  field owner và admin.
- Worktree đã có thay đổi của người dùng tại `config/database.php` (ép MySQL) và
  `package-lock.json` chưa được theo dõi. Không chỉnh sửa hoặc hoàn tác các tệp đó.
- Repository không có `database/tayvuong_sports_field_dump.sql` dù README có nêu.

## Thay đổi hoặc đề xuất

- Đã thêm `Doc/AI_PROJECT_CONTEXT.md`: bối cảnh toàn diện, stack, dữ liệu, route,
  luồng, ràng buộc và các điểm cần lưu ý.
- Đã thêm `Doc/REFERENCES.md`: registry nguồn nội bộ/ngoài và quy tắc trích dẫn.
- Đã thêm `Doc/README.md`, `Doc/TEMPLATES/CHANGE_RECORD_TEMPLATE.md` và
  `AGENTS.md`: bắt buộc tạo một bản ghi riêng cho mỗi đề xuất hoặc thay đổi sau này.
- Không thay đổi logic ứng dụng.

## Kiểm thử / xác minh

- Đã chạy `php artisan route:list --except-vendor`: hệ thống đăng ký 41 route.
- Đã thử `php artisan test`; tiến trình phải dừng do chờ kết nối MySQL. Chưa có
  kết quả pass/fail. Nguyên nhân quan sát được là `config/database.php` hiện ép
  connection MySQL thay vì đọc `DB_CONNECTION`; cần một MySQL đang hoạt động và
  cấu hình hợp lệ trước khi chạy test.
- Đã đối chiếu controller với route và phát hiện route admin toggle review gọi
  method `toggle` không tồn tại (controller có `toggleVisibility`). Chưa sửa vì
  phạm vi công việc là lập tài liệu.

## Nguồn tham khảo

- Nguồn nội bộ: `README.md`, `composer.json`, `package.json`, `vite.config.js` —
  phạm vi, dependency và cách vận hành.
- Nguồn nội bộ: `routes/web.php`, `bootstrap/app.php`,
  `app/Http/Middleware/RoleMiddleware.php` — endpoint và kiểm soát quyền.
- Nguồn nội bộ: `app/Http/Controllers/**/*.php`, `app/Models/*.php`,
  `database/migrations/*.php`, `database/seeders/*.php` — implementation hiện tại.
- Nguồn nội bộ: `resources/views/**/*.blade.php`, `tests/**/*.php` — hành vi UI và test.
- Nguồn ngoài: [Laravel 13 documentation](https://laravel.com/docs/13.x) — Laravel,
  truy cập `2026-08-31`.
- Nguồn ngoài: [Tailwind CSS](https://tailwindcss.com), [Vite](https://vite.dev),
  [Unsplash](https://unsplash.com) — các dependency/tài nguyên đang sử dụng,
  truy cập `2026-08-31`.

## Việc tiếp theo

- [ ] Cấu hình MySQL hợp lệ hoặc khôi phục việc đọc `DB_CONNECTION` trước khi chạy test.
- [ ] Quyết định và sửa method đích của route `admin.reviews.toggle`.
- [ ] Mở rộng test cho phân quyền, booking collision, ownership của slot và các luồng chuyển trạng thái.
