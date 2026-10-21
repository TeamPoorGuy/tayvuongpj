import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Clock, Download, FileText, Receipt } from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import type { ApiEnvelope, CurrentStatementSummary, OwnerStatement, StatementList } from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'

/** Đối soát thu hộ: SportHub giữ tiền khách trả, cuối kỳ chuyển cho chủ sân sau khi trừ hoàn tiền và hoa hồng. */
export function OwnerStatementsPage() {
  const [page, setPage] = useState(1)
  const [selectedStatement, setSelectedStatement] = useState<OwnerStatement | null>(null)

  const currentSummaryQuery = useQuery({
    queryKey: ['owner', 'statements', 'current-summary'],
    queryFn: async () =>
      (await api.get<ApiEnvelope<CurrentStatementSummary>>('/field-owner/statements/current-summary')).data.data,
  })

  const statementsQuery = useQuery({
    queryKey: ['owner', 'statements', page],
    queryFn: async () => (await api.get<StatementList>(`/field-owner/statements?page=${page}&per_page=10`)).data,
  })

  const detailQuery = useQuery({
    queryKey: ['owner', 'statements', 'detail', selectedStatement?.id],
    queryFn: async () =>
      (await api.get<ApiEnvelope<OwnerStatement>>(`/field-owner/statements/${selectedStatement?.id}`)).data.data,
    enabled: Boolean(selectedStatement?.id),
  })

  const summary = currentSummaryQuery.data
  const activeStatement = detailQuery.data || selectedStatement
  const list = statementsQuery.data?.data ?? []
  const totals = statementsQuery.data?.meta?.totals

  const renderStatus = (st: OwnerStatement) => {
    if (st.status === 'paid') {
      return <span className="status-pill status-pill--confirmed"><CheckCircle2 size={13} /> Đã nhận tiền</span>
    }
    if (st.is_overdue) {
      return <span className="status-pill status-pill--cancelled"><AlertTriangle size={13} /> SportHub chậm chi trả</span>
    }
    return <span className="status-pill status-pill--pending"><Clock size={13} /> Chờ SportHub chuyển</span>
  }

  const row = (label: string, value: string, strong = false) => (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
      <span>{label}</span>
      {strong ? <strong style={{ fontSize: 18, color: 'var(--pitch)' }}>{value}</strong> : <strong>{value}</strong>}
    </div>
  )

  return (
    <>
      <PageTitle
        icon={<Receipt />}
        kicker="Tài chính"
        title="Đối soát & Chi trả"
        copy="Khách thanh toán qua SportHub; cuối mỗi tháng SportHub chuyển cho bạn số tiền đã thu sau khi trừ tiền hoàn cho khách và hoa hồng nền tảng."
      />

      <section className="data-panel" style={{ marginBottom: 24 }}>
        <div className="data-panel__head">
          <div>
            <span>Ước tính tháng này ({summary?.period || 'Đang tải...'})</span>
            <h2>Tiền sẽ được chi trả</h2>
          </div>
          {summary && <span className="badge badge--brand">Hoa hồng nền tảng: {summary.commission_rate}%</span>}
        </div>
        <div className="data-panel__body">
          <div className="stats-grid-3" style={{ marginBottom: 0 }}>
            <div className="stat-card">
              <span className="stat-card__label">SportHub đã thu hộ</span>
              <strong className="stat-card__val">{formatMoney(summary?.collected ?? 0)}</strong>
              <small className="stat-card__note">{summary?.booking_count ?? 0} đơn đã thanh toán qua nền tảng</small>
            </div>
            <div className="stat-card">
              <span className="stat-card__label">Hoàn khách + hoa hồng</span>
              <strong className="stat-card__val">
                {formatMoney((summary?.refunded ?? 0) + (summary?.estimated_commission ?? 0))}
              </strong>
              <small className="stat-card__note">
                Hoàn {formatMoney(summary?.refunded ?? 0)} · hoa hồng {formatMoney(summary?.estimated_commission ?? 0)}
              </small>
            </div>
            <div className="stat-card stat-card--highlight">
              <span className="stat-card__label">Dự kiến bạn nhận</span>
              <strong className="stat-card__val">{formatMoney(summary?.estimated_payout ?? 0)}</strong>
              <small className="stat-card__note">Chi trả sau khi chốt đối soát ngày 01 tháng sau</small>
            </div>
          </div>
        </div>
      </section>

      <div className="stats-grid-3">
        <div className="stat-card">
          <span className="stat-card__label">Chờ SportHub chuyển</span>
          <strong className="stat-card__val" style={{ color: 'var(--warn, #e29a0c)' }}>{formatMoney(totals?.unpaid_amount ?? 0)}</strong>
          <small className="stat-card__note">{totals?.unpaid_count ?? 0} kỳ đã chốt, chưa chi trả</small>
        </div>
        <div className="stat-card stat-card--highlight">
          <span className="stat-card__label">Đã nhận</span>
          <strong className="stat-card__val">{formatMoney(totals?.paid_amount ?? 0)}</strong>
          <small className="stat-card__note">{totals?.paid_count ?? 0} kỳ đã chuyển khoản</small>
        </div>
        <div className="stat-card">
          <span className="stat-card__label">Tổng được chi trả</span>
          <strong className="stat-card__val">{formatMoney((totals?.unpaid_amount ?? 0) + (totals?.paid_amount ?? 0))}</strong>
          <small className="stat-card__note">Về tài khoản ngân hàng khai trong hồ sơ cơ sở</small>
        </div>
      </div>

      <section className="data-panel">
        <div className="data-panel__head">
          <div>
            <span>Lịch sử chi trả</span>
            <h2>Đối soát theo tháng</h2>
          </div>
        </div>

        <QueryState
          isPending={statementsQuery.isPending}
          isError={statementsQuery.isError}
          isEmpty={list.length === 0}
          empty={<Empty text="Bảng đối soát được hệ thống tự lập vào ngày 01 của tháng kế tiếp." />}
        >
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Kỳ</th>
                  <th>Số đơn</th>
                  <th>Đã thu hộ</th>
                  <th>Hoàn khách</th>
                  <th>Hoa hồng</th>
                  <th>Bạn nhận</th>
                  <th>Hạn chi trả</th>
                  <th>Trạng thái</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.map((st) => (
                  <tr key={st.id}>
                    <td><strong>Tháng {st.period}</strong></td>
                    <td>{st.booking_count} đơn</td>
                    <td>{formatMoney(st.collected_amount)}</td>
                    <td>{formatMoney(st.refunded_amount)}</td>
                    <td>{formatMoney(st.commission_amount)}</td>
                    <td><strong style={{ color: 'var(--pitch)' }}>{formatMoney(st.payout_amount)}</strong></td>
                    <td>{st.due_at ? formatDate(st.due_at) : '—'}</td>
                    <td>{renderStatus(st)}</td>
                    <td>
                      <button type="button" className="button button--small button--ghost" onClick={() => setSelectedStatement(st)}>
                        <FileText size={14} /> Chi tiết
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination page={page} lastPage={statementsQuery.data?.meta?.last_page ?? 1} onPageChange={setPage} />
        </QueryState>
      </section>

      <Modal
        isOpen={Boolean(selectedStatement)}
        onClose={() => setSelectedStatement(null)}
        title={`Đối soát tháng ${selectedStatement?.period}`}
      >
        {activeStatement && (
          <div className="statement-modal-content">
            <div style={{ background: 'var(--surface)', padding: 16, borderRadius: 8, marginBottom: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <span>Trạng thái:</span>
                {renderStatus(activeStatement)}
              </div>
              {row('SportHub đã thu hộ:', formatMoney(activeStatement.collected_amount))}
              {row('Hoàn tiền cho khách:', `− ${formatMoney(activeStatement.refunded_amount)}`)}
              {row('Hoa hồng nền tảng:', `− ${formatMoney(activeStatement.commission_amount)}`)}
              {row('Số tiền bạn nhận:', formatMoney(activeStatement.payout_amount), true)}
              {row('Nội dung chuyển khoản:', activeStatement.transfer_code)}
              {activeStatement.status === 'paid'
                ? row('Đã chuyển lúc:', activeStatement.paid_at ? formatDateTime(activeStatement.paid_at) : '—')
                : row('Hạn chi trả:', activeStatement.due_at ? formatDate(activeStatement.due_at) : '—')}
              {activeStatement.payout_ref && row('Mã giao dịch ngân hàng:', activeStatement.payout_ref)}
            </div>

            {activeStatement.status === 'unpaid' && (
              <p className="form-help">
                SportHub sẽ chuyển vào tài khoản {activeStatement.payout_bank?.account_number ?? 'khai trong hồ sơ cơ sở'}
                {activeStatement.payout_bank?.bank_name ? ` (${activeStatement.payout_bank.bank_name})` : ''}. Hãy kiểm tra thông tin ngân hàng ở mục Hồ sơ cơ sở.
              </p>
            )}

            <div style={{ marginTop: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <h4 style={{ margin: 0 }}>Đơn trong kỳ ({activeStatement.bookings?.length ?? activeStatement.booking_count} đơn)</h4>
                <a
                  href={`/api/v1/field-owner/statements/${activeStatement.id}/export-csv`}
                  className="button button--tiny button--secondary"
                  download
                  style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 4 }}
                >
                  <Download size={13} /> Xuất CSV
                </a>
              </div>
              {activeStatement.bookings && activeStatement.bookings.length > 0 ? (
                <div className="table-wrap" style={{ maxHeight: 250 }}>
                  <table style={{ minWidth: 0 }}>
                    <thead>
                      <tr>
                        <th>Mã đơn</th>
                        <th>Ngày chơi</th>
                        <th>Giờ</th>
                        <th>Khách đã trả</th>
                        <th>Hoàn</th>
                        <th>Hoa hồng</th>
                      </tr>
                    </thead>
                    <tbody>
                      {activeStatement.bookings.map((b) => (
                        <tr key={b.id}>
                          <td>#{b.id}</td>
                          <td>{formatDate(b.booking_date)}</td>
                          <td>{b.time_slot?.start_time} - {b.time_slot?.end_time}</td>
                          <td>{formatMoney(b.payment?.amount ?? 0)}</td>
                          <td>{b.refund ? formatMoney(b.refund.amount) : '—'}</td>
                          <td><strong>{formatMoney(b.commission?.amount ?? 0)}</strong></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-muted" style={{ fontSize: 13, marginTop: 4 }}>
                  {detailQuery.isLoading ? 'Đang tải danh sách đơn...' : 'Không có đơn nào trong kỳ.'}
                </p>
              )}
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}
