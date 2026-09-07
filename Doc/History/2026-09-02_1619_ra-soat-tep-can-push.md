# Rà soát các tệp có nên đưa lên GitHub

- Ngày giờ: `2026-09-02 16:19` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: thay đổi chuyển Laravel MVC sang REST API và React SPA

## Mục tiêu

Đánh giá các tệp modified/deleted trong ảnh người dùng cung cấp có nên được commit và push hay không.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: dự án đã chuyển từ Laravel MVC/Blade sang Laravel REST API và React SPA; worktree còn nhiều thay đổi chưa commit.
- Ràng buộc/rủi ro: chỉ nhìn trạng thái modified/deleted chưa đủ kết luận; phải đối chiếu diff, tệp thay thế và khả năng build/test. Không được đưa tệp bí mật hoặc tài liệu Markdown cục bộ lên Git.
- Quyết định và lý do: rà soát diff và quan hệ phụ thuộc của từng nhóm tệp trước khi khuyến nghị; không commit hoặc push trong lần này.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi rà soát cục bộ này.
- Hành vi trước/sau: không thay đổi mã ứng dụng; bổ sung kết luận nên/không nên push.
- Điều không thay đổi: Git index, mã nguồn và remote repository.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: kiểm tra `git status`, `git diff`, nội dung package/config và các tệp thay thế tương ứng.
- Kết quả: nên commit/push các tệp trong ảnh như một phần của **cùng một commit chuyển đổi kiến trúc**, kèm toàn bộ controller API, middleware mới, DTO/request/resource/service, `routes/api.php`, cấu hình Sanctum và mã React đang chưa được Git theo dõi. `composer validate --strict` hợp lệ; 13/13 test với 32 assertion đạt; frontend lint sạch; TypeScript/Vite production build thành công.
- Việc chưa xác minh và lý do: chưa kiểm thử lại luồng thật trên Laragon/MySQL trong lần rà soát này; không cần để kết luận về tính đầy đủ của commit nhưng nên smoke test trước khi phát hành.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — kiến trúc và trạng thái dự án hiện tại.
- Nguồn nội bộ: `Doc/README.md` — quy trình tài liệu.
- Nguồn nội bộ: `.gitignore` — phạm vi tệp cục bộ không được push.
- Nguồn nội bộ: Git diff/worktree hiện tại — bằng chứng cho từng thay đổi.
- Nguồn do người dùng cung cấp: `codex-clipboard-8584bacd-83b0-4f21-aec0-323f3102c3c5.png` — danh sách tệp cần đánh giá.

## Việc tiếp theo

- [x] Đối chiếu từng tệp trong ảnh với kiến trúc mới.
- [x] Kiểm tra tệp thay thế cho các tệp bị xóa.
- [x] Ghi kết luận và lưu ý trước khi commit/push.
