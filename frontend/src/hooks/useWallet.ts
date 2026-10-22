import { useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import type { WalletData } from '../types/api'

interface WalletResponse {
  data: WalletData
  meta: { current_page: number; last_page: number; total: number }
}

/** Ví của người đang đăng nhập (số dư + giao dịch gần đây, phân trang). */
export function useWallet(page = 1, enabled = true) {
  return useQuery({
    queryKey: ['wallet', page],
    enabled,
    queryFn: async () => (await api.get<WalletResponse>('/wallet', { params: { page } })).data,
  })
}
