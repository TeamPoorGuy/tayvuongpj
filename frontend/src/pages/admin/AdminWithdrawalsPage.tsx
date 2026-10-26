import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Wallet } from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import type { Paginated, Withdrawal } from '../../types/api'
import { formatDateTime, formatMoney } from '../../utils/format'
import { Empty, PageTitle } from '../customer/CustomerPages'

const STATUS: Record<Withdrawal['status'], { label: string; className: string }> = {
  pending: { label: 'Chờ chuyển khoản', className: 'status status--pending' },
  paid: { label: 'Đã chuyển', className: 'status status--approved' },
  rejected: { label: 'Đã từ chối', className: 'status status--rejected' },
}

/** Lệnh rút ví cần admin chuyển khoản (lệnh nhỏ đã tự duyệt nên không xuất hiện ở tab "Chờ"). */
export function AdminWithdrawalsPage() {
  const toast = useToast()
  const client = useQueryClient()
  const [status, setStatus] = useState<'pending' | 'paid' | 'rejected'>('pending')
  const [page, setPage] = useState(1)
  const [paying, setPaying] = useState<Withdrawal | null>(null)
  const [rejecting, setRejecting] = useState<Withdrawal | null>(null)
  const [reference, setReference] = useState('')
  const [reason, setReason] = useState('')

  const list = useQuery({
    queryKey: ['admin', 'withdrawals', status, page],
    refetchInterval: 30_000,
    queryFn: async () => (await api.get<Paginated<Withdrawal>>('/admin/withdrawals', { params: { status, page } })).data,
  })

  const done = () => {
    void client.invalidateQueries({ queryKey: ['admin'] })
    setPaying(null)
    setRejecting(null)
    setReference('')
    setReason('')
  }

  const pay = useMutation({
    mutationFn: async () => (await api.post<{ message: string }>(`/admin/withdrawals/${paying?.id}/paid`, { reference: reference.trim() || undefined })).data,
    onSuccess: (res) => {
      toast.success(res.message)
      done()
    },
    onError: (err) => toast.error(errorMessage(err), 'Không ghi nhận được'),
  })

  const reject = useMutation({
    mutationFn: async () => (await api.post<{ message: string }>(`/admin/withdrawals/${rejecting?.id}/reject`, { reason })).data,
    onSuccess: (res) => {
      toast.success(res.message)
      done()
    },
    onError: (err) => toast.error(errorMessage(err), 'Không từ chối được'),
  })

  const items = list.data?.data ?? []

  return (
    <>
      <PageTitle
        icon={<Wallet />}
        kicker="Tài chính"
        title="Lệnh rút tiền"
        copy="Lệnh rút nhỏ của người không có khiếu nại tự được duyệt và chuyển ngay. Lệnh lớn hơn hạn mức (chỉnh trong Cấu hình sàn) hoặc của người đang có khiếu nại chờ bạn quét QR chuyển khoản rồi bấm xác nhận."
      />

      <div className="toolbar">
        <select value={status} onChange={(e) => { setStatus(e.target.value as typeof status); setPage(1) }} aria-label="Lọc theo trạng thái">
          <option value="pending">Chờ chuyển khoản</option>
          <option value="paid">Đã chuyển</option>
          <option value="rejected">Đã từ chối</option>
        </select>
      </div>

      <QueryState isPending={list.isPending} isError={list.isError} isEmpty={items.length === 0} empty={<Empty text="Không có lệnh rút nào." />}>
        <section className="data-panel">
          <div className="table-wrap">
            <table>
              <thead><tr><th>Người rút</th><th>Số tiền</th><th>Về tài khoản</th><th>Trạng thái</th><th style={{ textAlign: 'right' }}>Thao tác</th></tr></thead>
              <tbody>
                {items.map((w) => (
                  <tr key={w.id}>
                    <td><strong>{w.user?.name}</strong><small style={{ display: 'block', color: 'var(--muted)' }}>{w.user?.email}</small><small>{formatDateTime(w.created_at)}</small></td>
                    <td><strong>{formatMoney(w.amount)}</strong></td>
                    <td>{w.bank.name} · {w.bank.account_number}<small style={{ display: 'block' }}>{w.bank.account_holder}</small></td>
                    <td>
                      <span className={STATUS[w.status].className}><i />{STATUS[w.status].label}</span>
                      {w.auto && <small style={{ display: 'block' }}>Tự động duyệt</small>}
                      {w.reference && <small style={{ display: 'block' }}>GD: {w.reference}</small>}
                      {w.reason && <small style={{ display: 'block' }}>{w.reason}</small>}
                    </td>
                    <td>
                      {w.status === 'pending' && (
                        <div className="row-actions">
                          <button type="button" className="button button--brand button--small" onClick={() => setPaying(w)}>Chuyển khoản</button>
                          <button type="button" className="button button--ghost button--small" onClick={() => setRejecting(w)}>Từ chối</button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} lastPage={list.data?.meta.last_page ?? 1} onPageChange={setPage} />
        </section>
      </QueryState>

      <Modal isOpen={!!paying} onClose={() => setPaying(null)} title={`Chuyển ${paying ? formatMoney(paying.amount) : ''} cho ${paying?.user?.name ?? ''}`}>
        {paying && (
          <div className="panel-form" style={{ display: 'grid', gap: 14, maxWidth: 440 }}>
            {paying.bank.qr_url && <img src={paying.bank.qr_url} alt="Mã QR chuyển khoản" style={{ width: 220, maxWidth: '100%', justifySelf: 'center', borderRadius: 8 }} />}
            <dl className="payment-rows">
              <div><dt>Ngân hàng</dt><dd>{paying.bank.name}</dd></div>
              <div><dt>Số tài khoản</dt><dd>{paying.bank.account_number}</dd></div>
              <div><dt>Chủ tài khoản</dt><dd>{paying.bank.account_holder}</dd></div>
              <div><dt>Số tiền</dt><dd>{formatMoney(paying.amount)}</dd></div>
            </dl>
            <label>
              <span>Mã giao dịch ngân hàng (tuỳ chọn)</span>
              <input value={reference} maxLength={100} onChange={(e) => setReference(e.target.value)} placeholder="Ví dụ: FT26102112345" />
            </label>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <button type="button" className="button button--ghost" onClick={() => setPaying(null)}>Đóng</button>
              <button type="button" className="button button--brand" disabled={pay.isPending} onClick={() => pay.mutate()}>{pay.isPending ? 'Đang ghi nhận...' : 'Đã chuyển khoản'}</button>
            </div>
          </div>
        )}
      </Modal>

      <Modal isOpen={!!rejecting} onClose={() => setRejecting(null)} title="Từ chối lệnh rút" asForm onSubmit={(e) => { e.preventDefault(); reject.mutate() }}>
        <div className="form-stack" style={{ maxWidth: 420 }}>
          <p className="form-help" style={{ margin: 0 }}>Tiền {rejecting ? formatMoney(rejecting.amount) : ''} sẽ được trả lại ví của người rút.</p>
          <label>
            <span>Lý do (gửi cho người rút)</span>
            <textarea required minLength={5} maxLength={500} rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ví dụ: Tên chủ tài khoản không khớp." />
          </label>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button type="button" className="button button--ghost" onClick={() => setRejecting(null)}>Đóng</button>
            <button type="submit" className="button button--danger" disabled={reject.isPending}>Từ chối</button>
          </div>
        </div>
      </Modal>
    </>
  )
}
