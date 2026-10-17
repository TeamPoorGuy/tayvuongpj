import { useMutation } from '@tanstack/react-query'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import type { Booking } from '../types/api'
import { formatDateTime, formatMoney } from '../utils/format'
import { useToast } from './Toast'


/**
 * Nút thao tác của chủ sân trên một đơn đang chờ, theo từng bước thanh toán:
 * chưa duyệt → Duyệt/Từ chối; chờ khách chuyển → hiện hạn; khách báo đã chuyển → Đã nhận tiền/Chưa nhận được.
 */
export function OwnerPaymentActions({
  booking,
  onReject,
  onDone,
  size = 'button--small',
}: {
  booking: Booking
  onReject: () => void
  onDone?: () => void
  size?: string
}) {
  const toast = useToast()
  const done = (message: string) => {
    toast.success(message)
    onDone?.()
  }

  const approve = useMutation({
    mutationFn: async () => api.patch(`/field-owner/bookings/${booking.id}/approve`),
    onSuccess: () => done(`Đã duyệt đơn #BK-${booking.id}. Đang chờ khách chuyển khoản.`),
    onError: (err) => toast.error(errorMessage(err), 'Không thể duyệt đơn'),
  })

  const resolve = useMutation({
    mutationFn: async (received: boolean) => api.patch(`/field-owner/bookings/${booking.id}/payment`, { received }),
    onSuccess: (_, received) =>
      done(received ? `Đã xác nhận nhận tiền đơn #BK-${booking.id}.` : `Đã báo chưa nhận được tiền đơn #BK-${booking.id}.`),
    onError: (err) => toast.error(errorMessage(err), 'Không thể cập nhật thanh toán'),
  })

  const busy = approve.isPending || resolve.isPending
  const payment = booking.payment
  const reject = (
    <button type="button" className={`button button--danger ${size}`} onClick={onReject} disabled={busy}>
      Từ chối
    </button>
  )

  if (payment?.status === 'reported') {
    return (
      <>
        <small style={{ flexBasis: '100%' }}>
          Khách báo đã chuyển <strong>{formatMoney(payment.amount ?? 0)}</strong> ({payment.option === 'full' ? 'trả hết' : 'đặt cọc'}),
          nội dung <strong>{payment.code}</strong>. Hãy kiểm tra sao kê.
        </small>
        <button type="button" className={`button button--brand ${size}`} onClick={() => resolve.mutate(true)} disabled={busy}>
          Đã nhận tiền
        </button>
        <button type="button" className={`button button--ghost ${size}`} onClick={() => resolve.mutate(false)} disabled={busy}>
          Chưa nhận được
        </button>
      </>
    )
  }

  if (payment?.status === 'awaiting') {
    return (
      <>
        <small style={{ flexBasis: '100%' }}>
          Chờ khách chuyển khoản
          {payment.due_at && ` (hạn ${formatDateTime(payment.due_at)})`}
          , nội dung <strong>{payment.code}</strong>.
        </small>
        {reject}
      </>
    )
  }


  return (
    <>
      <button type="button" className={`button button--brand ${size}`} onClick={() => approve.mutate()} disabled={busy}>
        Duyệt
      </button>
      {reject}
    </>
  )
}
