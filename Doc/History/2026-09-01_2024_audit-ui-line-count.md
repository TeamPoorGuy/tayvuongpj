# Rà soát chênh lệch số dòng sau khi thiết kế lại giao diện

- Ngày giờ: `2026-09-01 20:24` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: `resources/views/`, `resources/css/app.css`, `resources/js/app.js`

## Mục tiêu

Xác minh vì sao diff giao diện xóa khoảng 1.900 dòng nhưng chỉ thêm vài trăm
dòng, và đánh giá liệu chênh lệch này có phản ánh việc mất tính năng hay không.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: nhiều Blade view mới được viết với toàn bộ markup của một khối trên
  cùng một dòng; Git đếm dòng vật lý nên báo số dòng giảm rất mạnh.
- Ràng buộc/rủi ro: số dòng không đo chính xác lượng chức năng, nhưng các dòng dài
  hàng nghìn ký tự làm mã khó đọc, khó review và dễ xung đột Git.
- Quyết định và lý do: chưa thay đổi mã trong lần rà soát này. Đề xuất định dạng
  lại Blade/CSS/JavaScript thành nhiều dòng có thụt lề, không đổi giao diện hay
  nghiệp vụ.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ tạo bản ghi tài liệu này.
- Hành vi trước/sau: không thay đổi hành vi ứng dụng.
- Số liệu: 28 tệp tracked trước thay đổi có khoảng `2.035` dòng và `118.315` ký
  tự; bản hiện tại có `583` dòng tracked cộng `35` dòng từ 4 component mới, nhưng
  vẫn có tổng khoảng `111.867` ký tự. Nghĩa là số dòng giảm khoảng 70%, còn lượng
  ký tự chỉ giảm khoảng 5,4%.
- Điều không thay đổi: route, controller, model, database và giao diện đang chạy.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: `git diff --numstat`, `git diff --stat`, so sánh nội dung
  `HEAD:<file>` với file trong worktree và đếm ký tự/dòng.
- Kết quả: xác nhận phần lớn chênh lệch đến từ việc nén markup vào dòng dài,
  không phải xóa tương ứng 1.400–1.900 dòng chức năng.
- Việc chưa xác minh và lý do: chưa format lại vì người dùng mới yêu cầu giải
  thích, chưa yêu cầu thay đổi mã ở lượt này.

## Nguồn tham khảo

- Nguồn nội bộ: Git `HEAD`, `resources/views/**/*.blade.php`,
  `resources/css/app.css`, `resources/js/app.js` — số liệu diff và nội dung trước/sau.
- Nguồn nội bộ: `Doc/History/2026-09-01_0100_redesign-all-interfaces.md` — phạm vi
  thay đổi giao diện vừa thực hiện.
- Nguồn yêu cầu: câu hỏi của người dùng ngày `2026-09-01`.

## Việc tiếp theo

- [ ] Nếu được yêu cầu, format lại toàn bộ Blade/CSS/JavaScript để dễ đọc và tạo
  diff phản ánh đúng cấu trúc, sau đó chạy lại build/test.
