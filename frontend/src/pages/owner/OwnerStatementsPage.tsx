import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Copy,
  CreditCard,
  FileText,
  Info,
  Receipt,
  RotateCcw,
} from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import type {
  ApiEnvelope,
  CurrentStatementSummary,
  OwnerStatement,
  Paginated,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { formatDate, formatMoney } from '../../utils/format'

export function OwnerStatementsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [selectedStatement, setSelectedStatement] = useState<OwnerStatement | null>(null)
  const [reportNote, setReportNote] = useState('')

  // 1. Tóm tắt tháng hiện tại
  const currentSummaryQuery = useQuery({
    queryKey: ['owner', 'statements', 'current-summary'],
    queryFn: async () =>
      (await api.get<ApiEnvelope<CurrentStatementSummary>>('/field-owner/statements/current-summary')).data.data,
  })

  // 2. Danh sách các kỳ đối soát
  const statementsQuery = useQuery({
    queryKey: ['owner', 'statements', page],
    queryFn: async () =>
      (await api.get<Paginated<OwnerStatement>>(`/field-owner/statements?page=${page}&per_page=10`)).data,
  })

  // 3. Chi tiết kỳ đối soát đang chọn (kèm danh sách bookings)
  const detailQuery = useQuery({
    queryKey: ['owner', 'statements', 'detail', selectedStatement?.id],
    queryFn: async () =>
      (await api.get<ApiEnvelope<OwnerStatement>>(`/field-owner/statements/${selectedStatement?.id}`)).data.data,
    enabled: Boolean(selectedStatement?.id),
  })

  // 4. Mutation báo đã chuyển khoản
  const reportMutation = useMutation({
    mutationFn: async ({ id, note }: { id: number; note: string }) => {
      return (await api.post<ApiEnvelope<OwnerStatement>>(`/field-owner/statements/${id}/report`, { note })).data
    },
    onSuccess: (res) => {
      toast.success(res.message || 'Đã gửi thông báo chuyển khoản thành công!')
      qc.invalidateQueries({ queryKey: ['owner', 'statements'] })
      setSelectedStatement(null)
      setReportNote('')
    },
    onError: (err) => {
      toast.error(errorMessage(err))
    },
  })

  const summary = currentSummaryQuery.data
  const activeStatement = detailQuery.data || selectedStatement
  const list = statementsQuery.data?.data ?? []

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text)
    toast.success(`Đã sao chép ${label}`)
  }

  const renderStatus = (st: OwnerStatement) => {
    if (st.status === 'paid') {
      return <span className="status-pill status-pill--confirmed"><CheckCircle2 size={13} /> Đã thanh toán</span>
    }
    if (st.status === 'reported') {
      return <span className="status-pill status-pill--pending"><Clock size={13} /> Đã báo chuyển, chờ duyệt</span>
    }
    if (st.status === 'waived') {
      return <span className="status-pill status-pill--free"><RotateCcw size={13} /> Miễn thu</span>
    }
    if (st.is_overdue) {
      return <span className="status-pill status-pill--cancelled"><AlertTriangle size={13} /> Quá hạn thanh toán</span>
    }
    return <span className="status-pill status-pill--pending"><Clock size={13} /> Chờ thanh toán</span>
  }

  return (
    <>
      <PageTitle
        icon={<Receipt />}
        kicker="Tài chính & Hoa hồng"
        title="Đối soát & Phí nền tảng"
        copy="Theo dõi doanh thu, mức phí hoa hồng nền tảng theo mô hình đại lý (Agoda) và lịch sử thanh toán đối soát hàng tháng."
      />

      {/* Cảnh báo nếu nợ quá hạn */}
      {summary?.has_overdue_debt && (
        <div className="alert-banner alert-banner--danger" style={{ marginBottom: 20 }}>
          <AlertTriangle size={20} />
          <div>
            <strong>Tài khoản đang có kỳ đối soát nợ phí quá hạn!</strong>
            <p>Hệ thống tạm khóa tính năng duyệt đơn mới. Vui lòng thanh toán phí đối soát để tiếp tục nhận và duyệt đơn đặt sân.</p>
          </div>
        </div>
      )}

      {/* Thẻ tóm tắt tháng hiện tại */}
      <section className="data-panel" style={{ marginBottom: 24 }}>
        <div className="data-panel__head">
          <div>
            <span>Ước tính tháng này ({summary?.period || 'Đang tải...'})</span>
            <h2>Phí hoa hồng tạm tính</h2>
          </div>
          {summary && (
            <span className="badge badge--brand">Tỉ lệ hoa hồng: {summary.commission_rate}%</span>
          )}
        </div>
        <div className="stats-grid" style={{ marginTop: 12 }}>
          <div className="stat-card">
            <span className="stat-card__label">Tổng giá trị đơn (GMV)</span>
            <strong className="stat-card__val">{formatMoney(summary?.estimated_gmv ?? 0)}</strong>
            <small className="stat-card__note">Từ các đơn hoàn thành & giữ cọc</small>
          </div>
          <div className="stat-card">
            <span className="stat-card__label">Số đơn tính phí</span>
            <strong className="stat-card__val">{summary?.booking_count ?? 0} đơn</strong>
            <small className="stat-card__note">Đã check-in / No-show</small>
          </div>
          <div className="stat-card stat-card--highlight">
            <span className="stat-card__label">Phí nền tảng ước tính</span>
            <strong className="stat-card__val" style={{ color: 'var(--brand-primary)' }}>
              {formatMoney(summary?.estimated_commission ?? 0)}
            </strong>
            <small className="stat-card__note">Sẽ đối soát vào ngày 01 tháng sau</small>
          </div>
        </div>
      </section>

      {/* Bảng danh sách các kỳ đối soát */}
      <section className="data-panel">
        <div className="data-panel__head">
          <div>
            <span>Lịch sử thanh toán</span>
            <h2>Bảng đối soát hoa hồng qua các tháng</h2>
          </div>
        </div>

        <QueryState
          isPending={statementsQuery.isPending}
          isError={statementsQuery.isError}
          isEmpty={list.length === 0}
          empty={<Empty text="Bảng đối soát hàng tháng sẽ tự động được hệ thống tổng hợp vào ngày 01 của tháng kế tiếp." />}
        >
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Kỳ đối soát</th>
                  <th>Số đơn</th>
                  <th>Tổng giá trị (GMV)</th>
                  <th>Phí hoa hồng</th>
                  <th>Hạn thanh toán</th>
                  <th>Mã chuyển khoản</th>
                  <th>Trạng thái</th>
                  <th>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                {list.map((st) => (
                  <tr key={st.id}>
                    <td>
                      <strong>Tháng {st.period}</strong>
                    </td>
                    <td>{st.booking_count} đơn</td>
                    <td>{formatMoney(st.gmv)}</td>
                    <td>
                      <strong style={{ color: 'var(--brand-primary)' }}>{formatMoney(st.commission_amount)}</strong>
                    </td>
                    <td>
                      {st.due_at ? (
                        <span style={{ color: st.is_overdue ? 'var(--color-danger)' : 'inherit' }}>
                          {formatDate(st.due_at)}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>
                      <code style={{ fontSize: 13, background: 'var(--bg-card-secondary)', padding: '2px 6px', borderRadius: 4 }}>
                        {st.transfer_code}
                      </code>
                    </td>
                    <td>{renderStatus(st)}</td>
                    <td>
                      <button
                        type="button"
                        className="button button--small button--ghost"
                        onClick={() => {
                          setSelectedStatement(st)
                          setReportNote(st.note || '')
                        }}
                      >
                        <FileText size={14} /> Chi tiết & Nộp
                      </button>
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

      {/* Modal chi tiết & nộp phí đối soát */}
      <Modal
        isOpen={Boolean(selectedStatement)}
        onClose={() => setSelectedStatement(null)}
        title={`Đối soát hoa hồng tháng ${selectedStatement?.period}`}
      >
        {activeStatement && (
          <div className="statement-modal-content">
            <div className="statement-summary-box" style={{ background: 'var(--bg-card-secondary)', padding: 16, borderRadius: 8, marginBottom: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <span>Trạng thái:</span>
                {renderStatus(activeStatement)}
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <span>Số tiền hoa hồng cần nộp:</span>
                <strong style={{ fontSize: 18, color: 'var(--brand-primary)' }}>
                  {formatMoney(activeStatement.commission_amount)}
                </strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <span>Tổng giá trị đơn (GMV):</span>
                <strong>{formatMoney(activeStatement.gmv)}</strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span>Hạn chót thanh toán:</span>
                <strong style={{ color: activeStatement.is_overdue ? 'var(--color-danger)' : 'inherit' }}>
                  {activeStatement.due_at ? formatDate(activeStatement.due_at) : '—'}
                </strong>
              </div>
            </div>

            {/* Thông tin chuyển khoản SportHub */}
            {activeStatement.status === 'unpaid' && (
              <div className="bank-info-panel" style={{ border: '1px solid var(--border-color)', borderRadius: 8, padding: 16, marginBottom: 16 }}>
                <h4 style={{ display: 'flex', alignItems: 'center', gap: 6, margin: '0 0 12px 0' }}>
                  <CreditCard size={16} /> Thông tin chuyển khoản SportHub
                </h4>
                <div className="bank-info-grid" style={{ display: 'grid', gap: 8, fontSize: 14 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span className="text-muted">Ngân hàng:</span>
                    <strong>{activeStatement.platform_bank?.bank_name}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span className="text-muted">Số tài khoản:</span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                      <strong>{activeStatement.platform_bank?.account_number}</strong>
                      <button
                        type="button"
                        className="button button--tiny button--ghost"
                        onClick={() => copyToClipboard(activeStatement.platform_bank?.account_number || '', 'Số tài khoản')}
                        title="Sao chép"
                      >
                        <Copy size={12} />
                      </button>
                    </span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span className="text-muted">Chủ tài khoản:</span>
                    <strong>{activeStatement.platform_bank?.account_holder}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span className="text-muted">Nội dung chuyển:</span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                      <code style={{ background: 'var(--brand-primary-soft)', color: 'var(--brand-primary)', padding: '2px 8px', borderRadius: 4, fontWeight: 'bold' }}>
                        {activeStatement.transfer_code}
                      </code>
                      <button
                        type="button"
                        className="button button--tiny button--ghost"
                        onClick={() => copyToClipboard(activeStatement.transfer_code, 'Mã chuyển khoản')}
                        title="Sao chép"
                      >
                        <Copy size={12} />
                      </button>
                    </span>
                  </div>
                </div>

                <div className="form-group" style={{ marginTop: 16 }}>
                  <label htmlFor="reportNote">Ghi chú xác nhận (tuỳ chọn):</label>
                  <input
                    id="reportNote"
                    type="text"
                    className="input"
                    placeholder="Ví dụ: Đã chuyển từ tài khoản VCB lúc 09:30"
                    value={reportNote}
                    onChange={(e) => setReportNote(e.target.value)}
                  />
                </div>

                <button
                  type="button"
                  className="button button--primary"
                  style={{ width: '100%', marginTop: 12 }}
                  disabled={reportMutation.isPending}
                  onClick={() => reportMutation.mutate({ id: activeStatement.id, note: reportNote })}
                >
                  {reportMutation.isPending ? 'Đang gửi...' : 'Tôi đã chuyển khoản'}
                </button>
              </div>
            )}

            {activeStatement.status === 'reported' && (
              <div className="alert-banner alert-banner--warning" style={{ marginBottom: 16 }}>
                <Clock size={16} />
                <div>
                  <strong>Bạn đã gửi xác nhận chuyển khoản</strong>
                  <p>Ghi chú: {activeStatement.note || 'Không có ghi chú'}</p>
                  <small>Ban quản trị SportHub đang đối chiếu sao kê và sẽ duyệt ngay khi nhận được tiền.</small>
                </div>
              </div>
            )}

            {activeStatement.status === 'paid' && (
              <div className="alert-banner alert-banner--success" style={{ marginBottom: 16 }}>
                <CheckCircle2 size={16} />
                <div>
                  <strong>Kỳ đối soát đã hoàn tất thanh toán</strong>
                  <p>Cảm ơn bạn đã hợp tác cùng SportHub!</p>
                </div>
              </div>
            )}

            {activeStatement.status === 'waived' && (
              <div className="alert-banner alert-banner--info" style={{ marginBottom: 16 }}>
                <Info size={16} />
                <div>
                  <strong>Kỳ đối soát này được nền tảng miễn thu phí</strong>
                  <p>Lý do: {activeStatement.note || 'Miễn phí theo chính sách'}</p>
                </div>
              </div>
            )}

            {/* Danh sách các đơn trong kỳ */}
            <div className="statement-bookings-list" style={{ marginTop: 16 }}>
              <h4>Danh sách đơn đặt sân tính phí trong kỳ ({activeStatement.bookings?.length ?? activeStatement.booking_count} đơn)</h4>
              {activeStatement.bookings && activeStatement.bookings.length > 0 ? (
                <div className="table-responsive" style={{ maxHeight: 250, overflowY: 'auto', marginTop: 8 }}>
                  <table className="data-table data-table--compact">
                    <thead>
                      <tr>
                        <th>Mã đơn</th>
                        <th>Ngày chơi</th>
                        <th>Khung giờ</th>
                        <th>Tổng tiền</th>
                        <th>Hoa hồng</th>
                      </tr>
                    </thead>
                    <tbody>
                      {activeStatement.bookings.map((b) => (
                        <tr key={b.id}>
                          <td>#{b.id}</td>
                          <td>{formatDate(b.booking_date)}</td>
                          <td>{b.time_slot?.start_time} - {b.time_slot?.end_time}</td>
                          <td>{formatMoney(b.total_price)}</td>
                          <td>
                            <strong>{formatMoney(b.commission?.amount ?? 0)}</strong>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-muted" style={{ fontSize: 13, marginTop: 4 }}>
                  {detailQuery.isLoading ? 'Đang tải danh sách đơn...' : 'Bấm xem chi tiết để tải danh sách các đơn.'}
                </p>
              )}
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}
