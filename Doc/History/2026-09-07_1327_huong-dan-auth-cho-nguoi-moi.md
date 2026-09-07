# Hướng dẫn sửa auth cho người mới học PHP

- Ngày giờ: `2026-09-07 13:27` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Sanctum SPA auth, owner approval, logout, rate limit và cấu hình production

## Mục tiêu

Viết lại lộ trình sửa auth bằng ngôn ngữ đơn giản, có giải thích khái niệm và comment trong code để người lần đầu học PHP có thể làm từng bước.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: hướng dẫn trước đúng định hướng nhưng đưa nhiều khái niệm và đoạn mã cùng lúc, thiếu comment giải thích.
- Ràng buộc/rủi ro: đây chỉ là hướng dẫn; không tự sửa code. Cần phân biệt rõ việc bắt buộc làm ngay và việc chỉ cần khi deploy production.
- Quyết định và lý do: chia thành bài nhỏ; mỗi bài nêu mục đích, file cần mở, đoạn code có comment, cách kiểm tra và dấu hiệu thành công.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi đề xuất cục bộ này.
- Hành vi trước/sau: không thay đổi ứng dụng; cung cấp hướng dẫn dễ đọc hơn.
- Điều không thay đổi: Sanctum cookie/session, ba role và API hiện tại.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đối chiếu nội dung người dùng gửi với mã nguồn và kết quả audit auth đã ghi ngày `2026-09-07`.
- Kết quả: hướng dẫn được sắp lại theo thứ tự: owner approval, logout, rate limit, admin demo/production, tính năng nâng cao.
- Việc chưa xác minh và lý do: chưa chạy test mới vì chưa sửa code.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md` — bối cảnh và quy trình dự án.
- Nguồn nội bộ: `Doc/History/2026-09-07_1242_ra-soat-auth-va-role.md` và `Doc/History/2026-09-07_1252_lo-trinh-sua-auth.md` — audit và lộ trình trước.
- Nguồn nội bộ: `routes/api.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `frontend/src/context/AuthContext.tsx` — vị trí code được hướng dẫn.
- Nguồn người dùng cung cấp: `C:/Users/acer/.codex/attachments/8ffe463e-19e0-43cb-8559-d654f8367750/pasted-text.txt` — bản hướng dẫn cần viết lại.
- Nguồn ngoài: [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) — Laravel, SPA cookie/session và CSRF; truy cập `2026-09-07`.

## Việc tiếp theo

- [ ] Người dùng thực hiện hoặc yêu cầu Codex triển khai Bước 1.
- [ ] Chạy test và xác nhận trước khi chuyển sang Bước 2.
- [ ] Lặp lại theo từng bước, không gộp mọi thay đổi vào một lần.
