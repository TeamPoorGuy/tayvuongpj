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

/** 'YYYY-MM-DD' → "Hôm nay · 11/10/2026" / "Ngày mai · …" / "T5 · …" (không lệch múi giờ). */
export function formatDay(dateStr: string): string {
  const [year, month, day] = dateStr.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const diff = Math.round((date.getTime() - today.getTime()) / 86_400_000)
  const label = diff === 0 ? 'Hôm nay' : diff === 1 ? 'Ngày mai' : ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][date.getDay()]
  return `${label} · ${date.toLocaleDateString('vi-VN')}`
}

/** Cộng n ngày vào 'YYYY-MM-DD' theo giờ địa phương. */
export function addDays(dateStr: string, days: number): string {
  const [year, month, day] = dateStr.split('-').map(Number)
  const date = new Date(year, month - 1, day + days)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
