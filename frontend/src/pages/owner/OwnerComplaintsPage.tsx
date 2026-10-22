import { useQuery } from '@tanstack/react-query'
import { AlertCircle } from 'lucide-react'
import { useState } from 'react'
import { api } from '../../api/client'
import { ComplaintRespondModal } from '../../components/ComplaintRespond'
import { EvidenceGallery } from '../../components/EvidencePicker'
import { QueryState } from '../../components/QueryState'
import type { Complaint, Paginated } from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { complaintCategoryLabels } from '../../utils/complaint'
import { formatDate, formatDateTime } from '../../utils/format'

const statusLabel: Record<Complaint['status'], string> = {
  open: 'Chờ xử lý',
  processing: 'Đang xác minh',
  resolved: 'Đã chấp nhận',
  rejected: 'Không chấp nhận',
}

/** Khiếu nại khách gửi về sân của bạn (cần phản hồi) và khiếu nại bạn gửi về khách. */
export function OwnerComplaintsPage() {
  const [respondingId, setRespondingId] = useState<number | null>(null)
  const complaints = useQuery({
    queryKey: ['owner', 'complaints'],
    refetchInterval: 60_000,
    queryFn: async () => (await api.get<Paginated<Complaint>>('/field-owner/complaints')).data,
  })
  const list = complaints.data?.data ?? []

  return (
    <>
      <PageTitle
        icon={<AlertCircle />}
        kicker="Hỗ trợ"
        title="Khiếu nại"
        copy="Khi khách khiếu nại về sân, bạn được báo và có một lần phản hồi trước khi Ban quản trị quyết định. Bạn cũng gửi khiếu nại về khách ở mục Đơn đặt sân."
      />

      <QueryState
        isPending={complaints.isPending}
        isError={complaints.isError}
        isEmpty={list.length === 0}
        empty={<Empty text="Chưa có khiếu nại nào liên quan tới bạn." />}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          {list.map((c) => {
            const againstMe = c.direction !== 'owner_to_customer'
            return (
              <article key={c.id} className="content-card" style={{ display: 'grid', gap: 10 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center', justifyContent: 'space-between' }}>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
                    <strong>#KN-{String(c.id).padStart(4, '0')}</strong>
                    <span className="badge badge--soft">{againstMe ? 'Khách khiếu nại bạn' : 'Bạn khiếu nại khách'}</span>
                    <span className="badge badge--soft">{complaintCategoryLabels[c.category] ?? c.category}</span>
                    <span className="badge">{statusLabel[c.status]}</span>
                  </div>
                  <small className="text-muted">{formatDate(c.created_at)}</small>
                </div>

                {c.booking && (
                  <small className="text-muted">
                    Đơn #BK-{String(c.booking.id).padStart(5, '0')} · {c.booking.field_name ?? 'Sân'} · ngày {formatDate(c.booking.booking_date)}
                  </small>
                )}

                <p style={{ margin: 0, lineHeight: 1.6 }}>
                  <strong>{againstMe ? 'Nội dung khách nêu:' : 'Nội dung bạn nêu:'}</strong> {c.content}
                </p>

                <EvidenceGallery attachments={c.attachments?.filter((a) => a.by === 'complainant')} label={againstMe ? 'Ảnh khách gửi:' : 'Ảnh bạn gửi:'} />

                {c.response && (
                  <p style={{ margin: 0, lineHeight: 1.6 }}>
                    <strong>{againstMe ? 'Phản hồi của bạn:' : 'Phản hồi của khách:'}</strong> {c.response}
                  </p>
                )}

                <EvidenceGallery attachments={c.attachments?.filter((a) => a.by === 'respondent')} label={againstMe ? 'Ảnh bạn phản hồi:' : 'Ảnh khách phản hồi:'} />

                {c.can_respond && (
                  <div className="notice notice--warn" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center', justifyContent: 'space-between', fontSize: 13 }}>
                    <span>
                      Bạn có một lần phản hồi{c.respond_by ? ` trước ${formatDateTime(c.respond_by)}` : ''}. Ban quản trị sẽ quyết định dựa trên ý kiến của cả hai bên.
                    </span>
                    <button type="button" className="button button--brand button--small" onClick={() => setRespondingId(c.id)}>
                      Phản hồi
                    </button>
                  </div>
                )}

                {(c.status === 'resolved' || c.status === 'rejected') && c.resolution && (
                  <div className={`notice ${c.status === 'resolved' ? 'notice--success' : 'notice--warn'}`} style={{ fontSize: 13, lineHeight: 1.5 }}>
                    <strong>Kết luận của Ban quản trị:</strong> {c.resolution}
                  </div>
                )}
              </article>
            )
          })}
        </div>
      </QueryState>

      <ComplaintRespondModal complaintId={respondingId} role="field-owner" onClose={() => setRespondingId(null)} />
    </>
  )
}
