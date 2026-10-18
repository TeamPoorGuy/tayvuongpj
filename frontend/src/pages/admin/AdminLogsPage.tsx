import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Activity } from 'lucide-react'
import { api } from '../../api/client'
import { QueryState } from '../../components/QueryState'
import { formatDate } from '../../utils/format'
import type { AdminLog } from '../../types/api'

const actionLabels: Record<string, string> = {
  toggle_user: 'Khoá / Mở tài khoản',
  adjust_trust_score: 'Điều chỉnh điểm uy tín',
  verify_owner: 'Duyệt hồ sơ chủ sân',
  verify_field: 'Duyệt cơ sở sân',
  admin_cancel_booking: 'Admin huỷ đơn đặt',
  update_booking_refund: 'Cập nhật hoàn tiền cọc',
  handle_complaint: 'Xử lý khiếu nại',
  update_settings: 'Cập nhật cấu hình sàn',
  confirm_statement: 'Xác nhận thu hoa hồng',
  reject_statement_report: 'Từ chối báo chuyển hoa hồng',
  waive_statement: 'Miễn thu hoa hồng',
  cancel_match: 'Huỷ phòng ghép đấu',
  create_category: 'Tạo danh mục thể thao',
  update_category: 'Cập nhật danh mục thể thao',
  create_field_type: 'Tạo loại sân',
  update_field_type: 'Cập nhật loại sân',
}

export function AdminLogsPage() {
  const [actionFilter, setActionFilter] = useState('')
  const [fromDate, setFromDate] = useState('')
  const [toDate, setToDate] = useState('')

  const { data, isLoading, isError } = useQuery<{ data: AdminLog[] }>({
    queryKey: ['admin', 'logs', actionFilter, fromDate, toDate],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (actionFilter) params.set('action', actionFilter)
      if (fromDate) params.set('from_date', fromDate)
      if (toDate) params.set('to_date', toDate)
      return (await api.get<{ data: AdminLog[] }>(`/admin/logs?${params.toString()}`)).data
    },
  })

  const logs = data?.data ?? []

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      <div>
        <h1 style={{ fontSize: 24, margin: '0 0 4px', color: 'var(--forest)' }}>Nhật ký hoạt động quản trị</h1>
        <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
          Theo dõi minh bạch toàn bộ các hành động can thiệp, duyệt hồ sơ, xử lý đơn và điều chỉnh cấu hình của Admin.
        </p>
      </div>

      {/* Bộ lọc */}
      <div className="filter-bar" style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <select value={actionFilter} onChange={(e) => setActionFilter(e.target.value)} style={{ minWidth: 220 }}>
          <option value="">Tất cả hành động</option>
          {Object.entries(actionLabels).map(([k, label]) => (
            <option key={k} value={k}>
              {label}
            </option>
          ))}
        </select>

        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
          <span style={{ color: 'var(--muted)' }}>Từ ngày:</span>
          <input
            type="date"
            value={fromDate}
            onChange={(e) => setFromDate(e.target.value)}
            style={{ padding: '4px 8px', borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}
          />
          <span style={{ color: 'var(--muted)' }}>đến:</span>
          <input
            type="date"
            value={toDate}
            onChange={(e) => setToDate(e.target.value)}
            style={{ padding: '4px 8px', borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}
          />
        </div>
      </div>

      {/* Danh sách nhật ký */}
      <QueryState
        isPending={isLoading}
        isError={isError}
        isEmpty={logs.length === 0}
        error="Không thể tải nhật ký hoạt động."
        empty={
          <div className="empty-panel" style={{ padding: 40, textAlign: 'center' }}>
            <Activity size={36} style={{ color: 'var(--muted)', marginBottom: 8 }} />
            <h3 style={{ margin: 0, fontSize: 16, color: 'var(--forest)' }}>Không có bản ghi nhật ký nào</h3>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)' }}>Chưa có hành vi quản trị nào khớp với bộ lọc.</p>
          </div>
        }
      >
        <div className="content-card" style={{ padding: 0, overflow: 'hidden' }}>
          <table className="data-table" style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
            <thead>
              <tr style={{ background: 'var(--surface)', borderBottom: '1px solid var(--line)', textAlign: 'left' }}>
                <th style={{ padding: '12px 16px' }}>Thời gian</th>
                <th style={{ padding: '12px 16px' }}>Quản trị viên</th>
                <th style={{ padding: '12px 16px' }}>Hành động</th>
                <th style={{ padding: '12px 16px' }}>Đối tượng</th>
                <th style={{ padding: '12px 16px' }}>Chi tiết / Lý do</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id} style={{ borderBottom: '1px solid var(--line)' }}>
                  <td style={{ padding: '12px 16px', color: 'var(--muted)', whiteSpace: 'nowrap' }}>
                    {formatDate(log.created_at)}
                  </td>
                  <td style={{ padding: '12px 16px', fontWeight: 600, color: 'var(--forest)' }}>
                    {log.admin?.name ?? `Admin #${log.admin_id}`}
                  </td>
                  <td style={{ padding: '12px 16px' }}>
                    <span className="badge badge--brand" style={{ fontSize: 11 }}>
                      {actionLabels[log.action] ?? log.action}
                    </span>
                  </td>
                  <td style={{ padding: '12px 16px', color: 'var(--soft)' }}>
                    {log.subject_type ? (
                      <span>
                        {log.subject_type.split('\\').pop()} #{log.subject_id}
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td style={{ padding: '12px 16px', color: 'var(--soft)' }}>
                    {log.meta ? (
                      <span style={{ fontSize: 12 }}>
                        {log.meta.reason ? (
                          <span>
                            <strong>Lý do:</strong> {String(log.meta.reason)}
                          </span>
                        ) : log.meta.status ? (
                          <span>
                            Trạng thái: <strong>{String(log.meta.status)}</strong>
                          </span>
                        ) : (
                          JSON.stringify(log.meta)
                        )}
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </QueryState>
    </div>
  )
}
