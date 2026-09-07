# Giải thích framework của dự án

- Ngày giờ: `2026-09-07 15:48` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: kiến trúc Laravel REST API và React SPA

## Mục tiêu

Làm rõ dự án có hoàn toàn chạy trên Laravel hay không và vai trò của từng framework.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: repository chứa Laravel ở thư mục gốc và React/Vite trong `frontend/`.
- Ràng buộc/rủi ro: tránh mô tả nhầm Laravel đang render toàn bộ giao diện như kiến trúc Blade/MVC cũ.
- Quyết định và lý do: giải thích dự án là hai ứng dụng phối hợp, Laravel làm backend còn React làm frontend.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi giải thích cục bộ này.
- Hành vi trước/sau: không thay đổi mã hoặc cấu hình.
- Điều không thay đổi: kiến trúc và cách chạy dự án.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đối chiếu `Doc/AI_PROJECT_CONTEXT.md` và cấu trúc đã kiểm tra trước đó.
- Kết quả: backend dùng Laravel; frontend dùng React + TypeScript + Vite; cả hai chạy song song.
- Việc chưa xác minh và lý do: không có.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — kiến trúc dự án.
- Nguồn nội bộ: `package.json`, `frontend/package.json`, `routes/api.php`, `frontend/src/` — cấu trúc backend/frontend.

## Việc tiếp theo

- [ ] Không có; đây là giải thích kiến trúc.
