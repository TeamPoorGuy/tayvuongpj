# Giải thích cấu hình port khi chạy npm run dev

- Ngày giờ: `2026-09-07 15:54` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: `package.json`, `frontend/vite.config.ts`, Laravel và Vite

## Mục tiêu

Chỉ rõ nơi cấu hình port backend 8011 và frontend 5173 khi chạy `npm run dev`, cùng quan hệ proxy giữa hai ứng dụng.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: root script `dev` chạy Laravel bằng `php artisan serve --port=8011` và gọi dev script của frontend; Vite config đặt frontend port 5173 và proxy API về 8011.
- Ràng buộc/rủi ro: nếu đổi port backend phải cập nhật cả script và các proxy target; nếu đổi frontend port cần cập nhật cấu hình Sanctum/CORS trong `.env` nhưng không ghi bí mật vào tài liệu.
- Quyết định và lý do: giải thích riêng từng file, không sửa cấu hình khi người dùng chưa yêu cầu đổi port.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi giải thích cục bộ này.
- Hành vi trước/sau: không thay đổi port hoặc source code.
- Điều không thay đổi: backend 8011, frontend 5173 và proxy hiện tại.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đọc scripts trong `package.json` và phần `server` trong `frontend/vite.config.ts`.
- Kết quả: xác nhận Laravel dùng 8011, Vite dùng 5173 và ba đường dẫn `/api`, `/sanctum`, `/storage` được proxy sang backend 8011.
- Việc chưa xác minh và lý do: không chạy server vì người dùng chỉ hỏi vị trí cấu hình.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md` — bối cảnh và quy trình.
- Nguồn nội bộ: `package.json` — script chạy backend/frontend.
- Nguồn nội bộ: `frontend/vite.config.ts` — port frontend và proxy backend.

## Việc tiếp theo

- [ ] Không có; chỉ đổi port khi người dùng yêu cầu.
