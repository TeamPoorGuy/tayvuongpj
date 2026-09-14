# Rà soát toàn bộ hệ thống, kiến trúc và tiến độ thực tế sau PR #3

- Ngày giờ: `2026-09-14 14:25` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Antigravity
- Liên quan đến: Toàn bộ hệ thống (Laravel Backend API, React Frontend SPA, Database, Auth, Tiến độ thực tế trên máy người dùng)

## Mục tiêu

Kiểm tra, đối chiếu trung thực toàn bộ mã nguồn, cơ sở dữ liệu, tài liệu và môi trường chạy trên máy hiện tại; xác định chính xác thông tin website, kiến trúc hoạt động, những gì đã hoàn thành, những gì còn dở và những gì chưa làm.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng:
  + Nhánh `main` đang ở commit `17b267a` (Merge PR #3 từ Vu Truong Giang / GAD2412, vừa merge lúc 13:33 ngày 14/09/2026).
  + PR #3 đã bổ sung luồng đăng ký chủ sân (`/become-owner`), cập nhật logic `isApprovedOwner()`, thêm migration hồ sơ chủ sân, gửi email thông báo phê duyệt/từ chối và nâng bộ test backend lên 26 test.
  + Thư mục `Doc/` và `ProjectLog/` đã được commit lên Git từ PR #2 (`dd725e5`).
  + Máy người dùng hiện tại (`c:\LeDucLuong\...`): MySQL cổng 3306 đang chạy, nhưng `vendor/`, `node_modules/` và `.env` chưa có; server 8011 và 5173 chưa khởi động.
- Ràng buộc/rủi ro:
  + Phải trung thực tuyệt đối, không gian dối về tiến độ; chỉ rõ sự khác biệt giữa tài liệu cũ (README Blade MVC) và thực tế (Laravel API + React SPA).
  + Không ghi thông tin bí mật từ `.env` vào tài liệu.
- Quyết định và lý do:
  + Lập bản ghi rà soát chi tiết toàn bộ các tầng mã nguồn, đối chiếu danh sách chức năng thực tế với yêu cầu của một sàn đặt sân thể thao.

## Thay đổi hoặc đề xuất

- Tệp đã sửa (hoặc dự kiến sửa):
  + Tạo `Doc/History/2026-09-14_1425_ra-soat-toan-bo-he-thong-va-tien-do.md`.
  + Cập nhật `Doc/AI_PROJECT_CONTEXT.md` phản ánh các thay đổi sau PR #3.
  + Cập nhật `Doc/REFERENCES.md` bổ sung mốc rà soát.
- Hành vi trước/sau:
  + Cập nhật bản đồ thông tin dự án đúng với commit mới nhất `17b267a`.
- Điều không thay đổi:
  + Mã nguồn chức năng của backend và frontend giữ nguyên.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác:
  + `git status`, `git log -n 5 --stat`: Xác định các commit mới nhất.
  + `Test-Path`: Kiểm tra sự tồn tại của `vendor`, `node_modules`, `.env`.
  + `Test-NetConnection`: Kiểm tra trạng thái cổng 3306 (MySQL), 8011 (API), 5173 (Frontend).
  + Rà soát chi tiết: `routes/api.php`, `app/Models/User.php`, `app/Http/Middleware/EnsureFieldOwnerIsApproved.php`, `frontend/src/App.tsx`, `tests/Feature/Api/LayeredApiTest.php`.
- Kết quả:
  + MySQL 3306 đang mở (True).
  + Port 8011 và 5173 đang đóng (False).
  + Lỗi `$profile->verified` đã được đội ngũ sửa thành `$user->isApprovedOwner()` ở commit `186aaf7`.
  + Lỗi `/logout` bị kẹt trong middleware `active` vẫn còn tồn đọng trong `routes/api.php`.
  + Dependencies và `.env` trên máy người dùng chưa được khởi tạo.
- Việc chưa xác minh và lý do:
  + Chưa chạy `php artisan test` và `npm run build` trực tiếp trên máy này vì chưa chạy `composer install` và `npm install`.

## Nguồn tham khảo

- Nguồn nội bộ: Toàn bộ mã nguồn tại `app/`, `routes/api.php`, `frontend/src/`, `database/migrations/`.
- Nguồn nội bộ: Lịch sử Git commit `cd1da60`, `d4d183d`, `dd725e5`, `186aaf7`, `17b267a`.
- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `ProjectLog/PROJECT_LOG.md`, `README.md`.

## Việc tiếp theo

- [ ] Thông báo đầy đủ, trung thực kết quả rà soát cho người dùng.
- [ ] Hướng dẫn người dùng khởi tạo môi trường (cài `vendor`, `node_modules`, cấu hình `.env`, chạy migrate/seed).
- [ ] Đề xuất xử lý các tồn đọng kỹ thuật: route `/logout`, rate limit, hoàn thiện CRUD danh mục và các tính năng còn thiếu.
