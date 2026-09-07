# Bảo vệ file môi trường và tài liệu nội bộ khỏi Git

- Ngày giờ: `2026-09-02 16:10` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: `.gitignore`, file môi trường và thư mục tài liệu nội bộ

## Mục tiêu

Cấu hình Git để không thể vô tình commit/push `.env`, các biến thể file môi trường chứa bí mật và toàn bộ thư mục `Doc/`, trong khi vẫn cho phép commit file cấu hình mẫu `.env.example`.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: `.env`, một số biến thể và `/Doc/` đã có rule ignore; cần mở rộng cho file `.env.*` ở mọi thư mục, đặc biệt frontend, và xác minh không có file nhạy cảm đã được track.
- Ràng buộc/rủi ro: `.env.example` phải tiếp tục được Git quản lý để người khác biết cấu hình cần thiết; không đọc hoặc ghi lại nội dung `.env` thật.
- Quyết định và lý do: dùng rule theo mọi cấp thư mục, whitelist `.env.example`, kiểm tra bằng `git check-ignore` và `git ls-files`.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `.gitignore`, `Doc/AI_PROJECT_CONTEXT.md` và bản ghi này.
- Hành vi trước/sau: Git bỏ qua `.env`, `.env.*`, `*.env`, `*.env.*`, certificate private và `Doc/`/`doc/` ở mọi cấp phù hợp; file mẫu `*.env.example` vẫn có thể commit.
- Điều không thay đổi: nội dung `.env`, mã nguồn, API, frontend và dữ liệu MySQL.

## Kiểm thử / xác minh

- `git check-ignore --no-index`: xác nhận `.env`, `.env.local`, `.env.production`, file env trong `frontend/`, `Doc/`, `doc/`, `.pem` và `.p12` đều bị ignore.
- Xác nhận `.env.example`, `frontend/.env.example` và `service.env.example` không bị ignore.
- `git ls-files`: `tracked_sensitive_count=0`; không có `.env` thật hoặc file trong `Doc/` đã bị Git track.
- `git log --all -- .env Doc/** doc/**`: không tìm thấy commit cục bộ nào từng chứa các mục này.
- Việc chưa xác minh và lý do: không push thử lên GitHub vì người dùng chỉ yêu cầu cấu hình bảo vệ, chưa yêu cầu commit/push.

## Nguồn tham khảo

- Nguồn nội bộ: `.gitignore`, `.env.example`, trạng thái Git hiện tại và yêu cầu người dùng ngày `2026-09-02`.
- Nguồn ngoài: không sử dụng.

## Việc tiếp theo

- [x] Cập nhật quy tắc ignore.
- [x] Xác minh file thật bị ignore và file mẫu vẫn được track.
