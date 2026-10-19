import { useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import type { ApiEnvelope, PublicSettings } from '../types/api'

export const DEFAULT_PUBLIC_SETTINGS: PublicSettings = {
  commission_percent: 8,
  statement_due_days: 7,
  deposit_percent: 30,
  payment_window_hours: 2,
  free_cancel_hours: 4,
  max_pending_bookings: 3,
  max_advance_days: 30,
  platform_bank_name: 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)',
  platform_bank_code: 'VCB',
  platform_bank_account: '1234567890',
  platform_bank_holder: 'CONG TY SPORTHUB VIET NAM',
  support_email: 'hotro@sporthub.vn',
  support_hotline: '1900 6868',
  owner_terms_version: '2026-10',
}

export function usePublicSettings() {
  return useQuery({
    queryKey: ['settings', 'public'],
    queryFn: async () => {
      try {
        const res = await api.get<ApiEnvelope<PublicSettings>>('/settings/public')
        return res.data.data
      } catch {
        return DEFAULT_PUBLIC_SETTINGS
      }
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: DEFAULT_PUBLIC_SETTINGS,
  })
}
