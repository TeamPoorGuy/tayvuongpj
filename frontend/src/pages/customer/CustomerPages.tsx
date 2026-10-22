import { digitsOnly } from '../../utils/input'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertCircle,
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  CircleUserRound,
  Clock3,
  ListOrdered,
  MapPin,
  Phone,
  Repeat,
  ShieldCheck,
  Sparkles,
  Star,
  Timer,
  XCircle,
  Zap,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { ComplaintRespondModal } from '../../components/ComplaintRespond'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import { useAuth } from '../../context/auth-context'
import type { ApiEnvelope, Booking, BookingWaitlist, Paginated, User } from '../../types/api'
import { getLocalTodayString } from '../../utils/date'
import { complaintCategoryLabels } from '../../utils/complaint'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'



const fallbackFieldImage =
  'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=800&q=80'

function OfferCountdown({ expiresAt }: { expiresAt: string }) {
  const [timeLeft, setTimeLeft] = useState(() =>
    Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000)),
  )

  useEffect(() => {
    const timer = setInterval(() => {
      const remaining = Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000))
      setTimeLeft(remaining)
    }, 1000)
    return () => clearInterval(timer)
  }, [expiresAt])

  const mins = Math.floor(timeLeft / 60)
  const secs = timeLeft % 60
  const isExpiringSoon = timeLeft < 300

  return (
    <span className={`countdown-chip ${isExpiringSoon ? 'countdown-chip--danger' : ''}`}>
      <Timer size={13} /> Còn {mins}:{secs.toString().padStart(2, '0')} để xác nhận
    </span>
  )
}

function StarRatingInput({
  value,
  onChange,
}: {
  value: number
  onChange: (val: number) => void
}) {
  const [hover, setHover] = useState<number | null>(null)
  const current = hover ?? value
  const labels: Record<number, string> = {
    1: '1 sao - Rất tệ',
    2: '2 sao - Tệ',
    3: '3 sao - Bình thường',
    4: '4 sao - Tốt',
    5: '5 sao - Tuyệt vời',
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, margin: '8px 0 14px' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            type="button"
            style={{
              background: 'none',
              border: 'none',
              padding: '4px 2px',
              cursor: 'pointer',
              color: star <= current ? '#f59e0b' : 'var(--border, #cbd5e1)',
              transition: 'transform 0.15s ease',
              display: 'inline-flex',
            }}
            onMouseEnter={() => setHover(star)}
            onMouseLeave={() => setHover(null)}
            onClick={() => onChange(star)}
            aria-label={`${star} sao`}
          >
            <Star
              size={28}
              fill={star <= current ? 'currentColor' : 'none'}
              strokeWidth={1.5}
            />
          </button>
        ))}
        <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--pitch, #0b3025)', marginLeft: 8 }}>
          {labels[current] ?? `${current} sao`}
        </span>
      </div>
    </div>
  )
}

export function CustomerBookingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [activeTab, setActiveTab] = useState<'bookings' | 'waitlist'>('bookings')
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const [cancellingBooking, setCancellingBooking] = useState<Booking | null>(null)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelScope, setCancelScope] = useState<'single' | 'recurring'>('single')
  const [cancellingWaitlist, setCancellingWaitlist] = useState<{ id: number; isOffered: boolean } | null>(null)
  const [reviewing, setReviewing] = useState<Booking | null>(null)
  const [activeReviewing, setActiveReviewing] = useState<Booking | null>(null)
  const [reviewRating, setReviewRating] = useState(5)
  const [complaintBooking, setComplaintBooking] = useState<Booking | null>(null)
  const [respondingId, setRespondingId] = useState<number | null>(null)
  const [complaintCategory, setComplaintCategory] = useState('refund_pending')
  const [complaintContent, setComplaintContent] = useState('')
  const currentReview = reviewing ?? activeReviewing

  const openReview = (booking: Booking) => {
    setReviewing(booking)
    setActiveReviewing(booking)
    setReviewRating(5)
  }

  const openComplaint = (booking: Booking) => {
    setComplaintBooking(booking)
    setComplaintCategory(booking.no_show ? 'wrong_no_show' : booking.refund?.status === 'pending' ? 'refund_pending' : 'field_mismatch')
    setComplaintContent('')
  }

  const bookings = useQuery({
    queryKey: ['customer', 'bookings', status, page],
    // Đơn đang giữ chỗ/đã xác nhận có thể đổi trạng thái (thanh toán xong, hết hạn giữ chỗ, đến giờ chơi) mà khách không làm gì.
    refetchInterval: (query) => (query.state.data?.data?.some((b) => b.status === 'pending' || b.status === 'confirmed') ? 30_000 : false),
    queryFn: async () =>
      (await api.get<Paginated<Booking>>('/customer/bookings', { params: { page, status: status || undefined } })).data,
  })

  const waitlists = useQuery({
    queryKey: ['customer', 'waitlist'],
    queryFn: async () => (await api.get<Paginated<BookingWaitlist>>('/customer/waitlist')).data,
    // Có lời mời/đang chờ thì kiểm tra lại mỗi 30 giây để không lỡ suất 15 phút.
    refetchInterval: (query) =>
      query.state.data?.data?.some((w) => w.status === 'offered' || w.status === 'waiting') ? 30_000 : false,
  })

  const cancel = useMutation({
    mutationFn: async ({ booking, cancelReason }: { booking: Booking; cancelReason?: string }) =>
      api.patch(`/customer/bookings/${booking.id}/cancel`, { cancel_reason: cancelReason }),
    onSuccess: (_, { booking }) => {
      toast.success(`Đã hủy lịch đặt sân #BK-${booking.id}.`)
      setCancellingBooking(null)
      setCancelReason('')
      setCancelScope('single')
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể hủy lịch')
    },
  })

  const cancelRecurring = useMutation({
    mutationFn: async ({ groupId, cancelReason }: { groupId: string; cancelReason?: string }) =>
      api.post(`/customer/bookings/recurring/${groupId}/cancel`, { cancel_reason: cancelReason }),
    onSuccess: () => {
      toast.success('Đã hủy các buổi sắp tới trong chuỗi đặt cố định.')
      setCancellingBooking(null)
      setCancelReason('')
      setCancelScope('single')
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể hủy chuỗi lịch')
    },
  })

  const navigate = useNavigate()
  const acceptWaitlist = useMutation({
    mutationFn: async (waitlistId: number) => api.post(`/customer/waitlist/${waitlistId}/accept`),
    onSuccess: (res) => {
      toast.success('Bạn đã nhận được suất sân. Hãy chuyển khoản để giữ chỗ.')
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      // Nhận suất = giữ chỗ tạm 15 phút: đưa khách thẳng tới trang chuyển khoản.
      const paymentId = res.data?.data?.payment?.id
      if (paymentId) navigate(`/payments/${paymentId}`)
      else setActiveTab('bookings')
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể nhận suất sân')
    },
  })

  const cancelWaitlist = useMutation({
    mutationFn: async (waitlistId: number) => api.delete(`/customer/waitlist/${waitlistId}`),
    onSuccess: () => {
      toast.success('Đã hủy khỏi hàng đợi giữ chỗ.')
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể hủy yêu cầu')
    },
  })

  const review = useMutation({
    mutationFn: async (payload: { booking_id: number; rating: number; comment: string }) =>
      api.post('/customer/reviews', payload),
    onSuccess: () => {
      toast.success('Cảm ơn bạn đã gửi đánh giá!')
      setReviewing(null)
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể gửi đánh giá')
    },
  })

  const complaintMutation = useMutation({
    mutationFn: async ({
      bookingId,
      category,
      content,
    }: {
      bookingId: number
      category: string
      content: string
    }) =>
      api.post('/customer/complaints', {
        booking_id: bookingId,
        subject_type: 'booking',
        subject_id: String(bookingId),
        category,
        content,
      }),
    onSuccess: () => {
      toast.success('Đã gửi khiếu nại tới Ban quản trị SportHub thành công!')
      setComplaintBooking(null)
      setComplaintContent('')
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể gửi khiếu nại')
    },
  })

  const submitReview = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!reviewing) return
    const data = new FormData(event.currentTarget)
    review.mutate({
      booking_id: reviewing.id,
      rating: reviewRating,
      comment: String(data.get('comment')),
    })
  }

  const offeredCount = waitlists.data?.data.filter((w) => w.status === 'offered').length ?? 0
  const activeWaitlistCount =
    waitlists.data?.data.filter((w) => w.status === 'waiting' || w.status === 'offered').length ?? 0

  return (
    <>
      <PageTitle
        icon={<CalendarDays />}
        kicker="Lịch chơi & Hàng đợi"
        title="Quản lý lịch & Suất giữ chỗ"
        copy="Theo dõi đơn đặt sân và nhận cơ hội đặt sân khi có người huỷ lịch. Khi đến sân, chủ sân sẽ xác nhận bạn dựa trên mã đơn và thông tin tài khoản."
      />

      {/* Tab Switcher */}
      <div className="tab-pill-list">
        <button
          type="button"
          className={`tab-pill ${activeTab === 'bookings' ? 'active' : ''}`}
          onClick={() => setActiveTab('bookings')}
        >
          <CalendarDays size={16} />
          <span>Đơn đặt của tôi</span>
          {Boolean(bookings.data?.meta.total) && (
            <span className="tab-pill__count">{bookings.data?.meta.total}</span>
          )}
        </button>

        <button
          type="button"
          className={`tab-pill ${activeTab === 'waitlist' ? 'active' : ''}`}
          onClick={() => setActiveTab('waitlist')}
        >
          <ListOrdered size={16} />
          <span>Hàng đợi Waitlist</span>
          {activeWaitlistCount > 0 && (
            <span className={`tab-pill__count ${offeredCount > 0 ? 'tab-pill__count--highlight' : ''}`}>
              {activeWaitlistCount}
            </span>
          )}
          {offeredCount > 0 && <span className="tab-pill__ping" title="Có suất sân trống đang chờ bạn!" />}
        </button>
      </div>

      {activeTab === 'bookings' && (
        <>
          <div className="toolbar">
            <select
              value={status}
              aria-label="Lọc theo trạng thái đơn"
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">Mọi trạng thái</option>
              <option value="pending">Đang giữ chỗ, chờ thanh toán</option>
              <option value="confirmed">Đã xác nhận</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Đã hủy</option>
              <option value="rejected">Bị từ chối</option>
            </select>
            <Link to="/fields" className="button button--brand button--small">
              Đặt sân mới
            </Link>
          </div>

          {bookings.isPending && (
            <div className="query-state query-state--loading" aria-live="polite">
              Đang tải lịch đặt...
            </div>
          )}
          {bookings.isError && (
            <div className="notice notice--error" role="alert">
              Không thể tải lịch đặt. Vui lòng thử lại.
            </div>
          )}

          <div className="booking-list">
            {bookings.data?.data.map((booking) => (
              <article className="booking-item" key={booking.id}>
                <div className="booking-item__image">
                  <img
                    src={booking.field?.primary_image || fallbackFieldImage}
                    alt={booking.field?.name ?? 'Sân thể thao'}
                    onError={(e) => {
                      e.currentTarget.onerror = null
                      e.currentTarget.src = fallbackFieldImage
                    }}
                  />
                </div>
                <div className="booking-item__body">
                  <div className="booking-item__top">
                    <div>
                      <small>#BK-{String(booking.id).padStart(5, '0')}</small>
                      <h3>
                        {booking.field?.slug ? (
                          <Link to={`/fields/${booking.field.slug}`} title="Mở trang sân để đặt lại">
                            {booking.field.name}
                          </Link>
                        ) : (
                          booking.field?.name
                        )}
                      </h3>
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      {booking.recurring_group_id && (
                        <span className="badge badge--info" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }} title="Chuỗi đặt cố định hằng tuần">
                          <Repeat size={11} /> Cố định hằng tuần
                        </span>
                      )}
                      {booking.no_show && <span className="badge badge--warning">Vắng mặt (-25 điểm)</span>}
                      {booking.checked_in_at && (
                        <span className="badge badge--success" title={`Chủ sân xác nhận đến sân lúc ${new Date(booking.checked_in_at).toLocaleTimeString('vi-VN')}`}>
                          <CheckCircle2 size={12} /> Đã đến sân (+10đ)
                        </span>
                      )}
                      <StatusBadge status={booking.status} payment={booking.payment?.status} />
                    </div>
                  </div>
                  <div className="booking-meta">
                    <span>
                      <CalendarDays />
                      {formatDate(booking.booking_date)}
                    </span>
                    <span>
                      <Clock3 />
                      {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
                    </span>
                    <span>
                      <MapPin />
                      {booking.field?.address}
                    </span>
                  </div>
                  {booking.status === 'pending' && booking.payment?.status === 'awaiting' && (
                    <p className="form-help">
                      Đang giữ chỗ. Hãy chuyển khoản vào tài khoản SportHub
                      {booking.payment.due_at && ` trước ${formatDateTime(booking.payment.due_at)}`}, quá hạn chỗ sẽ được nhả cho người khác.
                    </p>
                  )}
                  {booking.status === 'pending' && booking.payment?.status === 'reported' && (
                    <p className="form-help">
                      Bạn đã báo chuyển {formatMoney(booking.payment.amount ?? 0)} (nội dung {booking.payment.code}). Đang chờ
                      chủ sân xác nhận đã nhận tiền.
                    </p>
                  )}
                  {booking.status === 'confirmed' && !booking.checked_in_at && booking.check_in_code && (
                    <div style={{ background: 'var(--pitch-soft, #e4f1e6)', border: '1px solid rgba(31, 135, 94, 0.3)', borderRadius: 10, padding: '10px 14px', margin: '10px 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 10 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Zap size={16} style={{ color: 'var(--pitch)' }} />
                        <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--forest)' }}>
                          Mã check-in: <strong style={{ fontSize: 16, letterSpacing: '0.08em', color: 'var(--forest)' }}>{booking.check_in_code}</strong>
                        </span>
                      </div>
                      <span style={{ fontSize: 12, color: 'var(--soft)' }}>Đưa mã này cho chủ sân khi đến nhận sân (+10đ uy tín)</span>
                    </div>
                  )}
                  {booking.status === 'confirmed' && !booking.checked_in_at && booking.booking_date >= getLocalTodayString() && (
                    <ul className="booking-facts">
                      {booking.payment?.status === 'paid' && (
                        <li>
                          <strong>Đã thanh toán {formatMoney(booking.payment.amount ?? 0)}</strong>
                          {booking.payment.option === 'deposit' && booking.total_price - (booking.payment.amount ?? 0) > 0
                            ? ` · còn ${formatMoney(booking.total_price - (booking.payment.amount ?? 0))} trả tại sân`
                            : ' · không phải trả thêm tại sân'}
                        </li>
                      )}
                      <li className={booking.can_be_cancelled && booking.cancel_penalty ? 'is-warn' : undefined}>
                        {!booking.can_be_cancelled
                          ? 'Đã đến giờ chơi nên không thể tự huỷ đơn.'
                          : booking.cancel_penalty
                            ? 'Đã sát giờ chơi: huỷ lúc này hoàn ít hoặc không hoàn tiền và bị trừ 15 điểm uy tín.'
                            : booking.cancel_until
                              ? <>Huỷ trước <strong>{formatDateTime(booking.cancel_until)}</strong> được hoàn 100%, sau mốc này hoàn theo chính sách.</>
                              : 'Huỷ lúc này được hoàn tiền theo chính sách huỷ.'}
                      </li>
                    </ul>
                  )}
                  {(booking.status === 'cancelled' || booking.status === 'rejected') && booking.cancel_reason && (
                    <p className="form-help">Lý do: {booking.cancel_reason}</p>
                  )}
                  {booking.refund?.status === 'pending' && (
                    <div className="notice notice--warn" style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      <strong>Khoản hoàn tiền:</strong> SportHub sẽ hoàn {formatMoney(booking.refund.amount)} cho bạn. Nếu sau 24h chưa nhận được, hãy bấm &quot;Khiếu nại&quot; bên dưới để được hỗ trợ.
                    </div>
                  )}
                  {booking.refund?.status === 'done' && (
                    <div className="notice notice--success" style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      Đã hoàn trả {formatMoney(booking.refund.amount)}{booking.refund.refunded_at ? ` lúc ${formatDateTime(booking.refund.refunded_at)}` : ''}.
                    </div>
                  )}
                  {booking.complaint_against && (
                    <div className={'notice notice--warn'} style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      <strong>Chủ sân khiếu nại về đơn này ({complaintCategoryLabels[booking.complaint_against.category] ?? booking.complaint_against.category}):</strong> {booking.complaint_against.content}
                      {booking.complaint_against.response && <div style={{ marginTop: 4 }}><strong>Phản hồi của bạn:</strong> {booking.complaint_against.response}</div>}
                      {booking.complaint_against.can_respond && (
                        <div style={{ marginTop: 6, display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
                          <span>Bạn có một lần phản hồi trước khi Ban quản trị quyết định.</span>
                          <button type="button" className="button button--brand button--small" onClick={() => setRespondingId(booking.complaint_against!.id)}>
                            Phản hồi
                          </button>
                        </div>
                      )}
                      {(booking.complaint_against.status === 'resolved' || booking.complaint_against.status === 'rejected') && (
                        <div style={{ marginTop: 4 }}>
                          <strong>Kết luận:</strong> {booking.complaint_against.status === 'resolved' ? 'Khiếu nại được chấp nhận' : 'Khiếu nại không được chấp nhận'}
                          {booking.complaint_against.resolution ? ` — ${booking.complaint_against.resolution}` : ''}
                          {booking.complaint_against.trust_penalty ? ` Bạn bị trừ ${booking.complaint_against.trust_penalty} điểm uy tín.` : ''}
                        </div>
                      )}
                    </div>
                  )}
                  {booking.complaint && (booking.complaint.status === 'resolved' || booking.complaint.status === 'rejected') && (
                    <div className={`notice ${booking.complaint.status === 'resolved' ? 'notice--success' : 'notice--warn'}`} style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      <strong>Khiếu nại {booking.complaint.status === 'resolved' ? 'đã được chấp nhận' : 'không được chấp nhận'}:</strong> {booking.complaint.resolution}
                      {booking.complaint.refund_amount ? <> SportHub hoàn thêm {formatMoney(booking.complaint.refund_amount)} cho bạn.</> : null}
                      {booking.complaint.attendance_confirmed ? ' Đã gỡ ghi nhận vắng mặt và hoàn lại 25 điểm uy tín.' : null}
                    </div>
                  )}
                  <div className="booking-item__footer">
                    <strong>{formatMoney(booking.total_price)}</strong>

                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                      {booking.payment?.id && (
                        <Link to={`/payments/${booking.payment.id}`} className={`button button--small ${booking.payment.status === 'awaiting' ? 'button--brand' : 'button--ghost'}`}>
                          {booking.payment.status === 'awaiting' ? 'Thanh toán' : 'Xem thanh toán'}
                        </Link>
                      )}
                      {booking.can_be_cancelled && (
                        <button
                          className="button button--danger button--small"
                          onClick={() => setCancellingBooking(booking)}
                        >
                          Hủy lịch
                        </button>
                      )}
                      {booking.status === 'completed' && !booking.review && !booking.no_show && (
                        <button
                          className="button button--brand button--small"
                          onClick={() => openReview(booking)}
                        >
                          <Star size={15} /> Đánh giá
                        </button>
                      )}
                      {booking.complaint && (booking.complaint.status === 'open' || booking.complaint.status === 'processing') ? (
                        <span className="badge badge--soft" title="Mỗi đơn chỉ có một khiếu nại đang xử lý">
                          <AlertCircle size={13} /> Khiếu nại {booking.complaint.status === 'open' ? 'đã gửi, chờ xử lý' : 'đang được xác minh'}
                        </span>
                      ) : (
                        <button
                          type="button"
                          className="button button--ghost button--small"
                          onClick={() => openComplaint(booking)}
                          title="Khiếu nại hoặc phản ánh về đơn đặt sân này"
                        >
                          <AlertCircle size={14} /> Khiếu nại
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {!bookings.isPending && !bookings.isError && !bookings.data?.data.length && (
            <Empty text={status ? 'Không có đơn nào ở trạng thái này.' : 'Bạn chưa có lịch đặt sân nào. Bấm "Đặt sân mới" để bắt đầu.'} />
          )}

          <Pagination
            page={page}
            lastPage={bookings.data?.meta.last_page ?? 1}
            total={bookings.data?.meta.total}
            onPageChange={setPage}
          />
        </>
      )}

      {activeTab === 'waitlist' && (
        <section className="waitlist-section">
          <div className="waitlist-info-banner">
            <Sparkles size={20} />
            <div>
              <strong>Cơ chế hàng đợi thông minh SportHub</strong>
              <p>
                Khi khung giờ bạn mong muốn đã kín chỗ, bạn có thể tham gia hàng đợi. Nếu có người hủy
                hoặc đơn bị huỷ, hệ thống sẽ tự động chuyển cơ hội sang người có Điểm Uy Tín cao nhất.
                Bạn có 15 phút để xác nhận nhận sân!
              </p>
            </div>
          </div>

          <div className="waitlist-list">
            {waitlists.data?.data.map((item) => (
              <article
                key={item.id}
                className={`waitlist-card ${item.status === 'offered' ? 'waitlist-card--offered' : ''}`}
              >
                <div className="waitlist-card__header">
                  <div>
                    <span className="waitlist-card__badge">Hàng đợi #{item.id}</span>
                    <h3>{item.sports_field?.name ?? 'Sân thể thao'}</h3>
                  </div>
                  <div>
                    {item.status === 'offered' ? (
                      <span className="badge badge--success">🎉 Đã có suất trống!</span>
                    ) : item.status === 'waiting' ? (
                      <span className="badge badge--soft">Đang chờ lượt</span>
                    ) : item.status === 'accepted' ? (
                      <span className="badge badge--success">Đã nhận sân</span>
                    ) : item.status === 'expired' ? (
                      <span className="badge badge--soft">Hết hạn</span>
                    ) : (
                      <span className="badge badge--soft">Đã hủy</span>
                    )}
                  </div>
                </div>

                <div className="booking-meta">
                  <span>
                    <CalendarDays />
                    {formatDate(item.booking_date)}
                  </span>
                  <span>
                    <Clock3 />
                    {item.time_slot?.start_time} – {item.time_slot?.end_time}
                  </span>
                  <span>
                    <ShieldCheck />
                    Điểm ưu tiên xếp hàng: {item.priority_score} điểm
                  </span>
                </div>

                {item.status === 'offered' && item.offer_expires_at && (
                  <div className="waitlist-offer-alert">
                    <OfferCountdown expiresAt={item.offer_expires_at} />
                    <p>
                      Suất sân này vừa được mở ra do có khách huỷ lịch! Nhấn &quot;Nhận sân ngay&quot; để
                      xác nhận giữ sân trước khi hết thời gian 15 phút.
                    </p>
                  </div>
                )}

                <div className="waitlist-card__actions">
                  {item.status === 'offered' && (
                    <>
                      <button
                        type="button"
                        className="button button--brand"
                        disabled={acceptWaitlist.isPending}
                        onClick={() => acceptWaitlist.mutate(item.id)}
                      >
                        <CheckCircle2 size={16} /> {acceptWaitlist.isPending ? 'Đang xác nhận...' : 'Nhận sân ngay'}
                      </button>
                      <button
                        type="button"
                        className="button button--ghost button--danger"
                        disabled={cancelWaitlist.isPending}
                        onClick={() => setCancellingWaitlist({ id: item.id, isOffered: true })}
                      >
                        <XCircle size={16} /> Bỏ lượt
                      </button>
                    </>
                  )}

                  {item.status === 'waiting' && (
                    <button
                      type="button"
                      className="button button--ghost button--small"
                      disabled={cancelWaitlist.isPending}
                      onClick={() => setCancellingWaitlist({ id: item.id, isOffered: false })}
                    >
                      Hủy giữ chỗ
                    </button>
                  )}
                </div>
              </article>
            ))}
          </div>

          {waitlists.isPending && (
            <QueryState type="loading" message="Đang tải danh sách hàng đợi giữ chỗ..." />
          )}

          {waitlists.isError && (
            <QueryState
              type="error"
              message={errorMessage(waitlists.error)}
              onRetry={() => void waitlists.refetch()}
            />
          )}

          {!waitlists.isPending && !waitlists.isError && !waitlists.data?.data.length && (
            <Empty text="Bạn chưa tham gia hàng đợi giữ chỗ nào. Khi xem chi tiết sân có khung giờ đã kín, hãy bấm vào khung giờ đó để tham gia hàng đợi." />
          )}
        </section>
      )}

      {/* Modal Đánh giá sân */}
      <Modal isOpen={!!reviewing} onClose={() => setReviewing(null)} asForm onSubmit={submitReview}>
        <h2>Đánh giá {currentReview?.field?.name}</h2>
        <label>
          <span>Đánh giá chất lượng</span>
          <StarRatingInput value={reviewRating} onChange={setReviewRating} />
        </label>
        <label>
          <span>Nội dung đánh giá</span>
          <textarea
            name="comment"
            required
            rows={3}
            placeholder="Chia sẻ cảm nhận về chất lượng mặt sân, ánh sáng, dịch vụ..."
          />
        </label>
        <div className="modal__actions">
          <button type="button" className="button button--ghost" onClick={() => setReviewing(null)}>
            Đóng
          </button>
          <button disabled={review.isPending} className="button button--brand">
            {review.isPending ? 'Đang gửi...' : 'Gửi đánh giá'}
          </button>
        </div>
      </Modal>

      {/* Modal Huỷ đơn đặt sân khách hàng */}
      <Modal
        isOpen={!!cancellingBooking}
        onClose={() => {
          setCancellingBooking(null)
          setCancelReason('')
          setCancelScope('single')
        }}
        title={`Hủy đơn đặt sân #BK-${cancellingBooking?.id}`}
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (!cancellingBooking) return
          if (cancelScope === 'recurring' && cancellingBooking.recurring_group_id) {
            cancelRecurring.mutate({
              groupId: cancellingBooking.recurring_group_id,
              cancelReason: cancelReason.trim() || undefined,
            })
          } else {
            cancel.mutate({
              booking: cancellingBooking,
              cancelReason: cancelReason.trim() || undefined,
            })
          }
        }}
      >
        {cancellingBooking && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <p style={{ margin: 0, fontSize: 14 }}>
              Bạn đang yêu cầu hủy đơn đặt sân <strong>{cancellingBooking.field?.name}</strong> ngày{' '}
              <strong>{formatDate(cancellingBooking.booking_date)}</strong> ({cancellingBooking.time_slot?.start_time} – {cancellingBooking.time_slot?.end_time}).
            </p>

            {cancellingBooking.recurring_group_id && (
              <div style={{ padding: '10px 14px', background: 'var(--surface-sunken)', borderRadius: 8, border: '1px solid var(--border)' }}>
                <span style={{ fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8, color: 'var(--forest)' }}>
                  <Repeat size={14} /> Đơn thuộc chuỗi đặt cố định hằng tuần:
                </span>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', margin: 0 }}>
                    <input
                      type="radio"
                      name="customer_cancel_scope"
                      value="single"
                      checked={cancelScope === 'single'}
                      onChange={() => setCancelScope('single')}
                    />
                    <span>Chỉ hủy lịch buổi này (#BK-{cancellingBooking.id})</span>
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', margin: 0 }}>
                    <input
                      type="radio"
                      name="customer_cancel_scope"
                      value="recurring"
                      checked={cancelScope === 'recurring'}
                      onChange={() => setCancelScope('recurring')}
                    />
                    <span>Hủy tất cả các buổi sắp tới trong chuỗi cố định</span>
                  </label>
                </div>
              </div>
            )}

            {(() => {
              const ownerPhone =
                cancellingBooking.field?.owner?.business?.business_phone ||
                cancellingBooking.field?.owner?.phone
              if (!ownerPhone) return null
              return (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', borderRadius: 8, background: 'var(--surface-sunken, #f8fafc)', fontSize: 13 }}>
                  <Phone size={15} style={{ color: 'var(--pitch)' }} />
                  <span>
                    Hotline chủ sân: <a href={`tel:${ownerPhone}`} style={{ fontWeight: 700, color: 'var(--pitch)' }}>{ownerPhone}</a> (Liên hệ nếu bạn muốn dời lịch thay vì hủy đơn).
                  </span>
                </div>
              )
            })()}

            {cancellingBooking.cancel_penalty ? (
              <div className="notice notice--error" style={{ margin: 0, fontSize: 13, lineHeight: 1.5 }}>
                <AlertTriangle size={18} />
                <div>
                  <strong>Cảnh báo hủy muộn:</strong> Đang trong khung giờ hủy muộn (dưới 4 tiếng trước giờ chơi). Hủy đơn lúc này bạn sẽ <strong>KHÔNG được hoàn tiền cọc</strong> và bị <strong>trừ 15 điểm uy tín</strong>.
                </div>
              </div>
            ) : cancellingBooking.refund_if_cancelled != null ? (
              <div
                className={`notice ${cancellingBooking.refund_if_cancelled > 0 ? 'notice--success' : 'notice--warn'}`}
                style={{ margin: 0, fontSize: 13, lineHeight: 1.5 }}
              >
                <CheckCircle2 size={18} />
                <div>
                  {cancellingBooking.refund_if_cancelled > 0 ? (
                    <>
                      <strong>Hủy lúc này bạn được hoàn {formatMoney(cancellingBooking.refund_if_cancelled)}</strong>
                      {cancellingBooking.refund_if_cancelled < (cancellingBooking.payment?.amount ?? 0) &&
                        ` (một phần trong ${formatMoney(cancellingBooking.payment?.amount ?? 0)} đã chuyển)`}
                      , không bị trừ điểm uy tín.
                    </>
                  ) : (
                    <>
                      <strong>Hủy lúc này bạn không được hoàn tiền</strong> vì đã sát giờ chơi.
                    </>
                  )}
                </div>
              </div>
            ) : (
              <div className="notice notice--success" style={{ margin: 0, fontSize: 13, lineHeight: 1.5 }}>
                <CheckCircle2 size={18} />
                <div>
                  <strong>Chưa thanh toán:</strong> huỷ lúc này không mất phí và không bị trừ điểm uy tín.
                </div>
              </div>
            )}

            <label style={{ margin: 0 }}>
              <span>Lý do hủy đơn (tùy chọn)</span>
              <textarea
                rows={3}
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                placeholder="VD: Có việc bận đột xuất, thời tiết không thuận lợi..."
                maxLength={500}
              />
            </label>

            <div className="modal__actions" style={{ marginTop: 8 }}>
              <button
                type="button"
                className="button button--ghost"
                onClick={() => {
                  setCancellingBooking(null)
                  setCancelReason('')
                  setCancelScope('single')
                }}
              >
                Giữ lại đơn
              </button>
              <button
                type="submit"
                className="button button--danger"
                disabled={cancel.isPending || cancelRecurring.isPending}
              >
                {cancel.isPending || cancelRecurring.isPending
                  ? 'Đang hủy...'
                  : cancelScope === 'recurring'
                    ? 'Hủy toàn bộ chuỗi'
                    : 'Xác nhận hủy đặt sân'}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Confirm modal for waitlist actions */}
      <ConfirmModal
        isOpen={!!cancellingWaitlist}
        onClose={() => setCancellingWaitlist(null)}
        onConfirm={() => {
          if (cancellingWaitlist) {
            cancelWaitlist.mutate(cancellingWaitlist.id)
            setCancellingWaitlist(null)
          }
        }}
        title={cancellingWaitlist?.isOffered ? 'Bỏ lượt nhận suất sân' : 'Hủy giữ chỗ trong hàng đợi'}
        message={
          cancellingWaitlist?.isOffered
            ? 'Bạn có chắc chắn muốn bỏ lượt nhận suất sân này không? Suất sân sẽ được chuyển cho người tiếp theo có điểm uy tín cao nhất trong hàng đợi.'
            : 'Bạn có chắc chắn muốn rời khỏi hàng đợi giữ chỗ của khung giờ này không?'
        }
        confirmText={cancellingWaitlist?.isOffered ? 'Bỏ lượt' : 'Hủy giữ chỗ'}
        cancelText="Giữ lại"
        danger={true}
        busy={cancelWaitlist.isPending}
      />

      <ComplaintRespondModal complaintId={respondingId} role="customer" onClose={() => setRespondingId(null)} />

      <Modal
        isOpen={!!complaintBooking}
        onClose={() => setComplaintBooking(null)}
        title={`Khiếu nại / Phản ánh đơn #BK-${complaintBooking?.id}`}
      >
        {complaintBooking && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              complaintMutation.mutate({
                bookingId: complaintBooking.id,
                category: complaintCategory,
                content: complaintContent,
              })
            }}
            style={{ maxWidth: 480 }}
          >
            <div style={{ padding: 12, background: 'var(--surface)', borderRadius: 8, marginBottom: 14, fontSize: 13 }}>
              <div>Sân: <strong>{complaintBooking.field?.name}</strong></div>
              <div>Ngày chơi: <strong>{formatDate(complaintBooking.booking_date)}</strong> ({complaintBooking.time_slot?.start_time} – {complaintBooking.time_slot?.end_time})</div>
              {complaintBooking.refund && (
                <div style={{ marginTop: 4, color: 'var(--pitch)', fontWeight: 600 }}>
                  Tiền cọc cần hoàn: {formatMoney(complaintBooking.refund.amount)} ({complaintBooking.refund.status === 'pending' ? 'Chưa nhận' : 'Đã hoàn'})
                </div>
              )}
            </div>

            <div className="form-stack">
              <label>
                <span>Loại khiếu nại / Vấn đề gặp phải</span>
                <select
                  value={complaintCategory}
                  onChange={(e) => setComplaintCategory(e.target.value)}
                >
                  <option value="refund_pending">Chưa nhận được tiền hoàn cọc</option>
                  <option value="field_mismatch">Sân không đúng thực tế / mô tả sai</option>
                  <option value="owner_cancelled">Chủ sân tự ý huỷ sân hoặc không đón tiếp</option>
                  <option value="bad_behavior">Thái độ phục vụ của nhân viên / chủ sân</option>
                  {complaintBooking.no_show && <option value="wrong_no_show">Tôi đã đến sân nhưng bị ghi nhận vắng mặt</option>}
                  <option value="other">Vấn đề khác</option>
                </select>
              </label>

              <label>
                <span>Nội dung chi tiết (tối thiểu 5 ký tự)</span>
                <textarea
                  required
                  rows={4}
                  value={complaintContent}
                  onChange={(e) => setComplaintContent(e.target.value)}
                  placeholder="Mô tả cụ thể sự việc để Ban quản trị đối soát với chủ sân..."
                />
              </label>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 12 }}>
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setComplaintBooking(null)}
                >
                  Hủy
                </button>
                <button
                  type="submit"
                  className="button button--brand"
                  disabled={complaintMutation.isPending || complaintContent.trim().length < 5}
                >
                  {complaintMutation.isPending ? 'Đang gửi...' : 'Gửi khiếu nại'}
                </button>
              </div>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}

export function CustomerProfilePage() {
  const { user } = useAuth()
  const client = useQueryClient()
  const toast = useToast()
  const profile = useQuery({
    queryKey: ['customer', 'profile'],
    queryFn: async () => (await api.get<ApiEnvelope<User>>('/customer/profile')).data.data,
  })
  const update = useMutation({
    mutationFn: async (payload: FormData) =>
      api.post('/customer/profile', withMethodOverride(payload, 'PUT')),
    onSuccess: () => {
      toast.success('Đã lưu hồ sơ. Nếu đổi email, hãy kiểm tra hộp thư mới để xác thực.')
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu hồ sơ')
    },
  })
  const password = useMutation({
    mutationFn: async (payload: Record<string, string>) =>
      api.put('/customer/profile/password', payload),
    onSuccess: () => {
      toast.success(hasPassword ? 'Đã đổi mật khẩu thành công.' : 'Đã tạo mật khẩu đăng nhập.')
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể cập nhật mật khẩu')
    },
  })
  const hasPassword = user?.has_password !== false
  const trustScore = profile.data?.trust_score ?? user?.trust_score ?? 100

  return (
    <>
      <PageTitle
        icon={<CircleUserRound />}
        kicker="Tài khoản"
        title="Hồ sơ cá nhân & Độ uy tín"
        copy="Cập nhật thông tin liên hệ, bảo mật và theo dõi Điểm Uy Tín xếp hàng."
      />

      {/* Trust Score Banner Card */}
      <div className="trust-score-card">
        <div className="trust-score-card__header">
          <div className="trust-score-badge">
            <div className="trust-icon-wrap">
              <ShieldCheck size={28} />
            </div>
            <div>
              <span className="trust-score-label">Điểm Uy Tín của bạn</span>
              <div className="trust-score-value">
                <strong>{trustScore}</strong>
                <span>/ 150 điểm tối đa</span>
              </div>
            </div>
          </div>
          <div className="trust-tier">
            {trustScore >= 120 ? (
              <span className="badge badge--success">⭐ Khách hàng Vàng · Ưu tiên hàng đợi cao</span>
            ) : trustScore >= 90 ? (
              <span className="badge badge--soft">✓ Khách hàng Tiêu chuẩn</span>
            ) : (
              <span className="badge badge--warning">⚠️ Cần cải thiện điểm uy tín</span>
            )}
          </div>
        </div>

        <div className="trust-progress-bar">
          <div
            className="trust-progress-fill"
            style={{
              width: `${Math.min(100, Math.max(5, (trustScore / 150) * 100))}%`,
              background:
                trustScore >= 120
                  ? 'linear-gradient(90deg, #10b981, #059669)'
                  : trustScore >= 90
                    ? 'linear-gradient(90deg, var(--brand), #2563eb)'
                    : 'linear-gradient(90deg, #f59e0b, #ef4444)',
            }}
          />
        </div>

        <div className="trust-rules-grid">
          <div className="trust-rule-item">
            <span className="trust-rule-val plus">+10</span>
            <span>Chủ sân xác nhận bạn đã đến sân</span>
          </div>
          <div className="trust-rule-item">
            <span className="trust-rule-val minus">-15</span>
            <span>Hủy đơn đã thanh toán khi còn dưới 4 giờ trước giờ chơi</span>
          </div>
          <div className="trust-rule-item">
            <span className="trust-rule-val danger">-25</span>
            <span>Vắng mặt (No-show) không đến</span>
          </div>
        </div>

        <p className="trust-note">
          💡 <strong>Quyền lợi:</strong> Khi có người huỷ lịch, hệ thống sẽ ưu tiên nhường suất sân cho
          thành viên có Điểm Uy Tín cao nhất trong hàng đợi Waitlist.
        </p>
      </div>

      <div className="settings-grid">
        <form
          key={profile.data?.id}
          className="panel-form"
          onSubmit={(event) => {
            event.preventDefault()
            update.mutate(new FormData(event.currentTarget))
          }}
        >
          <h2>Thông tin cá nhân</h2>
          <div className="form-grid">
            <label>
              <span>Họ tên</span>
              <input name="name" defaultValue={profile.data?.name} required />
            </label>
            <label>
              <span>Email</span>
              <input name="email" type="email" maxLength={255} defaultValue={profile.data?.email} required />
            </label>
            <label>
              <span>Số điện thoại</span>
              <input name="phone" type="tel" inputMode="numeric" required maxLength={11} pattern="0[0-9]{9,10}" title="Số điện thoại gồm 10–11 chữ số, bắt đầu bằng 0" onInput={(e) => (e.currentTarget.value = digitsOnly(e.currentTarget.value, 11))} defaultValue={profile.data?.phone ?? ''} />
            </label>
            <label>
              <span>Địa chỉ</span>
              <input name="address" defaultValue={profile.data?.address ?? ''} />
            </label>
          </div>
          <label>
            <span>Ảnh đại diện</span>
            <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" />
          </label>
          <button disabled={update.isPending} className="button button--brand">
            {update.isPending ? 'Đang lưu...' : 'Lưu hồ sơ'}
          </button>
        </form>

        <form
          className="panel-form"
          onSubmit={(event) => {
            event.preventDefault()
            const data = new FormData(event.currentTarget)
            password.mutate({
              ...(hasPassword ? { current_password: String(data.get('current_password')) } : {}),
              password: String(data.get('password')),
              password_confirmation: String(data.get('password_confirmation')),
            })
          }}
        >
          <h2>{hasPassword ? 'Đổi mật khẩu' : 'Tạo mật khẩu đăng nhập'}</h2>
          {!hasPassword && (
            <p className="form-help">
              Bạn đang đăng nhập bằng Google. Tạo mật khẩu để có thể đăng nhập bằng email và mật khẩu.
            </p>
          )}
          {hasPassword && (
            <label>
              <span>Mật khẩu hiện tại</span>
              <input name="current_password" type="password" required />
            </label>
          )}
          <label>
            <span>Mật khẩu mới</span>
            <input name="password" type="password" required minLength={8} maxLength={72} />
          </label>
          <label>
            <span>Xác nhận mật khẩu</span>
            <input name="password_confirmation" type="password" required minLength={8} maxLength={72} />
          </label>
          <button disabled={password.isPending} className="button button--dark">
            {password.isPending ? 'Đang xử lý...' : hasPassword ? 'Cập nhật mật khẩu' : 'Tạo mật khẩu'}
          </button>
          <p className="form-help">Đang đăng nhập với {user?.email}</p>
        </form>
      </div>
    </>
  )
}

export function PageTitle({
  icon,
  kicker,
  title,
  copy,
  action,
}: {
  icon?: React.ReactNode
  kicker: string
  title: string
  copy: string
  action?: React.ReactNode
}) {
  return (
    <div className="workspace-title">
      <div className="workspace-title__icon">{icon}</div>
      <div>
        <span>{kicker}</span>
        <h1>{title}</h1>
        <p>{copy}</p>
      </div>
      {action && <div className="workspace-title__action">{action}</div>}
    </div>
  )
}

export function Empty({ text }: { text: string }) {
  return (
    <div className="empty-panel">
      <CalendarDays />
      <h2>Chưa có dữ liệu</h2>
      <p>{text}</p>
    </div>
  )
}

