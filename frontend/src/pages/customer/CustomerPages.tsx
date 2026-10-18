import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertCircle,
  CalendarDays,
  CheckCircle2,
  CircleUserRound,
  Clock3,
  ListOrdered,
  MapPin,
  ShieldCheck,
  Sparkles,
  Star,
  Timer,
  XCircle,
  Zap,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import { useAuth } from '../../context/auth-context'
import type { ApiEnvelope, Booking, BookingWaitlist, Paginated, User } from '../../types/api'
import { getLocalTodayString } from '../../utils/date'
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

export function CustomerBookingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [activeTab, setActiveTab] = useState<'bookings' | 'waitlist'>('bookings')
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const [cancellingBooking, setCancellingBooking] = useState<Booking | null>(null)
  const [reviewing, setReviewing] = useState<Booking | null>(null)
  const [activeReviewing, setActiveReviewing] = useState<Booking | null>(null)
  const [complaintBooking, setComplaintBooking] = useState<Booking | null>(null)
  const [complaintCategory, setComplaintCategory] = useState('refund_pending')
  const [complaintContent, setComplaintContent] = useState('')
  const currentReview = reviewing ?? activeReviewing

  const openReview = (booking: Booking) => {
    setReviewing(booking)
    setActiveReviewing(booking)
  }

  const openComplaint = (booking: Booking) => {
    setComplaintBooking(booking)
    setComplaintCategory(booking.refund?.status === 'pending' ? 'refund_pending' : 'field_mismatch')
    setComplaintContent('')
  }

  const bookings = useQuery({
    queryKey: ['customer', 'bookings', status, page],
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
    mutationFn: async (booking: Booking) => api.patch(`/customer/bookings/${booking.id}/cancel`),
    onSuccess: (_, booking) => {
      toast.success(`Đã hủy lịch đặt sân #BK-${booking.id}.`)
      setCancellingBooking(null)
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể hủy lịch')
    },
  })

  const acceptWaitlist = useMutation({
    mutationFn: async (waitlistId: number) => api.post(`/customer/waitlist/${waitlistId}/accept`),
    onSuccess: () => {
      toast.success('Bạn đã nhận được suất sân. Đơn đặt sân mới đang chờ chủ sân duyệt, sau đó bạn chuyển khoản để giữ sân.')
      void client.invalidateQueries({ queryKey: ['customer', 'waitlist'] })
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      setActiveTab('bookings')
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
      rating: Number(data.get('rating')),
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
              <option value="pending">Chờ duyệt / chờ thanh toán</option>
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
                  {booking.status === 'pending' && !booking.payment?.status && (
                    <p className="form-help">
                      Đang chờ chủ sân duyệt — bạn có thể huỷ bất cứ lúc nào, không bị trừ điểm. Sau khi được duyệt bạn sẽ
                      chuyển khoản cọc hoặc trả hết để giữ sân. Đơn tự huỷ nếu chủ sân không phản hồi sau 24 giờ.
                    </p>
                  )}
                  {booking.status === 'pending' && booking.payment?.status === 'awaiting' && (
                    <p className="form-help">
                      Chủ sân đã duyệt. Hãy chuyển khoản
                      {booking.payment.due_at &&
                        ` trước ${formatDateTime(booking.payment.due_at)}`}
                      , quá hạn đơn sẽ tự huỷ.
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
                    <p className="form-help">
                      {booking.can_be_cancelled ? (
                        booking.cancel_penalty ? (
                          <span style={{ color: '#ef4444' }}>
                            Chủ sân đã xác nhận. Đã vào khung huỷ muộn (dưới 4 giờ trước giờ chơi): bạn vẫn có thể huỷ nhưng sẽ mất cọc và bị trừ 15 điểm uy tín.
                          </span>
                        ) : (
                          <span>
                            Chủ sân đã xác nhận. Bạn có thể huỷ miễn phí (hoàn cọc, không trừ điểm) trước {booking.cancel_until ? formatDateTime(booking.cancel_until) : 'giờ chơi'}.
                          </span>
                        )
                      ) : (
                        <span>Chủ sân đã xác nhận và đã đến/quá giờ chơi nên không thể tự huỷ đơn.</span>
                      )}{' '}
                      Mã đơn để đối chiếu khi đến sân: <strong>#BK-{String(booking.id).padStart(5, '0')}</strong>.
                      {booking.payment?.status === 'paid' &&
                        ` Đã chuyển ${formatMoney(booking.payment.amount ?? 0)}${booking.payment.option === 'deposit' ? `, còn ${formatMoney(booking.total_price - (booking.payment.amount ?? 0))} trả tại sân` : ''}.`}
                    </p>
                  )}
                  {(booking.status === 'cancelled' || booking.status === 'rejected') && booking.cancel_reason && (
                    <p className="form-help">Lý do: {booking.cancel_reason}</p>
                  )}
                  {booking.refund?.status === 'pending' && (
                    <div className="notice notice--warn" style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      <strong>Khoản hoàn tiền:</strong> Đơn đã ghi nhận hoàn cọc {formatMoney(booking.refund.amount)}. Nếu sau 24h chưa nhận được chuyển khoản, bạn hãy bấm &quot;Khiếu nại&quot; bên dưới để ban quản trị đối soát hỗ trợ.
                    </div>
                  )}
                  {booking.refund?.status === 'done' && (
                    <div className="notice notice--success" style={{ marginTop: 8, fontSize: 13, lineHeight: 1.5 }}>
                      Đã hoàn trả {formatMoney(booking.refund.amount)} tiền cọc{booking.refund.refunded_at ? ` lúc ${formatDateTime(booking.refund.refunded_at)}` : ''}.
                    </div>
                  )}
                  <div className="booking-item__footer">
                    <strong>{formatMoney(booking.total_price)}</strong>

                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                      {booking.status === 'pending' && booking.payment?.status && (
                        <Link to={`/bookings/${booking.id}/payment`} className="button button--brand button--small">
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
                      <button
                        type="button"
                        className="button button--ghost button--small"
                        onClick={() => openComplaint(booking)}
                        title="Khiếu nại hoặc phản ánh về đơn đặt sân này"
                      >
                        <AlertCircle size={14} /> Khiếu nại
                      </button>
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
                        onClick={() => cancelWaitlist.mutate(item.id)}
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
                      onClick={() => cancelWaitlist.mutate(item.id)}
                    >
                      Hủy giữ chỗ
                    </button>
                  )}
                </div>
              </article>
            ))}
          </div>

          {!waitlists.isPending && !waitlists.data?.data.length && (
            <Empty text="Bạn chưa tham gia hàng đợi giữ chỗ nào. Khi xem chi tiết sân có khung giờ đã kín, hãy bấm vào khung giờ đó để tham gia hàng đợi." />
          )}
        </section>
      )}

      <Modal isOpen={!!reviewing} onClose={() => setReviewing(null)} asForm onSubmit={submitReview}>
        <h2>Đánh giá {currentReview?.field?.name}</h2>
        <label>
          <span>Số sao</span>
          <select name="rating" defaultValue="5">
            {[5, 4, 3, 2, 1].map((n) => (
              <option value={n} key={n}>
                {n} sao
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>Nội dung</span>
          <textarea
            name="comment"
            required
            rows={3}
            placeholder="Chia sẻ cảm nhận về chất lượng sân, dịch vụ..."
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

      <ConfirmModal
        isOpen={!!cancellingBooking}
        title="Xác nhận hủy đặt sân"
        message={`Bạn có chắc muốn hủy lịch đặt sân "${cancellingBooking?.field?.name}" ngày ${
          cancellingBooking?.booking_date
            ? formatDate(cancellingBooking.booking_date)
            : ''
        } (${cancellingBooking?.time_slot?.start_time} - ${cancellingBooking?.time_slot?.end_time})? ${
          cancellingBooking?.cancel_penalty
            ? 'Cảnh báo: Đang trong khung huỷ muộn (dưới 4 giờ trước giờ chơi), huỷ lúc này bạn sẽ KHÔNG được hoàn cọc và bị trừ 15 điểm uy tín.'
            : 'Huỷ lúc này được hoàn tiền cọc và không bị trừ điểm uy tín.'
        }`}
        confirmText="Hủy lịch đặt"
        cancelText="Giữ lại"
        danger
        busy={cancel.isPending}
        onClose={() => setCancellingBooking(null)}
        onConfirm={() => {
          if (cancellingBooking) cancel.mutate(cancellingBooking)
        }}
      />

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
            <span>Hủy đơn đã duyệt khi còn dưới 4 giờ trước giờ chơi</span>
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
              <input name="email" defaultValue={profile.data?.email} required />
            </label>
            <label>
              <span>Số điện thoại</span>
              <input name="phone" defaultValue={profile.data?.phone ?? ''} />
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
            <input name="password" type="password" required />
          </label>
          <label>
            <span>Xác nhận mật khẩu</span>
            <input name="password_confirmation" type="password" required />
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

