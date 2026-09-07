# Loại toàn bộ tệp Markdown khỏi Git

- Ngày giờ: `2026-09-02 16:15` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: cấu hình Git, tài liệu Markdown cục bộ

## Mục tiêu

Không cho bất kỳ tệp `*.md` nào trong dự án được Git theo dõi hoặc đưa lên GitHub, đồng thời giữ nguyên các tệp trên máy.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: `Doc/` đã bị ignore; `AGENTS.md` và `frontend/README.md` chưa được theo dõi nhưng `README.md` ở thư mục gốc đang được Git theo dõi.
- Ràng buộc/rủi ro: thêm `*.md` vào `.gitignore` không tự loại tệp đã được theo dõi; cần gỡ `README.md` khỏi index. Commit tiếp theo sẽ thể hiện việc xóa `README.md` khỏi kho từ thời điểm đó, nhưng lịch sử commit cũ vẫn còn tệp.
- Quyết định và lý do: ignore toàn cục `*.md`, sau đó chỉ gỡ các tệp Markdown khỏi Git index bằng `git rm --cached` để không xóa bản cục bộ.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `.gitignore`, `Doc/AI_PROJECT_CONTEXT.md`, bản ghi này; trạng thái theo dõi của `README.md` trong Git index.
- Hành vi trước/sau: trước đây Git vẫn theo dõi `README.md`; sau thay đổi không còn tệp Markdown nào được theo dõi và mọi tệp Markdown mới đều bị ignore.
- Điều không thay đổi: nội dung và sự tồn tại của các tệp Markdown trên máy; lịch sử commit đã có; mã nguồn ứng dụng.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: `git rm --cached -- README.md`, `git check-ignore -v --no-index`, `git ls-files '*.md'`, `Test-Path README.md`, `git diff --check` và `git status --short`.
- Kết quả: `README.md` vẫn tồn tại trên máy; tất cả tệp Markdown mẫu đều khớp quy tắc ignore; `git ls-files '*.md'` trả về rỗng; `git diff --check` không phát hiện lỗi khoảng trắng.
- Việc chưa xác minh và lý do: chưa commit hoặc push vì người dùng chỉ yêu cầu cấu hình.

## Nguồn tham khảo

- Nguồn nội bộ: `.gitignore` — quy tắc ignore hiện tại.
- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — quy ước bảo vệ tệp cục bộ.
- Nguồn nội bộ: `Doc/README.md` — quy trình tài liệu dự án.
- Nguồn nội bộ: `Doc/TEMPLATES/CHANGE_RECORD_TEMPLATE.md` — mẫu bản ghi thay đổi.
- Nguồn nội bộ: kết quả `git ls-files '*.md'` và `git status --short` — trạng thái theo dõi trước thay đổi.

## Việc tiếp theo

- [x] Thêm quy tắc `*.md` vào `.gitignore`.
- [x] Gỡ mọi tệp Markdown đã được theo dõi khỏi Git index nhưng giữ bản cục bộ.
- [x] Xác minh không còn tệp Markdown nào được Git theo dõi.
