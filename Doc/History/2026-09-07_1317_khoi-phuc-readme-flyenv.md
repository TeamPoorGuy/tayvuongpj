# Khôi phục README có hướng dẫn FlyEnv

- Ngày giờ: `2026-09-07 13:17` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: `README.md`, commit `4099eae` và nhánh `main`

## Mục tiêu

Khôi phục đúng README tiếng Việt có hướng dẫn chạy dự án trên Linux bằng FlyEnv và domain `tayvuong.com`, rồi đưa bản này lên GitHub.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: README trên GitHub vừa bị thay bằng README Laravel mặc định từ root commit, không đúng phiên bản người dùng yêu cầu.
- Ràng buộc/rủi ro: phải phục hồi nguyên văn từ lịch sử, không tự biên tập hoặc trộn với README khác.
- Quyết định và lý do: dùng blob `README.md` tại commit `4099eae`, đã xác nhận chứa `FlyEnv`, `Linux` và `tayvuong.com`; so sánh hash trước khi commit/push.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: `README.md`; bản ghi cục bộ này.
- Hành vi trước/sau: GitHub hiển thị README tiếng Việt có hướng dẫn FlyEnv đúng yêu cầu.
- Điều không thay đổi: `.gitignore`, các Markdown cục bộ khác, mã ứng dụng và lịch sử commit.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: khôi phục `4099eae:README.md` bằng Git restore, so sánh object hash, kiểm tra các chuỗi FlyEnv/Linux/tayvuong.com, commit, push và đối chiếu remote.
- Kết quả: README trong HEAD có hash `a152a5ed4162a540c25f26ac4d639899ff2860b8`, trùng tuyệt đối nguồn tại commit `4099eae`. Commit `51668b9` (`Restore FlyEnv project README`) đã push lên `origin/main`; local và remote cùng hash `51668b94c72735da7d2ab445ea0f2f59dcc5cf09`.
- Việc chưa xác minh và lý do: bản nguồn có ba dòng trailing whitespace; giữ nguyên có chủ đích để bảo đảm phục hồi nguyên văn.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` và `Doc/README.md` — quy trình dự án.
- Nguồn nội bộ: commit `4099eae`, path `README.md` — phiên bản có hướng dẫn FlyEnv do người dùng xác nhận.
- Nguồn nội bộ: commit `f38e993` — lần phục hồi README Laravel mặc định chưa đúng.

## Việc tiếp theo

- [x] Khôi phục nguyên văn README từ commit `4099eae`.
- [x] Xác minh hash trùng khớp.
- [x] Commit, push và xác minh remote.
