# Không theo dõi thư mục tài liệu cục bộ

- Ngày giờ: `2026-08-31 00:20` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: Git ignore và tài liệu cục bộ

## Mục tiêu

Giữ toàn bộ tài liệu trong `Doc/` ở máy cục bộ và loại chúng khỏi các commit đưa
lên GitHub.

## Bối cảnh và đánh giá trước khi làm

- `Doc/` vừa được tạo để lưu bối cảnh, nguồn tham khảo và lịch sử công việc.
- Người dùng yêu cầu thư mục này không xuất hiện trong danh sách có thể commit.

## Thay đổi hoặc đề xuất

- Đã thêm `/Doc/` vào `.gitignore`.
- Các tệp trong `Doc/` vẫn tồn tại cục bộ nhưng Git sẽ bỏ qua các tệp chưa được
  theo dõi trong thư mục này.

## Kiểm thử / xác minh

- Kiểm tra `git status --short` sau thay đổi: `Doc/` không còn hiện là untracked.

## Nguồn tham khảo

- Nguồn nội bộ: `.gitignore` — quy tắc ignore hiện có.
- Nguồn yêu cầu: Yêu cầu người dùng, `2026-08-31`.

## Việc tiếp theo

- [ ] Giữ `Doc/` là tài liệu cục bộ; nếu sau này cần chia sẻ tài liệu, xuất bản
  có chọn lọc sang một vị trí khác theo yêu cầu người dùng.
