import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  CalendarDays,
  CheckCircle2,
  CircleUserRound,
  Clock3,
  Copy,
  ListOrdered,
  MapPin,
  QrCode,
  ShieldCheck,
  Sparkles,
  Star,
  Timer,
  XCircle,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import { useAuth } from '../../context/auth-context'
import type { ApiEnvelope, Booking, BookingWaitlist, Paginated, User } from '../../types/api'

const currency = new Intl.NumberFormat('vi-VN')

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
  const [cancellingBooking, setCancellingBooking] = useState<Booking | null>(null)
  const [reviewing, setReviewing] = useState<Booking | null>(null)
  const [activeReviewing, setActiveReviewing] = useState<Booking | null>(null)
  const [qrBooking, setQrBooking] = useState<Booking | null>(null)
  const currentReview = reviewing ?? activeReviewing

  const openReview = (booking: Booking) => {
    setReviewing(booking)
    setActiveReviewing(booking)
  }

  const bookings = useQuery({
    queryKey: ['customer', 'bookings', page],
    queryFn: async () => (await api.get<Paginated<Booking>>(`/customer/bookings?page=${page}`)).data,
  })

  const waitlists = useQuery({
    queryKey: ['customer', 'waitlist'],
    queryFn: async () => (await api.get<Paginated<BookingWaitlist>>('/customer/waitlist')).data,
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
      toast.success('Tuyệt vời! Bạn đã nhận sân thành công. Đơn đặt sân mới đã được kích hoạt.')
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
        copy="Theo dõi đơn đặt sân, nhận mã QR check-in và nhận cơ hội đặt sân khi có người huỷ lịch."
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
                      <h3>{booking.field?.name}</h3>
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      {booking.checked_in_at && (
                        <span className="badge badge--success" title={`Đã check-in lúc ${new Date(booking.checked_in_at).toLocaleTimeString('vi-VN')}`}>
                          <CheckCircle2 size={12} /> Đã check-in (+10đ)
                        </span>
                      )}
                      <StatusBadge status={booking.status} />
                    </div>
                  </div>
                  <div className="booking-meta">
                    <span>
                      <CalendarDays />
                      {new Date(booking.booking_date).toLocaleDateString('vi-VN')}
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
                  <div className="booking-item__footer">
                    <strong>{currency.format(booking.total_price)}đ</strong>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                      {(booking.status === 'confirmed' || booking.status === 'completed') && (
                        <button
                          type="button"
                          className="button button--ghost button--small qr-pass-btn"
                          onClick={() => setQrBooking(booking)}
                        >
                          <QrCode size={14} /> Vé / Mã vào sân
                        </button>
                      )}

                      {booking.can_be_cancelled && (
                        <button
                          className="button button--danger button--small"
                          onClick={() => setCancellingBooking(booking)}
                        >
                          Hủy lịch
                        </button>
                      )}
                      {booking.status === 'completed' && !booking.review && (
                        <button
                          className="button button--brand button--small"
                          onClick={() => openReview(booking)}
                        >
                          <Star size={15} /> Đánh giá
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {!bookings.isPending && !bookings.data?.data.length && (
            <Empty text="Bạn chưa có lịch đặt sân nào." />
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
                      <span className="badge badge--soft">Đang chờ lượt (Ưu tiên: {item.priority_score}đ)</span>
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
                    {new Date(item.booking_date).toLocaleDateString('vi-VN')}
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
                        <XCircle size={16} /> Từ chối nhường lượt
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
            <Empty text="Bạn chưa tham gia hàng đợi giữ chỗ nào. Khi xem chi tiết sân có khung giờ đã kín, hãy bấm 'Đăng ký hàng đợi'." />
          )}
        </section>
      )}

      {/* QR Digital Pass Modal */}
      <Modal
        isOpen={!!qrBooking}
        onClose={() => setQrBooking(null)}
        title="Thẻ vào sân & QR Check-in"
      >
        {qrBooking && (
          <div className="qr-pass-container">
            <div className="qr-pass-card">
              <div className="qr-pass-card__header">
                <div>
                  <span className="qr-pass-tag">SPORTHUB MATCHDAY PASS</span>
                  <h3>{qrBooking.field?.name}</h3>
                </div>
                <div className="qr-pass-code-pill">
                  {qrBooking.checked_in_at ? '✓ CHECKED-IN' : 'VALID'}
                </div>
              </div>

              <div className="qr-pass-body">
                <div className="qr-pass-graphic">
                  {/* Styled QR placeholder graphic */}
                  <div className="qr-code-box">
                    <QrCode size={110} strokeWidth={1.5} />
                  </div>
                  <div className="qr-pass-code-display">
                    <code>{qrBooking.check_in_code ?? `CK-${String(qrBooking.id).padStart(8, '0')}`}</code>
                    <button
                      type="button"
                      className="copy-code-btn"
                      onClick={() => {
                        const code = qrBooking.check_in_code ?? `CK-${String(qrBooking.id).padStart(8, '0')}`
                        navigator.clipboard.writeText(code)
                        toast.success(`Đã sao chép mã check-in: ${code}`)
                      }}
                      title="Sao chép mã"
                    >
                      <Copy size={14} />
                    </button>
                  </div>
                </div>

                <div className="qr-pass-details">
                  <div className="qr-pass-detail-item">
                    <span>Khách hàng</span>
                    <strong>{qrBooking.customer?.name ?? 'Khách đặt sân'}</strong>
                  </div>
                  <div className="qr-pass-detail-item">
                    <span>Ngày đá</span>
                    <strong>{new Date(qrBooking.booking_date).toLocaleDateString('vi-VN')}</strong>
                  </div>
                  <div className="qr-pass-detail-item">
                    <span>Khung giờ</span>
                    <strong>
                      {qrBooking.time_slot?.start_time} – {qrBooking.time_slot?.end_time}
                    </strong>
                  </div>
                  <div className="qr-pass-detail-item">
                    <span>Địa chỉ</span>
                    <strong style={{ fontSize: '0.85rem' }}>{qrBooking.field?.address}</strong>
                  </div>
                </div>
              </div>

              <div className="qr-pass-card__footer">
                {qrBooking.checked_in_at ? (
                  <p className="qr-pass-status success">
                    <CheckCircle2 size={16} /> Bạn đã check-in thành công tại sân vào lúc{' '}
                    {new Date(qrBooking.checked_in_at).toLocaleTimeString('vi-VN')}. (+10 điểm uy tín đã cộng)
                  </p>
                ) : (
                  <p className="qr-pass-status pending">
                    <ShieldCheck size={16} /> Đưa mã này cho chủ sân / nhân viên khi đến sân để check-in và
                    nhận ngay <strong>+10 điểm uy tín</strong>!
                  </p>
                )}
              </div>
            </div>
            <div className="modal__actions" style={{ marginTop: 16 }}>
              <button type="button" className="button button--brand" onClick={() => setQrBooking(null)}>
                Đóng vé
              </button>
            </div>
          </div>
        )}
      </Modal>

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
            ? new Date(cancellingBooking.booking_date).toLocaleDateString('vi-VN')
            : ''
        } (${cancellingBooking?.time_slot?.start_time} - ${cancellingBooking?.time_slot?.end_time})? ${
          cancellingBooking?.booking_date === new Date().toISOString().slice(0, 10)
            ? 'Lưu ý: Hủy sát giờ có thể trừ 15 điểm uy tín của bạn.'
            : ''
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
            <span>Check-in QR khi đến sân</span>
          </div>
          <div className="trust-rule-item">
            <span className="trust-rule-val minus">-15</span>
            <span>Hủy lịch sát giờ (&lt;2 tiếng)</span>
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
            <input name="avatar" type="file" accept="image/*" />
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

