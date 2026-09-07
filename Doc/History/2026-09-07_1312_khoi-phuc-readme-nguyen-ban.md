# Khôi phục README nguyên bản từ commit đầu tiên

- Ngày giờ: `2026-09-07 13:12` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: `README.md`, root commit và nhánh `main`

## Mục tiêu

Thay README vừa phục hồi bằng đúng nguyên bản README ở commit đầu tiên của repository, trước mọi thay đổi của dự án, rồi đưa bản đó lên GitHub.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: commit `fc72165` đã đưa README mô tả kiến trúc API + React lên GitHub, nhưng đó không phải phiên bản người dùng yêu cầu.
- Ràng buộc/rủi ro: phải lấy nguyên văn từ root commit, không tự biên tập; các Markdown khác vẫn bị ignore.
- Quyết định và lý do: dùng root commit `c34940ae4b839033ecc9f5f2984c7a5a7f82b53a` làm nguồn sự thật, xác minh hash nội dung sau phục hồi, commit và push thay thế.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `README.md`; bản ghi cục bộ này.
- Hành vi trước/sau: README trên GitHub chuyển từ bản mô tả kiến trúc mới sang đúng bản nguyên thủy của repository.
- Điều không thay đổi: ngoại lệ `/README.md` trong `.gitignore`, các Markdown khác, mã ứng dụng và lịch sử Git cũ.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đọc `c34940a:README.md`, thay nguyên văn bằng `apply_patch`, so sánh object hash, chạy `git diff --check`, commit, push và đối chiếu remote HEAD.
- Kết quả: README trong HEAD có object hash `5ad13779e0df8fca8c419cc16548c14801893757`, khớp tuyệt đối README của root commit. Commit `f38e993` (`Restore original Laravel README`) đã push lên `origin/main`; local và remote cùng hash `f38e9934c60052850154a0aa93cfc72145d4b33d`.
- Việc chưa xác minh và lý do: không có.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` và `Doc/README.md` — quy trình dự án.
- Nguồn nội bộ: root commit `c34940ae4b839033ecc9f5f2984c7a5a7f82b53a`, path `README.md` — nội dung nguyên bản cần phục hồi.
- Nguồn nội bộ: commit `fc72165` — lần phục hồi chưa đúng yêu cầu.

## Việc tiếp theo

- [x] Trích xuất và xác minh README từ root commit.
- [x] Thay README hiện tại bằng nguyên bản.
- [x] Commit, push và xác minh remote.
