import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote } from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import type { Paginated, Payment } from '../../types/api'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'
import { Empty, PageTitle } from '../customer/CustomerPages'

const STATUS_LABELS: Record<Payment['status'], string> = {
  awaiting: 'Chờ chuyển khoản',
  paid: 'Đã nhận tiền',
  expired: 'Hết hạn giữ chỗ',
  cancelled: 'Khách đã huỷ',
  refund_required: 'Cần hoàn tiền',
}

/** Giao dịch thu hộ: đối chiếu tay khi webhook ngân hàng không khớp (khách ghi sai nội dung…). */
export function AdminPaymentsPage() {
  const toast = useToast()
  const client = useQueryClient()
  const [status, setStatus] = useState<string>('awaiting')
  const [keyword, setKeyword] = useState('')
  const [confirming, setConfirming] = useState<Payment | null>(null)

  const payments = useQuery({
    queryKey: ['admin', 'payments', status, keyword],
    queryFn: async () => (await api.get<Paginated<Payment>>('/admin/payments', { params: { status: status || undefined, keyword: keyword || undefined } })).data,
    refetchInterval: 15_000,
  })

  const confirm = useMutation({
    mutationFn: async (payment: Payment) => api.post(`/admin/payments/${payment.id}/confirm`),
    onSuccess: (res) => {
      toast.success(res.data?.message ?? 'Đã ghi nhận tiền.')
      setConfirming(null)
      void client.invalidateQueries({ queryKey: ['admin', 'payments'] })
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể ghi nhận'),
  })

  return (
    <>
      <PageTitle
        icon={<Banknote />}
        kicker="Thu hộ"
        title="Giao dịch thanh toán"
        copy="Tiền khách chuyển vào tài khoản SportHub được ghi nhận tự động qua webhook ngân hàng. Dùng trang này khi cần đối chiếu tay với sao kê."
      />

      <div className="filter-bar" style={{ marginBottom: 16 }}>
        <select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Trạng thái">
          <option value="">Tất cả trạng thái</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>{label}</option>
          ))}
        </select>
        <input value={keyword} onChange={(e) => setKeyword(e.target.value.toUpperCase())} maxLength={20} placeholder="Mã chuyển khoản, vd SH12AB34" />
      </div>

      <QueryState
        isPending={payments.isPending}
        isError={payments.isError}
        isEmpty={!payments.data?.data.length}
        error={errorMessage(payments.error)}
        empty={<Empty text="Không có giao dịch phù hợp." />}
      >
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mã</th>
                <th>Khách</th>
                <th>Lịch đặt</th>
                <th>Số tiền</th>
                <th>Trạng thái</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {payments.data?.data.map((p) => (
                <tr key={p.id}>
                  <td><code>{p.code}</code><br /><small>{formatDateTime(p.created_at)}</small></td>
                  <td>{p.customer?.name}<br /><small>{p.customer?.phone}</small></td>
                  <td>
                    {p.bookings?.map((b) => (
                      <div key={b.id}><small>{b.field?.name} · {formatDate(b.booking_date)} {b.time_slot?.start_time}–{b.time_slot?.end_time}</small></div>
                    ))}
                  </td>
                  <td>
                    <strong>{formatMoney(p.amount)}</strong>
                    <br /><small>{p.option === 'full' ? 'Trả hết' : 'Đặt cọc'} / tổng {formatMoney(p.total)}</small>
                  </td>
                  <td>
                    {STATUS_LABELS[p.status]}
                    {p.status === 'awaiting' && <><br /><small>hạn {formatDateTime(p.due_at)}</small></>}
                    {p.paid_at && <><br /><small>{p.source === 'admin' ? 'Admin xác nhận' : 'Webhook'} {formatDateTime(p.paid_at)}</small></>}
                  </td>
                  <td>
                    {['awaiting', 'expired', 'cancelled'].includes(p.status) && (
                      <button type="button" className="button button--brand button--small" onClick={() => setConfirming(p)}>
                        Đã nhận tiền
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </QueryState>

      <ConfirmModal
        isOpen={!!confirming}
        onClose={() => setConfirming(null)}
        onConfirm={() => confirming && confirm.mutate(confirming)}
        title="Xác nhận đã nhận tiền?"
        message={confirming ? `Chỉ xác nhận khi sao kê tài khoản SportHub đã có ${formatMoney(confirming.amount)} với nội dung ${confirming.code}. Đơn sẽ được xác nhận ngay (hoặc chuyển sang chờ hoàn tiền nếu chỗ đã bị người khác đặt).` : ''}
        confirmText="Đã nhận tiền"
        busy={confirm.isPending}
      />
    </>
  )
}
