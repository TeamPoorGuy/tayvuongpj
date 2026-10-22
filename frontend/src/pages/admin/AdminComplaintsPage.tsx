import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Eye, MessageSquare, Search } from 'lucide-react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { Modal } from '../../components/Modal'
import { PaymentProofModal } from '../../components/PaymentProofModal'
import { QueryState } from '../../components/QueryState'
import { useToast } from '../../components/Toast'
import { formatDate, formatMoney } from '../../utils/format'
import type { Complaint } from '../../types/api'
import { PageTitle } from '../customer/CustomerPages'

const categoryLabels: Record<string, string> = {
  refund_pending: 'Chưa nhận được tiền hoàn cọc',
  field_mismatch: 'Sân không đúng thực tế/mô tả',
  owner_cancelled: 'Chủ sân tự ý huỷ/không nhận',
  bad_behavior: 'Thái độ phục vụ/hành vi xấu',
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
  const [proofBookingId, setProofBookingId] = useState<number | null>(null)

  // Handle modal state
  const [handleStatus, setHandleStatus] = useState<'processing' | 'resolved' | 'rejected'>('resolved')
  const [resolution, setResolution] = useState('')

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
    mutationFn: async ({ id, status, resolution }: { id: number; status: string; resolution: string }) => {
      return (await api.patch(`/admin/complaints/${id}/handle`, { status, resolution })).data
    },
    onSuccess: () => {
      toast.success('Đã cập nhật trạng thái khiếu nại và gửi thông báo tới người dùng.')
      setActiveComplaint(null)
      setResolution('')
      void client.invalidateQueries({ queryKey: ['admin', 'complaints'] })
      void client.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xử lý khiếu nại.')
    },
  })

  const openHandleModal = (c: Complaint) => {
    setActiveComplaint(c)
    setHandleStatus(c.status === 'open' ? 'resolved' : (c.status as any))
    setResolution(c.resolution ?? '')
  }

  const submitHandle = (e: React.FormEvent) => {
    e.preventDefault()
    if (!activeComplaint) return
    handleMutation.mutate({
      id: activeComplaint.id,
      status: handleStatus,
      resolution,
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
                  </span>
                  {c.booking && (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                      <strong style={{ color: 'var(--ink)' }}>Đơn đặt:</strong> #BK-{String(c.booking.id).padStart(5, '0')} · {c.booking.field_name ?? 'Sân'} · {formatMoney(c.booking.total_price)}
                      {c.booking.refund_status && (
                        <span style={{ color: c.booking.refund_status === 'pending' ? 'var(--warn)' : 'var(--pitch)', fontWeight: 600 }}>
                          ({c.booking.refund_status === 'pending' ? 'Chờ hoàn cọc' : 'Đã hoàn'})
                        </span>
                      )}
                      <button
                        type="button"
                        className="button button--ghost button--small"
                        style={{ minHeight: 30, height: 30, padding: '0 10px', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 5 }}
                        onClick={() => setProofBookingId(c.booking!.id)}
                        title="Xem ảnh biên lai chuyển khoản của đơn này"
                      >
                        <Eye size={13} /> Biên lai
                      </button>
                    </span>
                  )}
                </div>

                <button
                  type="button"
                  className="button button--brand button--small"
                  onClick={() => openHandleModal(c)}
                  style={{ marginLeft: 'auto' }}
                >
                  {c.status === 'open' ? 'Xử lý khiếu nại' : 'Xem & Cập nhật'}
                </button>
              </div>

              {c.resolution && (
                <div style={{ padding: '10px 14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 'var(--r-md)', fontSize: 13, lineHeight: 1.5 }}>
                  <strong style={{ color: '#166534' }}>Phương án giải quyết ({c.handler?.name ?? 'Admin'}):</strong> {c.resolution}
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

            <div className="form-stack">
              <label>
                <span>Trạng thái xử lý</span>
                <select value={handleStatus} onChange={(e) => setHandleStatus(e.target.value as any)}>
                  <option value="processing">Đang xác minh (chưa chốt phương án)</option>
                  <option value="resolved">Đã giải quyết (chấp nhận/thoả đáng)</option>
                  <option value="rejected">Từ chối khiếu nại (không có cơ sở)</option>
                </select>
              </label>

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
          </form>
        )}
      </Modal>

      <PaymentProofModal
        isOpen={!!proofBookingId}
        onClose={() => setProofBookingId(null)}
        proofUrl={proofBookingId ? `/api/v1/bookings/${proofBookingId}/payment-proof` : null}
        title={`Biên lai đơn #BK-${proofBookingId ?? ''}`}
      />
    </div>
  )
}
