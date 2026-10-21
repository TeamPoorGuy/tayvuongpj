import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Coins, Download, Eye, Plus, Search, Send } from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import type {
  AdminDashboard,
  ApiEnvelope,
  OwnerStatement,
  StatementList,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { formatDate, formatMoney } from '../../utils/format'

export function AdminStatementsPage() {
  const qc = useQueryClient()
  const toast = useToast()

  const [page, setPage] = useState(1)
  const [periodFilter, setPeriodFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, 300)

  // State modals
  const [generateModalOpen, setGenerateModalOpen] = useState(false)
  const [genPeriod, setGenPeriod] = useState(() => {
    const d = new Date()
    d.setMonth(d.getMonth() - 1)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  })
  const [payoutTarget, setPayoutTarget] = useState<OwnerStatement | null>(null)
  const [payoutRef, setPayoutRef] = useState('')
  const [detailTarget, setDetailTarget] = useState<OwnerStatement | null>(null)

  // 1. Dashboard metrics
  const dashboardQuery = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: async () => (await api.get<ApiEnvelope<AdminDashboard>>('/admin/dashboard')).data.data,
  })

  // 2. Danh sách đối soát
  const statementsQuery = useQuery({
    queryKey: ['admin', 'statements', page, periodFilter, statusFilter, debouncedSearch],
    queryFn: async () => {
      const params = new URLSearchParams()
      params.set('page', String(page))
      params.set('per_page', '15')
      if (periodFilter) params.set('period', periodFilter)
      if (statusFilter) params.set('status', statusFilter)
      if (debouncedSearch) params.set('keyword', debouncedSearch)
      return (await api.get<StatementList>(`/admin/statements?${params.toString()}`)).data
    },
  })

  // 3. Chi tiết statement (kèm bookings)
  const detailQuery = useQuery({
    queryKey: ['admin', 'statements', 'detail', detailTarget?.id],
    queryFn: async () =>
      (await api.get<ApiEnvelope<OwnerStatement>>(`/admin/statements/${detailTarget?.id}`)).data.data,
    enabled: Boolean(detailTarget?.id),
  })

  // 4. Sinh kỳ đối soát
  const generateMutation = useMutation({
    mutationFn: async (period: string) => {
      return (await api.post<ApiEnvelope<OwnerStatement[]>>('/admin/statements/generate', { period })).data
    },
    onSuccess: (res) => {
      toast.success(res.message || 'Đã sinh kỳ đối soát thành công!')
      qc.invalidateQueries({ queryKey: ['admin', 'statements'] })
      qc.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      setGenerateModalOpen(false)
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  // 5. Ghi nhận đã chuyển khoản chi trả cho chủ sân
  const payoutMutation = useMutation({
    mutationFn: async ({ id, ref }: { id: number; ref: string }) =>
      (await api.post<ApiEnvelope<OwnerStatement>>(`/admin/statements/${id}/payout`, { payout_ref: ref || null })).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Đã ghi nhận chi trả cho chủ sân!')
      qc.invalidateQueries({ queryKey: ['admin', 'statements'] })
      qc.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      setPayoutTarget(null)
      setPayoutRef('')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const stats = dashboardQuery.data
  const activeDetail = detailQuery.data || detailTarget
  const list = statementsQuery.data?.data ?? []
  const totals = statementsQuery.data?.meta?.totals

  const renderStatus = (st: OwnerStatement) => {
    if (st.status === 'paid') {
      return <span className="status status--approved"><i /> Đã chi trả</span>
    }
    if (st.is_overdue) {
      return <span className="status status--cancelled"><i /> Trễ hạn chi trả</span>
    }
    return <span className="status status--pending"><i /> Chờ chi trả</span>
  }

  return (
    <>
      <PageTitle
        icon={<Coins />}
        kicker="Quản trị tài chính"
        title="Đối soát & Chi trả chủ sân"
        copy="SportHub thu hộ tiền khách; mỗi tháng chuyển cho chủ sân số đã thu sau khi trừ tiền hoàn khách và hoa hồng nền tảng."
        action={
          <button
            type="button"
            className="button button--brand"
            onClick={() => setGenerateModalOpen(true)}
          >
            <Plus size={16} /> Lập kỳ đối soát
          </button>
        }
      />

      {/* Thẻ chỉ số chi trả */}
      <div className="stats-grid-3" style={{ marginBottom: 24 }}>
        <div className="stat-card">
          <span className="stat-card__label">Còn phải chi trả chủ sân</span>
          <strong className="stat-card__val" style={{ color: 'var(--warn, #e29a0c)' }}>{formatMoney(totals?.unpaid_amount ?? 0)}</strong>
          <small className="stat-card__note">{totals?.unpaid_count ?? 0} kỳ chưa chuyển khoản</small>
        </div>
        <div className="stat-card">
          <span className="stat-card__label">Đã chi trả chủ sân</span>
          <strong className="stat-card__val">{formatMoney(totals?.paid_amount ?? 0)}</strong>
          <small className="stat-card__note">{totals?.paid_count ?? 0} kỳ đã chuyển khoản</small>
        </div>
        <div className="stat-card stat-card--highlight">
          <span className="stat-card__label">Hoa hồng nền tảng</span>
          <strong className="stat-card__val">{formatMoney((stats?.total_commission_collected ?? 0) + (stats?.total_commission_pending ?? 0))}</strong>
          <small className="stat-card__note">Đã trừ thẳng từ tiền thu hộ khi đối soát</small>
        </div>
      </div>

      {/* Bộ lọc chuẩn hệ thống */}
      <div className="toolbar">
        <label className="search-control">
          <Search size={16} />
          <input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Tìm theo mã CK, tên cơ sở, chủ sân..."
            aria-label="Tìm theo mã CK, tên cơ sở, chủ sân"
          />
        </label>
        <input
          type="text"
          placeholder="Kỳ YYYY-MM"
          value={periodFilter}
          onChange={(e) => {
            setPeriodFilter(e.target.value)
            setPage(1)
          }}
          style={{ maxWidth: 150 }}
          aria-label="Lọc theo kỳ đối soát"
        />
        <select
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value)
            setPage(1)
          }}
          aria-label="Lọc theo trạng thái"
        >
          <option value="">Tất cả trạng thái</option>
          <option value="unpaid">Chờ chi trả</option>
          <option value="paid">Đã chi trả</option>
        </select>
        <a
          href={`/api/v1/admin/statements/export-csv?period=${encodeURIComponent(periodFilter)}&status=${encodeURIComponent(statusFilter)}&keyword=${encodeURIComponent(debouncedSearch)}`}
          className="button button--ghost"
          download
          style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none', marginLeft: 'auto' }}
          title="Tải về file CSV toàn bộ bảng đối soát theo bộ lọc"
        >
          <Download size={15} /> Xuất CSV
        </a>
      </div>

      <QueryState
        isPending={statementsQuery.isPending}
        isError={statementsQuery.isError}
        isEmpty={list.length === 0}
        error={errorMessage(statementsQuery.error)}
        empty={<Empty text="Không tìm thấy bảng đối soát nào. Thử thay đổi bộ lọc hoặc bấm 'Sinh kỳ đối soát' để hệ thống tổng hợp." />}
      >
        <section className="data-panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Cơ sở / Chủ sân</th>
                  <th>Kỳ / Số đơn</th>
                  <th>Thu hộ / Hoàn</th>
                  <th>Hoa hồng</th>
                  <th>Chi trả</th>
                  <th>Nội dung CK / Hạn</th>
                  <th>Trạng thái</th>
                  <th style={{ textAlign: 'right' }}>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                {list.map((st) => (
                  <tr key={st.id}>
                    <td>
                      <strong>{st.owner?.business_name || '—'}</strong>
                      <small style={{ display: 'block', color: 'var(--muted)' }}>
                        {st.owner?.user_name} ({st.owner?.user_phone || st.owner?.user_email})
                      </small>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      <strong>T{st.period}</strong>
                      <small style={{ display: 'block', color: 'var(--muted)' }}>{st.booking_count} đơn</small>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {formatMoney(st.collected_amount)}
                      <small style={{ display: 'block', color: 'var(--muted)' }}>hoàn {formatMoney(st.refunded_amount)}</small>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>{formatMoney(st.commission_amount)}</td>
                    <td>
                      <strong style={{ color: 'var(--forest)' }}>{formatMoney(st.payout_amount)}</strong>
                    </td>
                    <td>
                      <code style={{ fontSize: 12, background: 'var(--surface)', padding: '2px 6px', borderRadius: 'var(--r-sm)' }}>
                        {st.transfer_code}
                      </code>
                      <small style={{ display: 'block', color: st.is_overdue ? 'var(--danger)' : 'var(--muted)', marginTop: 2 }}>
                        Hạn: {st.due_at ? formatDate(st.due_at) : '—'}
                      </small>
                    </td>
                    <td>
                      {renderStatus(st)}
                      {st.payout_ref && <small style={{ display: 'block', color: 'var(--muted)' }}>GD: {st.payout_ref}</small>}
                    </td>
                    <td>
                      <div className="row-actions">
                        <button
                          type="button"
                          className="button button--ghost button--small"
                          onClick={() => setDetailTarget(st)}
                          title="Xem danh sách đơn"
                        >
                          <Eye size={13} /> Chi tiết
                        </button>
                        {st.status === 'unpaid' && (
                          <button
                            type="button"
                            className="button button--brand button--small"
                            onClick={() => {
                              setPayoutTarget(st)
                              setPayoutRef('')
                            }}
                            title="Chuyển khoản chi trả cho chủ sân"
                          >
                            <Send size={13} /> Chi trả
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination
            page={page}
            lastPage={statementsQuery.data?.meta?.last_page ?? 1}
            onPageChange={setPage}
          />
        </section>
      </QueryState>

      {/* Modal sinh kỳ đối soát */}
      <Modal
        isOpen={generateModalOpen}
        onClose={() => setGenerateModalOpen(false)}
        title="Lập bảng đối soát & chi trả hàng tháng"
      >
        <div className="panel-form" style={{ display: 'grid', gap: 16 }}>
          <p style={{ fontSize: 14, margin: 0, color: 'var(--muted)' }}>
            Hệ thống tổng hợp các đơn đã kết thúc và đã thanh toán qua SportHub trong tháng được chọn,
            tính tiền thu hộ, tiền hoàn cho khách, hoa hồng và số phải chi trả cho từng chủ sân.
          </p>
          <label>
            <span>Kỳ đối soát (Định dạng YYYY-MM):</span>
            <input
              id="genPeriod"
              type="month"
              placeholder="Ví dụ: 2026-09"
              value={genPeriod}
              onChange={(e) => setGenPeriod(e.target.value)}
            />
          </label>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 8 }}>
            <button
              type="button"
              className="button button--ghost"
              onClick={() => setGenerateModalOpen(false)}
            >
              Hủy
            </button>
            <button
              type="button"
              className="button button--brand"
              disabled={generateMutation.isPending || !/^\d{4}-\d{2}$/.test(genPeriod)}
              onClick={() => generateMutation.mutate(genPeriod)}
            >
              {generateMutation.isPending ? 'Đang tổng hợp...' : 'Lập đối soát'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal chi trả cho chủ sân */}
      <Modal
        isOpen={Boolean(payoutTarget)}
        onClose={() => setPayoutTarget(null)}
        title={`Chi trả tháng ${payoutTarget?.period} · ${payoutTarget?.owner?.business_name ?? ''}`}
      >
        {payoutTarget && (
          <div className="panel-form" style={{ display: 'grid', gap: 14 }}>
            <p style={{ fontSize: 14, margin: 0 }}>
              Chuyển <strong>{formatMoney(payoutTarget.payout_amount)}</strong> vào tài khoản của chủ sân với nội dung{' '}
              <code>{payoutTarget.transfer_code}</code>, rồi ghi mã giao dịch ngân hàng để đối chiếu.
            </p>
            {payoutTarget.payout_bank?.qr_url ? (
              <img src={payoutTarget.payout_bank.qr_url} alt="Mã QR chuyển khoản chi trả cho chủ sân" style={{ width: 220, maxWidth: '100%', justifySelf: 'center', borderRadius: 8 }} />
            ) : (
              <div className="notice notice--warn">Chủ sân chưa khai đủ tài khoản ngân hàng hợp lệ; liên hệ chủ sân trước khi chuyển.</div>
            )}
            <dl className="payment-rows">
              <div><dt>Ngân hàng</dt><dd>{payoutTarget.payout_bank?.bank_name ?? '—'}</dd></div>
              <div><dt>Số tài khoản</dt><dd>{payoutTarget.payout_bank?.account_number ?? '—'}</dd></div>
              <div><dt>Chủ tài khoản</dt><dd>{payoutTarget.payout_bank?.account_holder ?? '—'}</dd></div>
            </dl>
            <label>
              <span>Mã giao dịch ngân hàng (tuỳ chọn)</span>
              <input value={payoutRef} maxLength={100} onChange={(e) => setPayoutRef(e.target.value)} placeholder="Ví dụ: FT26102112345" />
            </label>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <button type="button" className="button button--ghost" onClick={() => setPayoutTarget(null)}>Đóng</button>
              <button
                type="button"
                className="button button--brand"
                disabled={payoutMutation.isPending}
                onClick={() => payoutMutation.mutate({ id: payoutTarget.id, ref: payoutRef.trim() })}
              >
                {payoutMutation.isPending ? 'Đang ghi nhận...' : 'Đã chuyển khoản'}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Modal chi tiết đơn */}
      <Modal
        isOpen={Boolean(detailTarget)}
        onClose={() => setDetailTarget(null)}
        title={`Chi tiết đối soát tháng ${detailTarget?.period} - ${detailTarget?.owner?.business_name}`}
        wide
      >
        {activeDetail && (
          <div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 16 }}>
              <div style={{ background: 'var(--surface)', padding: 14, borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}>
                <span style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Thu hộ − hoàn − hoa hồng:</span>
                <div style={{ fontWeight: 700, fontSize: 15, color: 'var(--ink)' }}>
                  {formatMoney(activeDetail.collected_amount)} − {formatMoney(activeDetail.refunded_amount)} − {formatMoney(activeDetail.commission_amount)}
                </div>
              </div>
              <div style={{ background: 'var(--surface)', padding: 14, borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}>
                <span style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Chi trả chủ sân:</span>
                <div style={{ fontWeight: 700, fontSize: 17, color: 'var(--forest)' }}>
                  {formatMoney(activeDetail.payout_amount)}
                </div>
              </div>
              <div style={{ background: 'var(--surface)', padding: 14, borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}>
                <span style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Trạng thái:</span>
                <div style={{ marginTop: 2 }}>{renderStatus(activeDetail)}</div>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 12 }}>
              <h4 style={{ margin: 0, fontSize: 15 }}>Đơn trong kỳ ({activeDetail.bookings?.length ?? activeDetail.booking_count} đơn)</h4>
              <a
                href={`/api/v1/admin/statements/${activeDetail.id}/export-csv`}
                className="button button--ghost button--small"
                download
                style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 6 }}
              >
                <Download size={13} /> Xuất CSV đơn
              </a>
            </div>
            <div className="table-wrap" style={{ maxHeight: 320, overflowY: 'auto' }}>
              <table>
                <thead>
                  <tr>
                    <th>Mã đơn</th>
                    <th>Ngày</th>
                    <th>Khách hàng</th>
                    <th>Khách đã trả</th>
                    <th>Hoàn</th>
                    <th>Phí hoa hồng</th>
                  </tr>
                </thead>
                <tbody>
                  {(activeDetail.bookings ?? []).map((b) => (
                    <tr key={b.id}>
                      <td><strong>#BK-{b.id}</strong></td>
                      <td>{formatDate(b.booking_date)}</td>
                      <td>
                        <strong>{b.customer?.name}</strong>
                        {b.customer?.phone && <small style={{ display: 'block', color: 'var(--muted)' }}>{b.customer.phone}</small>}
                      </td>
                      <td>{formatMoney(b.payment?.amount ?? 0)}</td>
                      <td>{b.refund ? formatMoney(b.refund.amount) : '—'}</td>
                      <td>
                        <strong style={{ color: 'var(--forest)' }}>{formatMoney(b.commission?.amount ?? 0)}</strong>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}
