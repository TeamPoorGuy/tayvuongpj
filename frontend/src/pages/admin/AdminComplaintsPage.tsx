import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { MessageSquare, Search } from 'lucide-react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { EvidenceGallery } from '../../components/EvidencePicker'
import { Modal } from '../../components/Modal'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import { digitsOnly } from '../../utils/input'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'
import type { Complaint } from '../../types/api'
import { PageTitle } from '../customer/CustomerPages'

const categoryLabels: Record<string, string> = {
  refund_pending: 'Chưa nhận được tiền hoàn cọc',
  field_mismatch: 'Sân không đúng thực tế/mô tả',
  owner_cancelled: 'Chủ sân tự ý huỷ/không nhận',
  bad_behavior: 'Thái độ phục vụ/hành vi xấu',
  wrong_no_show: 'Đã đến sân nhưng bị ghi vắng mặt',
  property_damage: 'Khách làm hư hỏng/mất tài sản',
  customer_misconduct: 'Khách gây rối/thái độ không phù hợp',
  overtime: 'Khách chơi quá giờ',
  other: 'Vấn đề khác',
}

const statusBadge = (status: Complaint['status']) => {
  switch (status) {
    case 'open':
      return <span className="status status--pending"><i />Chờ xử lý</span>
    case 'processing':
      return <span className="status status--awaiting"><i />Đang xác minh</span>
    case 'resolved':
      return <span className="status status--approved"><i />Đã giải quyết</span>
    case 'rejected':
      return <span className="status status--rejected"><i />Đã từ chối</span>
  }
}

export function AdminComplaintsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [statusFilter, setStatusFilter] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')
  const [keyword, setKeyword] = useState('')
  const [activeComplaint, setActiveComplaint] = useState<Complaint | null>(null)

  // Handle modal state
  const [handleStatus, setHandleStatus] = useState<'processing' | 'resolved' | 'rejected'>('resolved')
  const [resolution, setResolution] = useState('')
  const [refundInput, setRefundInput] = useState('')
  const [confirmAttendance, setConfirmAttendance] = useState(false)
  const [penaltyInput, setPenaltyInput] = useState('')

  const { data, isLoading, isError } = useQuery<{ data: Complaint[] }>({
    queryKey: ['admin', 'complaints', statusFilter, categoryFilter, keyword],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (statusFilter) params.set('status', statusFilter)
      if (categoryFilter) params.set('category', categoryFilter)
      if (keyword) params.set('keyword', keyword)
      return (await api.get<{ data: Complaint[] }>(`/admin/complaints?${params.toString()}`)).data
    },
  })

  const handleMutation = useMutation({
    mutationFn: async (payload: { id: number; status: string; resolution: string; refund_amount?: number; confirm_attendance?: boolean; trust_penalty?: number }) => {
      const { id, ...body } = payload
      return (await api.patch(`/admin/complaints/${id}/handle`, body)).data
    },
    onSuccess: () => {
      toast.success('Đã cập nhật trạng thái khiếu nại và gửi thông báo tới người dùng.')
      setActiveComplaint(null)
      setResolution('')
      setRefundInput('')
      setConfirmAttendance(false)
      setPenaltyInput('')
      void client.invalidateQueries({ queryKey: ['admin', 'complaints'] })
      void client.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xử lý khiếu nại.')
    },
  })

  const openHandleModal = (c: Complaint) => {
    setActiveComplaint(c)
    setHandleStatus(c.status === 'open' ? 'resolved' : (c.status as 'processing' | 'resolved' | 'rejected'))
    setResolution(c.resolution ?? '')
    setRefundInput('')
    setConfirmAttendance(false)
    setPenaltyInput('')
  }

  const submitHandle = (e: React.FormEvent) => {
    e.preventDefault()
    if (!activeComplaint) return
    const refund = Number(refundInput)
    const accepted = handleStatus === 'resolved'
    handleMutation.mutate({
      id: activeComplaint.id,
      status: handleStatus,
      resolution,
      refund_amount: accepted && refund > 0 ? refund : undefined,
      confirm_attendance: accepted && confirmAttendance ? true : undefined,
      trust_penalty: accepted && Number(penaltyInput) > 0 ? Number(penaltyInput) : undefined,
    })
  }

  const complaints = data?.data ?? []

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* Header */}
      <PageTitle
        icon={<MessageSquare />}
        kicker="Quản trị dịch vụ"
        title="Khiếu nại & Tranh chấp"
        copy="Tiếp nhận và xử lý các phản ánh, khiếu nại về hoàn cọc, chất lượng sân bãi và dịch vụ."
      />

      {/* Filters Toolbar */}
      <div className="toolbar">
        <label className="search-control">
          <Search size={16} />
          <input
            type="search"
            placeholder="Tìm theo nội dung, tên hoặc email..."
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
          />
        </label>

        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
          <option value="">Tất cả trạng thái</option>
          <option value="open">Chờ xử lý</option>
          <option value="processing">Đang xác minh</option>
          <option value="resolved">Đã giải quyết</option>
          <option value="rejected">Đã từ chối</option>
        </select>

        <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}>
          <option value="">Tất cả phân loại</option>
          {Object.entries(categoryLabels).map(([k, label]) => (
            <option key={k} value={k}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {/* Content */}
      <QueryState
        isPending={isLoading}
        isError={isError}
        isEmpty={complaints.length === 0}
        error="Không thể tải danh sách khiếu nại."
        empty={
          <div className="empty-panel" style={{ padding: 40, textAlign: 'center' }}>
            <MessageSquare size={36} style={{ color: 'var(--muted)', marginBottom: 8 }} />
            <h3 style={{ margin: 0, fontSize: 16, color: 'var(--forest)' }}>Không có khiếu nại nào</h3>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)' }}>Hiện không có phản ánh nào theo bộ lọc đã chọn.</p>
          </div>
        }
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {complaints.map((c) => (
            <div
              key={c.id}
              className="content-card"
              style={{
                padding: '20px 22px',
                borderLeft: c.status === 'open' ? '4px solid var(--warn)' : c.status === 'processing' ? '4px solid #38bdf8' : '4px solid var(--line)',
                display: 'flex',
                flexDirection: 'column',
                gap: 14,
              }}
            >
              {/* Header */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                  <code style={{ fontSize: 13, fontWeight: 700, background: 'var(--surface)', padding: '3px 8px', borderRadius: 'var(--r-sm)' }}>
                    #KN-{String(c.id).padStart(4, '0')}
                  </code>
                  {statusBadge(c.status)}
                  <span className="badge badge--soft" style={{ fontSize: 12 }}>
                    {categoryLabels[c.category] ?? c.category}
                  </span>
                </div>
                <small style={{ color: 'var(--muted)', fontSize: 13 }}>{formatDate(c.created_at)}</small>
              </div>

              {/* Nội dung phản ánh */}
              <div
                style={{
                  fontSize: 14.5,
                  color: 'var(--ink)',
                  lineHeight: 1.6,
                  background: 'var(--surface)',
                  padding: '14px 16px',
                  borderRadius: 'var(--r-md)',
                  border: '1px solid var(--line)',
                }}
              >
                {c.content}
              </div>

              {/* Thông tin người gửi, đơn đặt & Thao tác */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 14 }}>
                <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', fontSize: 13, color: 'var(--soft)' }}>
                  <span>
                    <strong style={{ color: 'var(--ink)' }}>Người gửi:</strong>{' '}
                    {c.user ? `${c.user.name} (${c.user.email})` : 'Khách liên hệ vãng lai'}
                    {c.direction === 'owner_to_customer' ? ' · chủ sân' : c.user ? ' · khách' : ''}
                  </span>
                  {c.against && (
                    <span>
                      <strong style={{ color: 'var(--ink)' }}>Bị khiếu nại:</strong> {c.against.name}
                    </span>
                  )}
                  {c.booking && (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                      <strong style={{ color: 'var(--ink)' }}>Đơn đặt:</strong> #BK-{String(c.booking.id).padStart(5, '0')} · {c.booking.field_name ?? 'Sân'} · {formatMoney(c.booking.total_price)}
                      {c.booking.refund_status && (
                        <span style={{ color: c.booking.refund_status === 'pending' ? 'var(--warn)' : 'var(--pitch)', fontWeight: 600 }}>
                          ({c.booking.refund_status === 'pending' ? 'Chờ hoàn cọc' : 'Đã hoàn'})
                        </span>
                      )}
                    </span>
                  )}
                </div>

                <button
                  type="button"
                  className="button button--brand button--small"
                  onClick={() => openHandleModal(c)}
                  style={{ marginLeft: 'auto' }}
                >
                  {c.status === 'resolved' || c.status === 'rejected' ? 'Xem kết quả' : c.status === 'open' ? 'Xử lý khiếu nại' : 'Tiếp tục xử lý'}
                </button>
              </div>

              <EvidenceGallery attachments={c.attachments?.filter((a) => a.by === 'complainant')} label="Ảnh bên khiếu nại:" />

              {c.against && (
                <div style={{ padding: '10px 14px', background: 'var(--surface)', border: '1px solid var(--line)', borderRadius: 'var(--r-md)', fontSize: 13, lineHeight: 1.5 }}>
                  {c.response ? (
                    <><strong>Phản hồi của bên bị khiếu nại ({c.responded_at ? formatDateTime(c.responded_at) : ''}):</strong> {c.response}
                      <div style={{ marginTop: 6 }}><EvidenceGallery attachments={c.attachments?.filter((a) => a.by === 'respondent')} label="Ảnh phản hồi:" /></div></>
                  ) : c.status === 'open' || c.status === 'processing' ? (
                    <span style={{ color: 'var(--warn)' }}>Chưa có phản hồi{c.respond_by ? ` · hạn ${formatDateTime(c.respond_by)}` : ''}</span>
                  ) : (
                    <span style={{ color: 'var(--muted)' }}>Bên bị khiếu nại không phản hồi.</span>
                  )}
                </div>
              )}

              {c.resolution && (
                <div style={{ padding: '10px 14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 'var(--r-md)', fontSize: 13, lineHeight: 1.5 }}>
                  <strong style={{ color: '#166534' }}>Phương án giải quyết ({c.handler?.name ?? 'Admin'}):</strong> {c.resolution}
                  {c.refund_amount ? <div>Đã hoàn thêm cho khách: <strong>{formatMoney(c.refund_amount)}</strong></div> : null}
                  {c.attendance_confirmed ? <div>Đã xác nhận khách có đến sân (gỡ vắng mặt, trả 25 điểm uy tín).</div> : null}
                  {c.trust_penalty ? <div>Đã trừ khách <strong>{c.trust_penalty}</strong> điểm uy tín.</div> : null}
                </div>
              )}
            </div>
          ))}
        </div>
      </QueryState>

      {/* Modal Xử lý khiếu nại */}
      <Modal isOpen={!!activeComplaint} onClose={() => setActiveComplaint(null)} ariaLabel="Xử lý khiếu nại">
        {activeComplaint && (
          <form onSubmit={submitHandle} style={{ maxWidth: 520 }}>
            <h2 style={{ fontSize: 18, margin: '0 0 6px', color: 'var(--forest)' }}>
              Xử lý khiếu nại #KN-{String(activeComplaint.id).padStart(4, '0')}
            </h2>
            <p style={{ margin: '0 0 16px', fontSize: 13, color: 'var(--muted)' }}>
              Xem xét nội dung phản ánh và đưa ra phương án giải quyết thoả đáng cho các bên.
            </p>

            <div className="content-card" style={{ padding: 12, background: 'var(--surface)', marginBottom: 16, fontSize: 13 }}>
              <div style={{ marginBottom: 6 }}>
                <strong>Người khiếu nại:</strong> {activeComplaint.user?.name ?? 'Khách vãng lai'} ({activeComplaint.user?.email ?? 'Không có email'})
              </div>
              <div style={{ marginBottom: 6 }}>
                <strong>Phân loại:</strong> {categoryLabels[activeComplaint.category] ?? activeComplaint.category}
              </div>
              <div>
                <strong>Nội dung:</strong> <em>"{activeComplaint.content}"</em>
              </div>
            </div>

            {activeComplaint.against && (
              <div className="content-card" style={{ padding: 12, background: 'var(--surface)', marginBottom: 16, fontSize: 13, display: 'grid', gap: 6 }}>
                <div>
                  <strong>Bên bị khiếu nại:</strong> {activeComplaint.against.name}
                  {activeComplaint.parties?.respondent && (
                    <span style={{ color: 'var(--muted)' }}>
                      {' '}· huỷ đơn đã thanh toán 90 ngày: {activeComplaint.parties.respondent.owner_cancellations_90d} · vắng mặt: {activeComplaint.parties.respondent.no_shows} · khiếu nại đã nhận: {activeComplaint.parties.respondent.complaints_received} (đã chấp nhận {activeComplaint.parties.respondent.complaints_upheld})
                    </span>
                  )}
                </div>
                <div>
                  <strong>Phản hồi:</strong>{' '}
                  {activeComplaint.response ? <em>"{activeComplaint.response}"</em> : <span style={{ color: 'var(--muted)' }}>chưa có{activeComplaint.respond_by ? ` (hạn ${formatDateTime(activeComplaint.respond_by)})` : ''}</span>}
                </div>
              </div>
            )}

            {activeComplaint.booking && (
              <div className="content-card" style={{ padding: 12, background: 'var(--surface)', marginBottom: 16, fontSize: 13 }}>
                <div>
                  <strong>Đơn #BK-{String(activeComplaint.booking.id).padStart(5, '0')}:</strong> {activeComplaint.booking.field_name ?? 'Sân'} · khách đã trả{' '}
                  {formatMoney(activeComplaint.booking.payment_amount ?? 0)}
                  {activeComplaint.booking.no_show ? ' · đang bị ghi nhận vắng mặt' : ''}
                </div>
                {activeComplaint.status !== 'resolved' && activeComplaint.status !== 'rejected' && activeComplaint.direction !== 'owner_to_customer' && (
                  <div style={{ marginTop: 4, color: 'var(--muted)' }}>
                    Trong lúc khiếu nại chưa đóng, đơn này được giữ lại, chưa tính vào kỳ chi trả cho chủ sân.
                  </div>
                )}
              </div>
            )}

            {activeComplaint.status === 'resolved' || activeComplaint.status === 'rejected' ? (
              <div className="form-stack">
                <div className="notice notice--warn" style={{ fontSize: 13, lineHeight: 1.5 }}>
                  Khiếu nại đã đóng ({activeComplaint.status === 'resolved' ? 'chấp nhận' : 'từ chối'}) nên không sửa lại được.
                  {activeComplaint.resolution && <div style={{ marginTop: 6 }}><strong>Phương án:</strong> {activeComplaint.resolution}</div>}
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                  <button type="button" className="button button--ghost" onClick={() => setActiveComplaint(null)}>Đóng</button>
                </div>
              </div>
            ) : (
            <div className="form-stack">
              <label>
                <span>Trạng thái xử lý</span>
                <select value={handleStatus} onChange={(e) => setHandleStatus(e.target.value as any)}>
                  <option value="processing">Đang xác minh (chưa chốt phương án)</option>
                  <option value="resolved">Đã giải quyết (chấp nhận/thoả đáng)</option>
                  <option value="rejected">Từ chối khiếu nại (không có cơ sở)</option>
                </select>
              </label>

              {handleStatus === 'resolved' && activeComplaint.booking && activeComplaint.direction === 'owner_to_customer' && (
                <fieldset style={{ border: '1px solid var(--line)', borderRadius: 'var(--r-md)', padding: '10px 12px', margin: 0 }}>
                  <legend style={{ fontSize: 13, fontWeight: 600, padding: '0 6px' }}>Kết quả kèm theo (tuỳ chọn)</legend>
                  <label>
                    <span>Trừ điểm uy tín của khách (5–50 điểm)</span>
                    <input inputMode="numeric" placeholder="Để trống nếu không trừ điểm" value={penaltyInput} onChange={(e) => setPenaltyInput(digitsOnly(e.target.value, 2))} />
                  </label>
                </fieldset>
              )}

              {handleStatus === 'resolved' && activeComplaint.booking && activeComplaint.direction !== 'owner_to_customer' && (
                <fieldset style={{ border: '1px solid var(--line)', borderRadius: 'var(--r-md)', padding: '10px 12px', margin: 0 }}>
                  <legend style={{ fontSize: 13, fontWeight: 600, padding: '0 6px' }}>Kết quả kèm theo (tuỳ chọn)</legend>
                  {(activeComplaint.booking.refundable ?? 0) > 0 ? (
                    <label>
                      <span>Hoàn thêm cho khách (tối đa {formatMoney(activeComplaint.booking.refundable ?? 0)})</span>
                      <input
                        inputMode="numeric"
                        placeholder="Để trống nếu không hoàn tiền"
                        value={refundInput}
                        onChange={(e) => setRefundInput(digitsOnly(e.target.value, 9))}
                      />
                      <small className="form-help">Ghi nhận khoản hoàn chờ chuyển; sau khi chuyển khoản, đánh dấu "đã hoàn" ở trang Đơn đặt sân. Hoa hồng tính lại trên phần còn lại.</small>
                    </label>
                  ) : (
                    <small className="form-help" style={{ display: 'block' }}>Đơn này không còn khoản nào để hoàn thêm (không thu qua SportHub hoặc đã hoàn đủ).</small>
                  )}
                  {activeComplaint.booking.no_show && (
                    <label style={{ display: 'flex', gap: 8, alignItems: 'flex-start', marginTop: 8 }}>
                      <input type="checkbox" checked={confirmAttendance} onChange={(e) => setConfirmAttendance(e.target.checked)} style={{ marginTop: 3 }} />
                      <span>Xác nhận khách đã đến sân: gỡ ghi nhận vắng mặt và trả lại 25 điểm uy tín.</span>
                    </label>
                  )}
                </fieldset>
              )}

              <label>
                <span>Phương án xử lý / Lý do phản hồi (sẽ gửi qua email)</span>
                <textarea
                  required
                  rows={4}
                  value={resolution}
                  onChange={(e) => setResolution(e.target.value)}
                  placeholder="Ví dụ: Đã đối chiếu với chủ sân, xác nhận chủ sân đã chuyển khoản hoàn lại tiền cọc..."
                />
              </label>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 12 }}>
                <button type="button" className="button button--ghost" onClick={() => setActiveComplaint(null)}>
                  Đóng
                </button>
                <button type="submit" className="button button--brand" disabled={handleMutation.isPending}>
                  {handleMutation.isPending ? 'Đang lưu...' : 'Lưu kết quả & Gửi mail'}
                </button>
              </div>
            </div>
            )}
          </form>
        )}
      </Modal>
    </div>
  )
}
