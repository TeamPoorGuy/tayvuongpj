import { useMutation } from '@tanstack/react-query'
import { Eye } from 'lucide-react'
import { useState } from 'react'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import type { Booking } from '../types/api'
import { formatDateTime, formatMoney } from '../utils/format'
import { ConfirmModal } from './ConfirmModal'
import { PaymentProofModal } from './PaymentProofModal'
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
  const [confirmAction, setConfirmAction] = useState<'received' | 'not_received' | null>(null)
  const [showProofModal, setShowProofModal] = useState(false)

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
    onSuccess: (_, received) => {
      done(received ? `Đã xác nhận nhận tiền đơn #BK-${booking.id}.` : `Đã báo chưa nhận được tiền đơn #BK-${booking.id}.`)
      setConfirmAction(null)
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể cập nhật thanh toán'),
  })

  const busy = approve.isPending || resolve.isPending
  const payment = booking.payment
  const reject = (
    <button type="button" className={`button button--danger ${size}`} onClick={onReject} disabled={busy}>
      Từ chối
    </button>
  )

  return (
    <>
      {payment?.status === 'reported' && (
        <>
          <small style={{ flexBasis: '100%' }}>
            Khách báo đã chuyển <strong>{formatMoney(payment.amount ?? 0)}</strong> ({payment.option === 'full' ? 'trả hết' : 'đặt cọc'}),
            nội dung <strong>{payment.code}</strong>. Hãy kiểm tra sao kê.
          </small>
          {payment.proof_url && (
            <button
              type="button"
              className={`button button--ghost ${size}`}
              onClick={() => setShowProofModal(true)}
              title="Xem ảnh chứng từ chuyển khoản do khách đính kèm"
            >
              <Eye size={14} /> Xem biên lai
            </button>
          )}
          <button
            type="button"
            className={`button button--brand ${size}`}
            onClick={() => setConfirmAction('received')}
            disabled={busy}
          >
            Đã nhận tiền
          </button>
          <button
            type="button"
            className={`button button--ghost ${size}`}
            onClick={() => setConfirmAction('not_received')}
            disabled={busy}
          >
            Chưa nhận được
          </button>
        </>
      )}

      {payment?.status === 'awaiting' && (
        <>
          <small style={{ flexBasis: '100%' }}>
            Chờ khách chuyển khoản
            {payment.due_at && ` (hạn ${formatDateTime(payment.due_at)})`}
            , nội dung <strong>{payment.code}</strong>.
          </small>
          {reject}
        </>
      )}

      {!payment?.status && (
        <>
          <button type="button" className={`button button--brand ${size}`} onClick={() => approve.mutate()} disabled={busy}>
            Duyệt
          </button>
          {reject}
        </>
      )}

      {/* Confirmation modal for "Đã nhận tiền" */}
      <ConfirmModal
        isOpen={confirmAction === 'received'}
        onClose={() => setConfirmAction(null)}
        onConfirm={() => resolve.mutate(true)}
        title={`Xác nhận đã nhận tiền đơn #BK-${booking.id}`}
        message={`Bạn xác nhận đã nhận đủ số tiền ${formatMoney(payment?.amount ?? 0)} từ khách hàng? Đơn sẽ được chuyển sang trạng thái Đã xác nhận.`}
        confirmText="Xác nhận đã nhận"
        cancelText="Kiểm tra lại"
        danger={false}
        busy={resolve.isPending}
      />

      {/* Confirmation modal for "Chưa nhận được" */}
      <ConfirmModal
        isOpen={confirmAction === 'not_received'}
        onClose={() => setConfirmAction(null)}
        onConfirm={() => resolve.mutate(false)}
        title={`Báo chưa nhận được tiền đơn #BK-${booking.id}`}
        message={`Bạn chưa nhận được số tiền ${formatMoney(payment?.amount ?? 0)} từ khách hàng? Đơn sẽ quay lại trạng thái Chờ thanh toán để khách hàng kiểm tra lại hoặc chuyển khoản tiếp.`}
        confirmText="Báo chưa nhận"
        cancelText="Đóng"
        danger={true}
        busy={resolve.isPending}
      />

      {/* Payment Proof Modal */}
      {payment?.proof_url && (
        <PaymentProofModal
          isOpen={showProofModal}
          onClose={() => setShowProofModal(false)}
          proofUrl={payment.proof_url}
          title={`Biên lai đơn #BK-${booking.id}`}
        />
      )}
    </>
  )
}
