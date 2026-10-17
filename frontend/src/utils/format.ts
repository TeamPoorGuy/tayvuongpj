/**
 * Định dạng tiền tệ và thời gian chuẩn cho toàn bộ ứng dụng SportHub.
 */

export const currencyFormatter = new Intl.NumberFormat('vi-VN')

/**
 * Format số tiền theo chuẩn Việt Nam.
 * @param amount Số tiền cần format (number, string, null, undefined)
 * @param withUnit Có kèm ký tự 'đ' ở cuối hay không (mặc định: true)
 * @example formatMoney(150000) => "150.000đ"
 * @example formatMoney(150000, false) => "150.000"
 */
export function formatMoney(amount: number | string | null | undefined, withUnit = true): string {
  if (amount === null || amount === undefined || amount === '') {
    return withUnit ? '0đ' : '0'
  }
  const num = typeof amount === 'string' ? Number(amount) : amount
  const validNum = Number.isFinite(num) ? num : 0
  const formatted = currencyFormatter.format(validNum)
  return withUnit ? `${formatted}đ` : formatted
}

/**
 * Format ngày theo định dạng Việt Nam: DD/MM/YYYY.
 */
export function formatDate(date: string | Date | null | undefined): string {
  if (!date) return ''
  const d = typeof date === 'string' ? new Date(date) : date
  return isNaN(d.getTime()) ? '' : d.toLocaleDateString('vi-VN')
}

/**
 * Format ngày giờ theo định dạng Việt Nam: HH:mm DD/MM/YYYY.
 */
export function formatDateTime(date: string | Date | null | undefined): string {
  if (!date) return ''
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return ''
  return `${d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })} ${d.toLocaleDateString('vi-VN')}`
}
