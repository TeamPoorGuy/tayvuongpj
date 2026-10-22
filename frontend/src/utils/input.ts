/** Chỉ giữ chữ số, cắt theo độ dài tối đa. */
export const digitsOnly = (value: string, max = 20) => value.replace(/\D/g, '').slice(0, max)

/** Tên chủ tài khoản ngân hàng: in hoa, không dấu, chỉ A-Z và khoảng trắng (đúng yêu cầu VietQR). */
export const toBankHolder = (value: string) =>
  value
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toUpperCase()
    .replace(/[^A-Z ]/g, '')
    .slice(0, 50)
