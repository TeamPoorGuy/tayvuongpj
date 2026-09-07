# Hướng dẫn chạy project với MySQL Laragon ngoài thư mục www

- Ngày giờ: `2026-09-01 00:00` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Môi trường phát triển cục bộ

## Mục tiêu

Hướng dẫn chạy project tại `C:/Users/acer/Documents/OpenSource/tayvuongpj` trong
khi chỉ sử dụng MySQL do Laragon quản lý, không di chuyển project vào
`C:/laragon/www`.

## Bối cảnh và đánh giá trước khi làm

- `.env` hiện dùng MySQL tại `127.0.0.1:3306`, database `tayvuongpj`, username
  `root`; không đọc hoặc ghi lại mật khẩu.
- CLI lấy PHP `8.3.30` và Composer `2.9.4` từ Laragon; PHP đáp ứng yêu cầu `^8.3`.
- Node hiện chạy `v24.15.0`, npm `11.12.1`.
- Laragon chỉ cần chạy MySQL; Laravel chạy bằng Artisan từ thư mục project.

## Thay đổi hoặc đề xuất

- Không sửa cấu hình hoặc mã nguồn.
- Bật MySQL trong Laragon, tạo database `tayvuongpj`, mở Laragon Terminal, `cd`
  vào project, cài dependency nếu cần, migrate/seed, tạo storage link, rồi chạy
  `php artisan serve` và `npm run dev` trong hai terminal.

## Kiểm thử / xác minh

- Đã kiểm tra runtime và các khóa `.env` không nhạy cảm liên quan đến MySQL.
- Chưa chạy migration hoặc thay đổi database vì người dùng mới yêu cầu hướng dẫn.

## Nguồn tham khảo

- Nguồn nội bộ: `.env`, `composer.json`, `package.json`, `config/database.php`.
- Nguồn ngoài: [Laragon Terminal](https://laragon.org/docs/terminal), truy cập `2026-09-01`.
- Nguồn ngoài: [Laragon Directory Structure](https://laragon.org/docs/directory-structure), truy cập `2026-09-01`.
- Nguồn ngoài: [Laragon Pretty URLs](https://laragon.org/docs/pretty-urls), truy cập `2026-09-01`.
- Nguồn ngoài: [Laravel 13 Directory Structure](https://laravel.com/docs/13.x/structure), truy cập `2026-09-01`.

## Việc tiếp theo

- [ ] Người dùng bật MySQL và tạo database `tayvuongpj` nếu chưa có.
- [ ] Chạy migrate/seed, storage link và hai development server theo hướng dẫn.
