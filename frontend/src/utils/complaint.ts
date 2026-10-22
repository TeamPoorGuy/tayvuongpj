/** Nhãn loại khiếu nại cho cả hai chiều (khách → chủ sân và chủ sân → khách). */
export const complaintCategoryLabels: Record<string, string> = {
  refund_pending: 'Chưa nhận được tiền hoàn cọc',
  field_mismatch: 'Sân không đúng thực tế/mô tả',
  owner_cancelled: 'Chủ sân tự ý huỷ/không nhận',
  bad_behavior: 'Thái độ phục vụ/hành vi xấu',
  wrong_no_show: 'Đã đến sân nhưng bị ghi vắng mặt',
  property_damage: 'Khách làm hư hỏng/mất tài sản',
  customer_misconduct: 'Khách gây rối/thái độ không phù hợp',
  overtime: 'Khách chơi quá giờ',
  other: 'Vấn đề khác',
}

/** Thân yêu cầu gửi khiếu nại/phản hồi: JSON thường, chuyển sang FormData khi có ảnh đính kèm. */
export function complaintBody(fields: Record<string, string | number | undefined>, photos: File[]): Record<string, unknown> | FormData {
  if (photos.length === 0) return fields
  const form = new FormData()
  for (const [key, value] of Object.entries(fields)) {
    if (value !== undefined) form.append(key, String(value))
  }
  photos.forEach((photo) => form.append('photos[]', photo))
  return form
}
