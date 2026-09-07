# Tài liệu dự án Tây Vương / SportHub

Thư mục này là điểm vào dành cho con người và AI khi tiếp tục dự án.

| Tệp | Mục đích |
| --- | --- |
| `PROJECT_SUMMARY.md` | Bản tóm tắt ngắn, dễ đọc cho chủ dự án. |
| `AI_PROJECT_CONTEXT.md` | Bản đồ đầy đủ: mục tiêu, kiến trúc, luồng nghiệp vụ, dữ liệu, route và giới hạn hiện tại. Đọc đầu tiên. |
| `API_CONTRACT.md` | Hợp đồng endpoint, xác thực, request/response của REST API v1. |
| `REFERENCES.md` | Sổ đăng ký nguồn nội bộ, tài liệu kỹ thuật, tài nguyên giao diện/hình ảnh. |
| `History/` | Một bản ghi Markdown cho mỗi lần phân tích, đề xuất hoặc thay đổi. |
| `TEMPLATES/CHANGE_RECORD_TEMPLATE.md` | Mẫu bắt buộc cho bản ghi mới. |

## Quy trình bắt buộc cho các lần tiếp theo

Trước bất kỳ đề xuất hoặc sửa đổi nào, tạo một tệp mới trong `History/` với tên
`YYYY-MM-DD_HHMM_slug-ngan-gon.md`, điền mẫu và trích dẫn nguồn. Nếu đã sửa mã,
cập nhật `AI_PROJECT_CONTEXT.md` khi phần mô tả hiện tại không còn đúng.

Quy tắc này cũng được đặt tại `AGENTS.md` ở thư mục gốc để các AI agent làm việc
trong repository tự động tuân theo.

## Cách dùng nhanh

1. Đọc `AI_PROJECT_CONTEXT.md`.
2. Đọc bản ghi mới nhất trong `History/`.
3. Mở `REFERENCES.md` để biết nguồn đã được chấp nhận và các tệp mã gốc.
4. Tạo bản ghi riêng trước khi nêu đề xuất hoặc thay đổi tiếp theo.
