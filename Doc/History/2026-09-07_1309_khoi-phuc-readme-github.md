# Khôi phục README lên GitHub

- Ngày giờ: `2026-09-07 13:09` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: `README.md`, `.gitignore`, lịch sử Git và nhánh `main`

## Mục tiêu

Khôi phục README gốc từ lịch sử Git, cho phép riêng README ở thư mục gốc được theo dõi và đưa lại lên GitHub.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: quy tắc `*.md` đang ignore mọi Markdown; commit trước đã xóa `README.md` khỏi GitHub nhưng giữ bản cục bộ.
- Ràng buộc/rủi ro: không đưa `Doc/`, `AGENTS.md` hoặc Markdown khác lên Git; không viết lại lịch sử remote.
- Quyết định và lý do: tìm phiên bản README gần nhất trong lịch sử, đối chiếu bản cục bộ, thêm ngoại lệ `!/README.md`, commit và push thay đổi phục hồi.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `.gitignore`, `README.md`, `Doc/AI_PROJECT_CONTEXT.md` và bản ghi này.
- Hành vi trước/sau: trước đây README gốc bị ignore và không còn trên HEAD; sau thay đổi README gốc được Git theo dõi và xuất hiện lại trên GitHub.
- Điều không thay đổi: mọi Markdown khác vẫn bị ignore; lịch sử commit cũ không bị rewrite.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: dùng `git log`, `git show`, `git hash-object`, `git check-ignore`, `git diff --cached --check`, `git cat-file`, commit, push và `git ls-remote`.
- Kết quả: xác định commit `3f6c8ae` đã xóa README và commit cha `4099eae` còn bản MVC cũ. Chọn bản README cục bộ mới hơn, đúng kiến trúc API + React; thêm ngoại lệ `!/README.md`; commit `fc72165` (`Restore project README`) đã push lên `origin/main`. Local HEAD và remote `main` cùng hash `fc72165003d9f4317f2cfc75e170f019ef71c9f6`; `README.md` tồn tại trong HEAD.
- Việc chưa xác minh và lý do: không có.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — trạng thái ignore Markdown hiện tại.
- Nguồn nội bộ: `Doc/README.md` — quy trình tài liệu.
- Nguồn nội bộ: `.gitignore` — quy tắc `*.md` hiện tại.
- Nguồn nội bộ: lịch sử Git của `README.md` — nguồn khôi phục nội dung.

## Việc tiếp theo

- [x] Xác định phiên bản README gần nhất trước khi bị xóa.
- [x] Thêm ngoại lệ ignore chỉ cho `/README.md`.
- [x] Phục hồi, xác minh, commit và push README.
