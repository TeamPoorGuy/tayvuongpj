import { Clock } from 'lucide-react'
import type { Booking } from '../types/api'
import { formatDateTime } from '../utils/format'

/**
 * Đơn đang chờ của chủ sân: khách giữ chỗ và chuyển khoản vào tài khoản SportHub, đơn tự xác nhận khi có tiền
 * nên chủ sân không cần duyệt. Chỉ đơn kiểu cũ (chưa gắn lượt thanh toán) mới còn nút từ chối.
 */
export function OwnerPaymentActions({
  booking,
  onReject,
  size = 'button--small',
}: {
  booking: Booking
  onReject: () => void
  onDone?: () => void
  size?: string
}) {
  if (!booking.payment?.id) {
    return (
      <button type="button" className={`button button--danger ${size}`} onClick={onReject}>
        Từ chối
      </button>
    )
  }

  return (
    <span className="form-help" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, margin: 0 }}>
      <Clock size={14} /> Khách đang thanh toán
      {booking.payment.due_at ? ` (giữ chỗ đến ${formatDateTime(booking.payment.due_at)})` : ''}
    </span>
  )
}
