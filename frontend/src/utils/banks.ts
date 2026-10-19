export interface BankInfo {
  bin: string
  short_name: string
  name: string
}

export const VIETNAMESE_BANKS: BankInfo[] = [
  { bin: '970436', short_name: 'Vietcombank', name: 'Ngân hàng TMCP Ngoại Thương Việt Nam (Vietcombank)' },
  { bin: '970415', short_name: 'VietinBank', name: 'Ngân hàng TMCP Công Thương Việt Nam (VietinBank)' },
  { bin: '970418', short_name: 'BIDV', name: 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam (BIDV)' },
  { bin: '970405', short_name: 'Agribank', name: 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam (Agribank)' },
  { bin: '970422', short_name: 'MBBank', name: 'Ngân hàng TMCP Quân đội (MB)' },
  { bin: '970407', short_name: 'Techcombank', name: 'Ngân hàng TMCP Kỹ Thương Việt Nam (Techcombank)' },
  { bin: '970432', short_name: 'VPBank', name: 'Ngân hàng TMCP Việt Nam Thịnh Vượng (VPBank)' },
  { bin: '970423', short_name: 'TPBank', name: 'Ngân hàng TMCP Tiên Phong (TPBank)' },
  { bin: '970403', short_name: 'Sacombank', name: 'Ngân hàng TMCP Sài Gòn Thương Tín (Sacombank)' },
  { bin: '970437', short_name: 'HDBank', name: 'Ngân hàng TMCP Phát triển TP.HCM (HDBank)' },
  { bin: '970441', short_name: 'VIB', name: 'Ngân hàng TMCP Quốc tế Việt Nam (VIB)' },
  { bin: '970416', short_name: 'ACB', name: 'Ngân hàng TMCP Á Châu (ACB)' },
  { bin: '970443', short_name: 'SHB', name: 'Ngân hàng TMCP Sài Gòn - Hà Nội (SHB)' },
  { bin: '970431', short_name: 'Eximbank', name: 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam (Eximbank)' },
  { bin: '970426', short_name: 'MSB', name: 'Ngân hàng TMCP Hàng Hải Việt Nam (MSB)' },
  { bin: '970428', short_name: 'Nam A Bank', name: 'Ngân hàng TMCP Nam Á (Nam A Bank)' },
  { bin: '970429', short_name: 'SCB', name: 'Ngân hàng TMCP Sài Gòn (SCB)' },
  { bin: '970414', short_name: 'OceanBank', name: 'Ngân hàng Thương mại TNHH MTV Đại Dương (OceanBank)' },
  { bin: '970440', short_name: 'SeABank', name: 'Ngân hàng TMCP Đông Nam Á (SeABank)' },
  { bin: '970427', short_name: 'VietABank', name: 'Ngân hàng TMCP Việt Á (VietABank)' },
  { bin: '970438', short_name: 'BaoVietBank', name: 'Ngân hàng TMCP Bảo Việt (BaoViet Bank)' },
  { bin: '970454', short_name: 'VietCapitalBank', name: 'Ngân hàng TMCP Bản Việt (BVBank)' },
]

export function guessBin(bankName?: string | null): string | null {
  if (!bankName) return null
  const normalized = bankName.toLowerCase().replace(/[^a-z0-9]/g, '')
  for (const b of VIETNAMESE_BANKS) {
    const shortNorm = b.short_name.toLowerCase().replace(/[^a-z0-9]/g, '')
    const fullNorm = b.name.toLowerCase().replace(/[^a-z0-9]/g, '')
    if (normalized.includes(shortNorm) || shortNorm.includes(normalized) || normalized.includes(fullNorm)) {
      return b.bin
    }
  }
  return null
}
