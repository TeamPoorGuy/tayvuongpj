/**
 * Trả về chuỗi ngày YYYY-MM-DD theo múi giờ địa phương (Việt Nam UTC+7)
 * Giải quyết triệt để lỗi lệch múi giờ của toISOString() trước 7h sáng.
 */
export function getLocalTodayString(): string {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
