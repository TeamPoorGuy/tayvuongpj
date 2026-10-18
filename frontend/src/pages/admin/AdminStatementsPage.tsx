import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Coins,
  Eye,
  Plus,
  RotateCcw,
  XCircle,
} from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import type {
  AdminDashboard,
  ApiEnvelope,
  OwnerStatement,
  Paginated,
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
  const [confirmTarget, setConfirmTarget] = useState<OwnerStatement | null>(null)
  const [waiveTarget, setWaiveTarget] = useState<OwnerStatement | null>(null)
  const [waiveReason, setWaiveReason] = useState('')
  const [rejectTarget, setRejectTarget] = useState<OwnerStatement | null>(null)
  const [rejectReason, setRejectReason] = useState('')
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
      return (await api.get<Paginated<OwnerStatement>>(`/admin/statements?${params.toString()}`)).data
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

  // 5. Xác nhận thu phí
  const confirmMutation = useMutation({
    mutationFn: async (id: number) => {
      return (await api.post<ApiEnvelope<OwnerStatement>>(`/admin/statements/${id}/confirm`)).data
    },
    onSuccess: (res) => {
      toast.success(res.message || 'Xác nhận thu phí thành công!')
      qc.invalidateQueries({ queryKey: ['admin', 'statements'] })
      qc.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      setConfirmTarget(null)
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  // 6. Miễn thu phí
  const waiveMutation = useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => {
      return (await api.post<ApiEnvelope<OwnerStatement>>(`/admin/statements/${id}/waive`, { reason })).data
    },
    onSuccess: (res) => {
      toast.success(res.message || 'Đã miễn thu phí đối soát!')
      qc.invalidateQueries({ queryKey: ['admin', 'statements'] })
      qc.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      setWaiveTarget(null)
      setWaiveReason('')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  // 7. Từ chối báo cáo thanh toán (chưa nhận được tiền)
  const rejectMutation = useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => {
      return (await api.post<ApiEnvelope<OwnerStatement>>(`/admin/statements/${id}/reject-report`, { reason })).data
    },
    onSuccess: (res) => {
      toast.success(res.message || 'Đã từ chối báo cáo chuyển khoản!')
      qc.invalidateQueries({ queryKey: ['admin', 'statements'] })
      qc.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      setRejectTarget(null)
      setRejectReason('')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const stats = dashboardQuery.data
  const activeDetail = detailQuery.data || detailTarget
  const list = statementsQuery.data?.data ?? []

  const renderStatus = (st: OwnerStatement) => {
    if (st.status === 'paid') {
      return <span className="status-pill status-pill--confirmed"><CheckCircle2 size={13} /> Đã thu</span>
    }
    if (st.status === 'reported') {
      return <span className="status-pill status-pill--pending" style={{ background: '#fef3c7', color: '#92400e' }}><Clock size={13} /> Khách báo chuyển</span>
    }
    if (st.status === 'waived') {
      return <span className="status-pill status-pill--free"><RotateCcw size={13} /> Miễn thu</span>
    }
    if (st.is_overdue) {
      return <span className="status-pill status-pill--cancelled"><AlertTriangle size={13} /> Nợ quá hạn</span>
    }
    return <span className="status-pill status-pill--pending"><Clock size={13} /> Chờ nộp</span>
  }

  return (
    <>
      <PageTitle
        icon={<Coins />}
        kicker="Quản trị tài chính"
        title="Đối soát tháng & Hoa hồng đại lý"
        copy="Quản lý việc thu phí hoa hồng nền tảng từ các cơ sở sân theo chu kỳ tháng (mô hình Agoda)."
        action={
          <button
            type="button"
            className="button button--primary"
            onClick={() => setGenerateModalOpen(true)}
          >
            <Plus size={16} /> Sinh kỳ đối soát
          </button>
        }
      />

      {/* Thẻ chỉ số hoa hồng */}
      <div className="stats-grid" style={{ marginBottom: 20 }}>
        <div className="stat-card">
          <span className="stat-card__label">Tổng giá trị đơn toàn sàn (GMV)</span>
          <strong className="stat-card__val">{formatMoney(stats?.total_gmv ?? stats?.total_revenue ?? 0)}</strong>
          <small className="stat-card__note">Tổng doanh thu các đơn đã xác nhận / xong</small>
        </div>
        <div className="stat-card stat-card--highlight">
          <span className="stat-card__label">Hoa hồng nền tảng đã thu</span>
          <strong className="stat-card__val" style={{ color: 'var(--color-success)' }}>
            {formatMoney(stats?.total_commission_collected ?? 0)}
          </strong>
          <small className="stat-card__note">Thực thu từ các kỳ đối soát đã duyệt</small>
        </div>
        <div className="stat-card">
          <span className="stat-card__label">Hoa hồng đang chờ thu</span>
          <strong className="stat-card__val" style={{ color: 'var(--brand-primary)' }}>
            {formatMoney(stats?.total_commission_pending ?? 0)}
          </strong>
          <small className="stat-card__note">Các kỳ chưa nộp hoặc đang chờ xác nhận</small>
        </div>
      </div>

      {/* Bộ lọc */}
      <section className="data-panel">
        <div className="filters-bar" style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 16 }}>
          <div style={{ flex: '1 1 200px', position: 'relative' }}>
            <input
              type="text"
              className="input"
              placeholder="Tìm theo mã CK, tên cơ sở, chủ sân..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <div style={{ width: 160 }}>
            <input
              type="text"
              className="input"
              placeholder="Kỳ YYYY-MM"
              value={periodFilter}
              onChange={(e) => setPeriodFilter(e.target.value)}
            />
          </div>
          <div style={{ width: 160 }}>
            <select
              className="input"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="">Tất cả trạng thái</option>
              <option value="unpaid">Chờ nộp</option>
              <option value="reported">Đã báo chuyển</option>
              <option value="paid">Đã thanh toán</option>
              <option value="waived">Miễn thu</option>
            </select>
          </div>
        </div>

        <QueryState
          isPending={statementsQuery.isPending}
          isError={statementsQuery.isError}
          isEmpty={list.length === 0}
          empty={<Empty text="Không tìm thấy bảng đối soát nào. Thử thay đổi bộ lọc hoặc bấm 'Sinh kỳ đối soát' để hệ thống tổng hợp." />}
        >
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Cơ sở / Chủ sân</th>
                  <th>Kỳ</th>
                  <th>Số đơn</th>
                  <th>GMV</th>
                  <th>Hoa hồng</th>
                  <th>Mã CK / Hạn nộp</th>
                  <th>Trạng thái</th>
                  <th>Ghi chú</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {list.map((st) => (
                  <tr key={st.id}>
                    <td>
                      <strong>{st.owner?.business_name || '—'}</strong>
                      <div className="text-muted" style={{ fontSize: 12 }}>
                        {st.owner?.user_name} ({st.owner?.user_phone || st.owner?.user_email})
                      </div>
                    </td>
                    <td>
                      <strong>Tháng {st.period}</strong>
                    </td>
                    <td>{st.booking_count}</td>
                    <td>{formatMoney(st.gmv)}</td>
                    <td>
                      <strong style={{ color: 'var(--brand-primary)' }}>{formatMoney(st.commission_amount)}</strong>
                    </td>
                    <td>
                      <code style={{ fontSize: 12, background: 'var(--bg-card-secondary)', padding: '2px 4px', borderRadius: 4 }}>
                        {st.transfer_code}
                      </code>
                      <div style={{ fontSize: 12, color: st.is_overdue ? 'var(--color-danger)' : 'inherit', marginTop: 2 }}>
                        Hạn: {st.due_at ? formatDate(st.due_at) : '—'}
                      </div>
                    </td>
                    <td>{renderStatus(st)}</td>
                    <td style={{ maxWidth: 160, fontSize: 12 }}>
                      {st.note ? <span title={st.note}>{st.note}</span> : '—'}
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: 6 }}>
                        <button
                          type="button"
                          className="button button--tiny button--ghost"
                          onClick={() => setDetailTarget(st)}
                          title="Xem danh sách đơn"
                        >
                          <Eye size={13} /> Chi tiết
                        </button>
                        {['unpaid', 'reported'].includes(st.status) && (
                          <button
                            type="button"
                            className="button button--tiny button--primary"
                            onClick={() => setConfirmTarget(st)}
                            title="Xác nhận đã nhận tiền"
                          >
                            <CheckCircle2 size={13} /> Duyệt thu
                          </button>
                        )}
                        {st.status === 'reported' && (
                          <button
                            type="button"
                            className="button button--tiny button--danger"
                            onClick={() => {
                              setRejectTarget(st)
                              setRejectReason('')
                            }}
                            title="Chưa nhận được tiền (trả về chờ nộp)"
                          >
                            <XCircle size={13} /> Chưa nhận
                          </button>
                        )}
                        {st.status !== 'paid' && st.status !== 'waived' && (
                          <button
                            type="button"
                            className="button button--tiny button--ghost"
                            onClick={() => {
                              setWaiveTarget(st)
                              setWaiveReason('')
                            }}
                            title="Miễn thu phí"
                          >
                            <RotateCcw size={13} /> Miễn thu
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
        </QueryState>
      </section>

      {/* Modal sinh kỳ đối soát */}
      <Modal
        isOpen={generateModalOpen}
        onClose={() => setGenerateModalOpen(false)}
        title="Sinh bảng đối soát hoa hồng hàng tháng"
      >
        <div style={{ display: 'grid', gap: 16 }}>
          <p style={{ fontSize: 14 }}>
            Hệ thống sẽ quét tất cả các đơn đặt sân đã hoàn thành hoặc huỷ có phạt cọc trong tháng được chọn,
            tính toán phí hoa hồng tương ứng theo tỷ lệ cấu hình và tạo bảng đối soát cho toàn bộ chủ sân được duyệt.
          </p>
          <div className="form-group">
            <label htmlFor="genPeriod">Kỳ đối soát (Định dạng YYYY-MM):</label>
            <input
              id="genPeriod"
              type="text"
              className="input"
              placeholder="Ví dụ: 2026-09"
              value={genPeriod}
              onChange={(e) => setGenPeriod(e.target.value)}
            />
          </div>
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
              className="button button--primary"
              disabled={generateMutation.isPending || !/^\d{4}-\d{2}$/.test(genPeriod)}
              onClick={() => generateMutation.mutate(genPeriod)}
            >
              {generateMutation.isPending ? 'Đang tổng hợp...' : 'Bắt đầu sinh đối soát'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal xác nhận duyệt thu tiền */}
      <ConfirmModal
        isOpen={Boolean(confirmTarget)}
        title="Xác nhận đã thu phí hoa hồng?"
        message={`Bạn có chắc chắn đã nhận đủ số tiền ${formatMoney(confirmTarget?.commission_amount ?? 0)} từ chủ sân "${confirmTarget?.owner?.business_name}" cho kỳ đối soát tháng ${confirmTarget?.period}?`}
        confirmText="Xác nhận đã nhận"
        danger={false}
        busy={confirmMutation.isPending}
        onConfirm={() => confirmTarget && confirmMutation.mutate(confirmTarget.id)}
        onClose={() => setConfirmTarget(null)}
      />

      {/* Modal miễn thu phí */}
      <Modal
        isOpen={Boolean(waiveTarget)}
        onClose={() => setWaiveTarget(null)}
        title={`Miễn thu phí đối soát tháng ${waiveTarget?.period}`}
      >
        <div style={{ display: 'grid', gap: 16 }}>
          <p style={{ fontSize: 14 }}>
            Bạn đang thực hiện miễn thu phí hoa hồng <strong>{formatMoney(waiveTarget?.commission_amount ?? 0)}</strong> cho cơ sở <strong>{waiveTarget?.owner?.business_name}</strong>.
          </p>
          <div className="form-group">
            <label htmlFor="waiveReason">Lý do miễn thu (bắt buộc):</label>
            <textarea
              id="waiveReason"
              className="input"
              rows={3}
              placeholder="Ví dụ: Chương trình khuyến mãi khai trương / bồi thường sự cố..."
              value={waiveReason}
              onChange={(e) => setWaiveReason(e.target.value)}
            />
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button
              type="button"
              className="button button--ghost"
              onClick={() => setWaiveTarget(null)}
            >
              Đóng
            </button>
            <button
              type="button"
              className="button button--danger"
              disabled={waiveMutation.isPending || !waiveReason.trim()}
              onClick={() => waiveTarget && waiveMutation.mutate({ id: waiveTarget.id, reason: waiveReason })}
            >
              {waiveMutation.isPending ? 'Đang xử lý...' : 'Xác nhận miễn thu'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal từ chối báo cáo thanh toán (chưa nhận được tiền) */}
      <Modal
        isOpen={Boolean(rejectTarget)}
        onClose={() => setRejectTarget(null)}
        title={`Từ chối báo cáo chuyển khoản tháng ${rejectTarget?.period}`}
      >
        <div style={{ display: 'grid', gap: 16 }}>
          <p style={{ fontSize: 14 }}>
            Bạn đang xác nhận <strong>chưa nhận được</strong> số tiền <strong>{formatMoney(rejectTarget?.commission_amount ?? 0)}</strong> từ chủ sân <strong>{rejectTarget?.owner?.business_name}</strong>.
            Kỳ đối soát sẽ được chuyển lại về trạng thái <strong>Chờ nộp (unpaid)</strong>.
          </p>
          <div className="form-group">
            <label htmlFor="rejectReason">Lý do từ chối (tuỳ chọn):</label>
            <input
              id="rejectReason"
              type="text"
              className="input"
              placeholder="Ví dụ: Chưa thấy tiền nổi trên sao kê tài khoản ngân hàng..."
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
            />
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button
              type="button"
              className="button button--ghost"
              onClick={() => setRejectTarget(null)}
            >
              Đóng
            </button>
            <button
              type="button"
              className="button button--danger"
              disabled={rejectMutation.isPending}
              onClick={() => rejectTarget && rejectMutation.mutate({ id: rejectTarget.id, reason: rejectReason })}
            >
              {rejectMutation.isPending ? 'Đang xử lý...' : 'Xác nhận chưa nhận tiền'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal chi tiết đơn */}
      <Modal
        isOpen={Boolean(detailTarget)}
        onClose={() => setDetailTarget(null)}
        title={`Chi tiết đối soát tháng ${detailTarget?.period} - ${detailTarget?.owner?.business_name}`}
      >
        {activeDetail && (
          <div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 16 }}>
              <div style={{ background: 'var(--bg-card-secondary)', padding: 12, borderRadius: 8 }}>
                <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>Tổng GMV:</span>
                <div style={{ fontWeight: 'bold', fontSize: 16 }}>{formatMoney(activeDetail.gmv)}</div>
              </div>
              <div style={{ background: 'var(--bg-card-secondary)', padding: 12, borderRadius: 8 }}>
                <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>Phí hoa hồng:</span>
                <div style={{ fontWeight: 'bold', fontSize: 16, color: 'var(--brand-primary)' }}>
                  {formatMoney(activeDetail.commission_amount)}
                </div>
              </div>
              <div style={{ background: 'var(--bg-card-secondary)', padding: 12, borderRadius: 8 }}>
                <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>Trạng thái:</span>
                <div style={{ marginTop: 2 }}>{renderStatus(activeDetail)}</div>
              </div>
            </div>

            <h4>Danh sách đơn đặt sân tính phí ({activeDetail.bookings?.length ?? activeDetail.booking_count} đơn)</h4>
            <div className="table-responsive" style={{ maxHeight: 300, overflowY: 'auto', marginTop: 8 }}>
              <table className="data-table data-table--compact">
                <thead>
                  <tr>
                    <th>Mã đơn</th>
                    <th>Ngày</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Phí hoa hồng</th>
                  </tr>
                </thead>
                <tbody>
                  {(activeDetail.bookings ?? []).map((b) => (
                    <tr key={b.id}>
                      <td>#{b.id}</td>
                      <td>{formatDate(b.booking_date)}</td>
                      <td>{b.customer?.name} ({b.customer?.phone})</td>
                      <td>{formatMoney(b.total_price)}</td>
                      <td>
                        <strong>{formatMoney(b.commission?.amount ?? 0)}</strong>
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
