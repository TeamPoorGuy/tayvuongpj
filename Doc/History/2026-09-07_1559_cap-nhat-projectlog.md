# Cập nhật ProjectLog và hướng dẫn chạy dự án

- Ngày giờ: `2026-09-07 15:59` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: `ProjectLog/`, lịch sử thay đổi và cách chạy Laravel API + React

## Mục tiêu

Tạo tài liệu Markdown trong `ProjectLog/` ghi lại các phần dự án đã sửa, trạng thái auth gần nhất và hướng dẫn cài/chạy project.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: thư mục `ProjectLog/` đã tồn tại nhưng chưa có tệp; branch `main` sạch và đồng bộ `origin/main`; commit mới nhất là `cd1da60 fix auth`.
- Ràng buộc/rủi ro: phải phân biệt thay đổi đã commit với việc còn dở; không ghi `.env`, token, mật khẩu thật hoặc dữ liệu cá nhân. Markdown đang bị `.gitignore` loại trừ nên ProjectLog chỉ lưu cục bộ.
- Quyết định và lý do: tạo một file tổng hợp `ProjectLog/PROJECT_LOG.md`, dựa trên Git history/source hiện tại, có phần trạng thái, thay đổi, cách chạy hằng ngày, cài lần đầu, kiểm thử và xử lý lỗi thường gặp.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `ProjectLog/PROJECT_LOG.md`, `Doc/AI_PROJECT_CONTEXT.md`, `Doc/REFERENCES.md` và bản ghi này.
- Hành vi trước/sau: không thay đổi ứng dụng; bổ sung tài liệu vận hành và lịch sử dễ đọc.
- Điều không thay đổi: source code, database, `.env` và remote Git.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đọc diff commit `cd1da60`, AuthContext, scripts/ports và các key không bí mật trong `.env.example`; chạy PHP lint, 13 backend test, frontend lint/build và `git check-ignore`.
- Kết quả: đã tạo ProjectLog tổng hợp. PHP syntax đạt; 13 test/32 assertion đạt; frontend lint/build đạt. Log ghi rõ hai lỗi logic auth chưa được test bắt: sai tên thuộc tính verification và logout vẫn bị `active` chặn. `ProjectLog/PROJECT_LOG.md` khớp quy tắc ignore `*.md`.
- Việc chưa xác minh và lý do: chưa chạy smoke test qua Laragon/MySQL vì thay đổi chỉ là tài liệu và người dùng chưa yêu cầu khởi động server.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md`, `Doc/TEMPLATES/CHANGE_RECORD_TEMPLATE.md` — bối cảnh và quy trình.
- Nguồn nội bộ: lịch sử Git và commit `cd1da60` — thay đổi gần nhất.
- Nguồn nội bộ: `package.json`, `frontend/package.json`, `frontend/vite.config.ts`, `.env.example` — cài đặt, lệnh chạy và port.

## Việc tiếp theo

- [x] Rà soát commit auth và trạng thái source hiện tại.
- [x] Tạo/cập nhật `ProjectLog/PROJECT_LOG.md`.
- [x] Xác minh Markdown bị ignore và không ảnh hưởng Git.
