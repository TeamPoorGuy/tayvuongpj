import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertTriangle,
  CalendarDays,
  Check,
  Clock3,
  CornerDownRight,
  Flame,
  Info,
  MapPin,
  Phone,
  ShieldCheck,
  Sparkles,
  Star,
  Sun,
  Sunset,
  Moon,
  ArrowRight,
  BellRing,
  Zap,
} from 'lucide-react'
import { Link, useLocation, useParams, useSearchParams } from 'react-router'
import { useState, useMemo } from 'react'
import axios from 'axios'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'
import { useToast } from '../components/Toast'
import { FieldMap } from '../components/FieldMap'
import { addDays, formatDay, getLocalTodayString } from '../utils/date'
import type { ApiEnvelope, SlotSuggestion, SportsField, TimeSlot } from '../types/api'
import { Avatar } from '../components/Avatar'
import { formatMoney, formatDate } from '../utils/format'
import { BookingConfirmModal } from '../components/BookingConfirmModal'

export function FieldDetailPage() {

  const { slug = '' } = useParams()
  const { user } = useAuth()
  const { success, error } = useToast()
  const queryClient = useQueryClient()

  const todayStr = useMemo(() => getLocalTodayString(), [])
  const [searchParams] = useSearchParams()
  // Cho phép mở từ trang tìm đối với ngày đã chọn (?date=YYYY-MM-DD), bỏ qua ngày quá khứ.
  const dateParam = searchParams.get('date')
  const [date, setDate] = useState(
    dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam) && dateParam >= todayStr ? dateParam : todayStr,
  )
  const [slotId, setSlotId] = useState<number | null>(null)
  // Mở từ trang tìm đối / gợi ý (?start=HH:MM): chọn sẵn khung giờ đó nếu còn trống.
  const [startHint, setStartHint] = useState(searchParams.get('start'))
  const location = useLocation()
  const loginState = { from: location.pathname + location.search }
  const [notes, setNotes] = useState('')
  const [activeImg, setActiveImg] = useState(0)
  const [periodFilter, setPeriodFilter] = useState<'all' | 'morning' | 'afternoon' | 'evening'>('all')
  const [selectedBookedSlot, setSelectedBookedSlot] = useState<TimeSlot | null>(null)
  const [showConfirmModal, setShowConfirmModal] = useState(false)

  const field = useQuery({
    queryKey: ['field', slug],
    queryFn: async () => (await api.get<ApiEnvelope<SportsField>>(`/fields/${slug}`)).data.data,
  })

  const [recurringConflicts, setRecurringConflicts] = useState<Array<{ date: string; reason: string }> | null>(null)
  const [pendingRecurringConfig, setPendingRecurringConfig] = useState<{ isRecurring: boolean; weeks: number } | null>(null)

  const slots = useQuery({
    queryKey: ['slots', field.data?.id, date],
    enabled: !!field.data,
    queryFn: async () =>
      (await api.get<ApiEnvelope<{ date: string; is_closed?: boolean; closure_reason?: string; slots: TimeSlot[] }>>(
        `/fields/${field.data?.id}/available-slots?date=${date}`,
      )).data.data,
  })

  const slotsList = useMemo(() => slots.data?.slots ?? [], [slots.data?.slots])
  const isDateClosed = !!slots.data?.is_closed
  const closureReason = slots.data?.closure_reason

  const suggestions = useQuery({
    queryKey: ['suggestions', slug, selectedBookedSlot?.id, date],
    enabled: !!selectedBookedSlot && !!slug,
    queryFn: async () =>
      (await api.get<ApiEnvelope<SlotSuggestion[]>>(
        `/fields/${slug}/suggestions?slot_id=${selectedBookedSlot?.id}&date=${date}`,
      )).data.data,
  })

  const joinWaitlist = useMutation({
    mutationFn: async (slot: TimeSlot) =>
      api.post('/customer/waitlist', {
        sports_field_id: field.data?.id,
        time_slot_id: slot.id,
        booking_date: date,
      }),
    onSuccess: () => {
      success(
        'Đã tham gia hàng đợi thành công! Hệ thống sẽ ưu tiên gửi email giữ chỗ cho bạn ngay khi có người hủy.',
        'Hàng đợi thông minh (Waitlist)',
      )
      setSelectedBookedSlot(null)
    },
    onError: (err) => {
      error(errorMessage(err), 'Không thể vào hàng đợi')
    },
  })

  const booking = useMutation({
    mutationFn: async ({ isRecurring, weeks, skipConflicts }: { isRecurring: boolean; weeks: number; skipConflicts?: boolean }) => {
      if (isRecurring) {
        return (await api.post('/customer/bookings/recurring', {
          sports_field_id: field.data?.id,
          time_slot_id: selectedSlot?.id,
          start_date: date,
          weeks,
          notes,
          skip_conflicts: skipConflicts ?? false,
        })).data
      }
      return (await api.post('/customer/bookings', {
        sports_field_id: field.data?.id,
        time_slot_id: selectedSlot?.id,
        booking_date: date,
        notes,
      })).data
    },
    onSuccess: (_, vars) => {
      setShowConfirmModal(false)
      setRecurringConflicts(null)
      setPendingRecurringConfig(null)
      pickSlot(null)
      setNotes('')
      const msg = vars.isRecurring
        ? `Đã gửi yêu cầu đặt cố định ${vars.weeks} buổi. Khi chủ sân duyệt, bạn sẽ thanh toán cọc theo từng tuần.`
        : 'Yêu cầu đặt sân đã được gửi. Khi chủ sân duyệt, bạn sẽ chuyển khoản cọc hoặc trả hết để giữ sân.'
      success(msg, 'Đã gửi yêu cầu')
      void queryClient.invalidateQueries({ queryKey: ['slots'] })
      void queryClient.invalidateQueries({ queryKey: ['customer', 'bookings'] })
    },
    onError: (err: any) => {
      const data = err?.response?.data
      if (data?.has_conflicts && data?.conflicts) {
        setRecurringConflicts(data.conflicts)
      } else {
        error(errorMessage(err), 'Không thể đặt sân')
      }
    },
  })

  useReveal([field.data])

  const item = field.data
  const images = useMemo(() => {
    if (!item) return []
    if (item.images?.length) return item.images
    if (item.primary_image) return [{ id: 0, url: item.primary_image, is_primary: true }]
    return []
  }, [item])

  const selectedSlot =
    slotsList.find((s) => s.id === slotId) ??
    (startHint
      ? slotsList.find((s) => s.start_time === startHint && !s.is_booked && !s.is_past && !s.is_held)
      : null) ??
    null

  const pickSlot = (id: number | null) => {
    setStartHint(null)
    setSlotId(id)
  }

  const changeDate = (next: string) => {
    setDate(next)
    pickSlot(null)
    setSelectedBookedSlot(null)
  }

  const weekDays = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(todayStr, i)), [todayStr])

  // Calculate duration in hours between HH:mm and HH:mm and respect slot-specific price
  const calculatedPrice = useMemo(() => {
    if (!selectedSlot || !item) return null
    const [sh, sm] = selectedSlot.start_time.split(':').map(Number)
    const [eh, em] = selectedSlot.end_time.split(':').map(Number)
    const startMinutes = sh * 60 + sm
    const endMinutes = eh * 60 + em
    const durationHours = Math.max(0.5, (endMinutes - startMinutes) / 60)
    // Sửa lỗi: ưu tiên giá riêng của khung giờ slot.price_per_hour nếu có
    const effectivePricePerHour = selectedSlot.price_per_hour ?? item.price_per_hour

    return {
      durationHours,
      durationMinutes: endMinutes - startMinutes,
      effectivePricePerHour,
      total: Math.round(effectivePricePerHour * durationHours),
    }
  }, [selectedSlot, item])

  // Categorize slots by day period
  const filteredSlots = useMemo(() => {
    return slotsList.filter((slot) => {
      const hour = parseInt(slot.start_time.slice(0, 2), 10)
      if (periodFilter === 'morning') return hour < 12
      if (periodFilter === 'afternoon') return hour >= 12 && hour < 17
      if (periodFilter === 'evening') return hour >= 17
      return true
    })
  }, [slotsList, periodFilter])

  const allSlots = slotsList
  const availableCount = useMemo(
    () => allSlots.filter((s) => !s.is_booked && !s.is_past && !s.is_held).length,
    [allSlots],
  )
  const isDateFullyBooked = !slots.isPending && allSlots.length > 0 && availableCount === 0

  const tomorrowDateStr = addDays(date, 1)

  const scrollToBooking = () => {
    const el = document.getElementById('booking-card-section')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  if (field.isPending) {
    return (
      <div className="shell page-section">
        <div className="skeleton-detail">
          <div className="skeleton-line skeleton-line--title skeleton-shimmer" style={{ width: '40%', height: 38 }} />
          <div className="skeleton-card__img skeleton-shimmer" style={{ height: 380, borderRadius: 24, margin: '24px 0' }} />
        </div>
      </div>
    )
  }

  if (field.isError) {
    const isNotFound = axios.isAxiosError(field.error) && field.error.response?.status === 404
    if (!isNotFound) {
      return (
        <div className="shell page-section">
          <div className="empty-page">
            <h1>Lỗi tải thông tin sân</h1>
            <p>{errorMessage(field.error) || 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại kết nối mạng.'}</p>
            <button
              type="button"
              className="button button--brand"
              onClick={() => void field.refetch()}
              style={{ marginTop: 16 }}
            >
              Thử lại
            </button>
          </div>
        </div>
      )
    }
    return (
      <div className="shell page-section">
        <div className="empty-page">
          <h1>Không tìm thấy sân</h1>
          <p>Sân bạn đang tìm kiếm không tồn tại hoặc đã tạm dừng hoạt động.</p>
          <Link to="/fields" className="button button--brand" style={{ marginTop: 16 }}>
            Khám phá sân khác
          </Link>
        </div>
      </div>
    )
  }

  if (!field.data || !item) {
    return (
      <div className="shell page-section">
        <div className="empty-page">
          <h1>Không tìm thấy sân</h1>
          <p>Sân bạn đang tìm kiếm có thể đã tạm dừng hoặc đổi tên.</p>
          <Link to="/fields" className="button button--brand" style={{ marginTop: 16 }}>
            Khám phá sân khác
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="shell page-section detail-page">
      {/* Detail Header */}
      <div className="detail-head">
        <div>
          <span className="kicker">
            <Sparkles size={14} /> {item.field_type?.category?.name} · {item.field_type?.name}
          </span>
          <h1>{item.name}</h1>
          <p>
            <MapPin size={16} /> {item.address}
          </p>
        </div>
        <div className="detail-head__meta">
          <div className="detail-rating">
            <Star fill="currentColor" />
            <strong>{item.average_rating ? item.average_rating.toFixed(1) : 'Mới'}</strong>
            <span>({item.reviews_count} đánh giá)</span>
          </div>
          {(item.bookings_count ?? 0) > 5 && (
            <span className="badge badge--hot">
              <Flame size={12} /> {item.bookings_count} lượt đặt
            </span>
          )}
        </div>
      </div>

      {/* Thông báo lịch nghỉ / đóng sân nếu có */}
      {(() => {
        const upcoming = (item.closures ?? []).filter((c) => c.end_date >= todayStr)
        if (upcoming.length === 0) return null
        return (
          <div className="notice notice--warn" style={{ marginBottom: 20 }}>
            <AlertTriangle size={18} />
            <div>
              <strong>Thông báo lịch nghỉ & bảo dưỡng sân:</strong>
              <ul style={{ margin: '4px 0 0 16px', padding: 0, fontSize: 13 }}>
                {upcoming.map((c) => (
                  <li key={c.id}>
                    Từ <strong>{formatDate(c.start_date)}</strong> đến <strong>{formatDate(c.end_date)}</strong>
                    {c.start_time && c.end_time ? ` (khoá ${c.start_time}–${c.end_time})` : ''}: {c.reason}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )
      })()}

      {/* Gallery Showcase */}
      <div className="gallery-showcase">
        <div className="gallery-showcase__main">
          {images.length > 0 ? (
            <img
              src={images[activeImg]?.url ?? images[0]?.url}
              alt={`${item.name} - ảnh ${activeImg + 1}`}
            />
          ) : (
            <div className="gallery-showcase__placeholder">
              <Sparkles size={48} />
              <span>Chưa có hình ảnh sân</span>
            </div>
          )}
        </div>
        {images.length > 1 && (
          <div className="gallery-showcase__thumbs">
            {images.map((img, idx) => (
              <button
                key={img.id}
                type="button"
                className={`gallery-thumb ${activeImg === idx ? 'gallery-thumb--active' : ''}`}
                onClick={() => setActiveImg(idx)}
                aria-label={`Xem ảnh ${idx + 1}`}
              >
                <img src={img.url} alt="" />
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Main Grid: Content + Sticky Booking Card */}
      <div className="detail-grid">
        <div className="detail-main-col">
          {/* Field Description */}
          <section className="content-card reveal">
            <h2>Thông tin sân</h2>
            <p className="detail-desc">{item.description || 'Chủ sân chưa cập nhật mô tả chi tiết.'}</p>
            <div className="feature-list">
              <span>
                <ShieldCheck /> Sân đã kiểm duyệt
              </span>
              <span>
                <Clock3 /> Khung giờ minh bạch
              </span>
              <span>
                <Check /> Xác nhận bởi chủ sân
              </span>
              <span>
                <Info /> Chờ duyệt: hủy bất cứ lúc nào · Đã xác nhận: hủy được trong 2 giờ, hủy khi còn dưới 4 giờ trước giờ chơi bị trừ điểm uy tín
              </span>
            </div>
          </section>

          {/* Owner Info Card */}
          {item.owner && (
            <section className="content-card reveal">
              <h2>Cơ sở quản lý</h2>
              <div className="owner-info-strip">
                <div className="avatar">
                  {item.owner.business?.business_name?.slice(0, 1) ?? item.owner.name.slice(0, 1)}
                </div>
                <div>
                  <strong>{item.owner.business?.business_name ?? item.owner.name}</strong>
                  {item.owner.business?.business_phone && (
                    <small>
                      <Phone size={13} /> {item.owner.business.business_phone}
                    </small>
                  )}
                </div>
              </div>
            </section>
          )}

          {/* Leaflet Map & Directions */}
          <FieldMap field={item} />

          {/* Reviews List */}
          <section className="content-card reveal">
            <div className="section-head-sm">
              <h2>Đánh giá từ cộng đồng</h2>
              <span>{item.reviews_count} nhận xét</span>
            </div>
            {item.reviews && item.reviews.filter((r) => r.is_visible).length > 0 ? (
              <div className="review-stack">
                {item.reviews
                  .filter((r) => r.is_visible)
                  .map((review) => (
                    <article className="review" key={review.id}>
                      <div className="review__header">
                        <Avatar name={review.user?.name} src={review.user?.avatar} className="avatar avatar--sm" />
                        <div>
                          <strong>{review.user?.name}</strong>
                          <span className="review__stars">{'★'.repeat(review.rating)}</span>
                        </div>
                      </div>
                      <p>{review.comment}</p>
                      {review.owner_reply && (
                        <div
                          style={{
                            marginTop: 10,
                            padding: '10px 14px',
                            background: 'var(--surface-sunken, #f8fafc)',
                            borderRadius: 8,
                            borderLeft: '3px solid var(--pitch, #0b3025)',
                          }}
                        >
                          <div
                            style={{
                              display: 'flex',
                              alignItems: 'center',
                              gap: 6,
                              fontSize: 12,
                              fontWeight: 700,
                              color: 'var(--pitch, #0b3025)',
                              marginBottom: 4,
                            }}
                          >
                            <CornerDownRight size={14} />
                            <span>Phản hồi từ chủ sân</span>
                            {review.owner_replied_at && (
                              <span style={{ fontWeight: 400, color: 'var(--text-muted, #64748b)' }}>
                                • {formatDate(review.owner_replied_at)}
                              </span>
                            )}
                          </div>
                          <p style={{ margin: 0, fontSize: 13, color: 'var(--text)' }}>
                            {review.owner_reply}
                          </p>
                        </div>
                      )}
                    </article>
                  ))}
              </div>
            ) : (
              <p className="empty-inline">Chưa có đánh giá nào. Hãy là người đầu tiên trải nghiệm sân!</p>
            )}
          </section>
        </div>

        {/* Booking Sticky Card */}
        <aside className="booking-card" id="booking-card-section">
          <div className="booking-price">
            <span>Giá thuê tiêu chuẩn</span>
            <strong>
              {formatMoney(item.price_per_hour)} <small>/ giờ</small>
            </strong>
          </div>

          <label className="booking-label">
            <span>Ngày vào sân</span>
            <input
              type="date"
              min={todayStr}
              value={date}
              onChange={(event) => event.target.value && changeDate(event.target.value)}
            />
          </label>
          <div className="day-strip" role="group" aria-label="Chọn nhanh ngày">
            {weekDays.map((day) => {
              const [label, rest] = formatDay(day).split(' · ')
              return (
                <button
                  type="button"
                  key={day}
                  className={day === date ? 'active' : ''}
                  aria-pressed={day === date}
                  onClick={() => changeDate(day)}
                >
                  <strong>{label}</strong>
                  <small>{rest.slice(0, 5)}</small>
                </button>
              )
            })}
          </div>

          {/* Time slot period filter */}
          <div className="slot-picker-wrap">
            <div className="slot-picker-head">
              <div className="slot-picker-title-col">
                <span className="field-label">Khung giờ hoạt động</span>
                {!slots.isPending && allSlots.length > 0 && (
                  <span className="slot-availability-pill">
                    Còn <strong>{availableCount}</strong>/{allSlots.length} khung giờ
                  </span>
                )}
              </div>
              <div className="period-tabs">
                <button
                  type="button"
                  className={periodFilter === 'all' ? 'active' : ''}
                  onClick={() => setPeriodFilter('all')}
                  title="Tất cả giờ"
                >
                  Tất cả
                </button>
                <button
                  type="button"
                  className={periodFilter === 'morning' ? 'active' : ''}
                  onClick={() => setPeriodFilter('morning')}
                  title="Sáng (<12h)"
                >
                  <Sun size={12} /> Sáng
                </button>
                <button
                  type="button"
                  className={periodFilter === 'afternoon' ? 'active' : ''}
                  onClick={() => setPeriodFilter('afternoon')}
                  title="Chiều (12h-17h)"
                >
                  <Sunset size={12} /> Chiều
                </button>
                <button
                  type="button"
                  className={periodFilter === 'evening' ? 'active' : ''}
                  onClick={() => setPeriodFilter('evening')}
                  title="Tối (>17h)"
                >
                  <Moon size={12} /> Tối
                </button>
              </div>
            </div>

            {slots.isPending && (
              <div className="slot-loading">
                <span className="skeleton-shimmer" style={{ height: 38, borderRadius: 10 }} />
                <span className="skeleton-shimmer" style={{ height: 38, borderRadius: 10 }} />
                <span className="skeleton-shimmer" style={{ height: 38, borderRadius: 10 }} />
              </div>
            )}

            {/* Notice when date is closed/maintenance */}
            {isDateClosed && (
              <div className="notice notice--warn" style={{ marginBottom: 16 }}>
                <AlertTriangle size={18} />
                <div>
                  <strong>Sân tạm đóng cửa vào ngày này:</strong> {closureReason || 'Bảo trì hoặc nghỉ lễ theo lịch của chủ sân.'}
                </div>
              </div>
            )}

            {/* Smart notice when date is fully booked */}
            {!isDateClosed && isDateFullyBooked && (
              <div className="fully-booked-notice">
                <div className="fully-booked-head">
                  <Info size={15} />
                  <strong>{formatDay(date)} đã kín tất cả khung giờ!</strong>
                </div>
                <p>
                  Bạn có thể bấm vào các khung giờ đã đặt bên dưới để tham gia <strong>Hàng đợi (Waitlist)</strong>, hoặc chuyển sang ngày kế tiếp để xem khung giờ trống.
                </p>
                <button
                  type="button"
                  className="button button--brand button--small"
                  onClick={() => changeDate(tomorrowDateStr)}
                >
                  <CalendarDays size={13} /> Xem ngày mai ({formatDay(tomorrowDateStr)})
                </button>
              </div>
            )}

            <div className="slot-grid">
              {filteredSlots.map((slot) => {
                const isSelected = selectedSlot?.id === slot.id
                const isBookedSelected = selectedBookedSlot?.id === slot.id
                const isAvailable = !slot.is_booked && !slot.is_past && !slot.is_held
                return (
                  <button
                    type="button"
                    disabled={slot.is_past || slot.is_held}
                    aria-disabled={slot.is_booked || slot.is_past || slot.is_held}
                    className={`slot-chip ${isSelected ? 'selected' : ''} ${
                      slot.is_booked ? 'slot-chip--booked' : ''
                    } ${isBookedSelected ? 'slot-chip--booked-active' : ''} ${
                      slot.is_past ? 'slot-chip--past' : ''
                    }`}
                    onClick={() => {
                      if (slot.is_booked) {
                        pickSlot(null)
                        setSelectedBookedSlot(slot)
                      } else if (!slot.is_past && !slot.is_held) {
                        setSelectedBookedSlot(null)
                        pickSlot(slot.id)
                      }
                    }}
                    key={slot.id}
                    title={
                      slot.is_past
                        ? 'Khung giờ này đã trôi qua'
                        : slot.is_held
                          ? 'Đang giữ chỗ 15 phút cho người trong hàng đợi'
                          : slot.is_booked
                          ? 'Khung giờ đã kín — Bấm để vào hàng đợi Waitlist hoặc xem gợi ý'
                          : 'Khung giờ còn trống — Bấm để chọn đặt'
                    }
                  >
                    <span>
                      {slot.start_time} – {slot.end_time}
                    </span>
                    {slot.price_per_hour && (
                      <small className="slot-custom-price">
                        {formatMoney(slot.price_per_hour)}/h
                      </small>
                    )}
                    {slot.is_booked && <small className="slot-badge--booked">Đã đặt · Chờ</small>}
                    {slot.is_past && <small className="slot-badge--past">Đã qua giờ</small>}
                    {slot.is_held && !slot.is_past && <small className="slot-badge--booked">Đang giữ chỗ</small>}
                    {isAvailable && <small className="slot-badge--avail">Còn trống</small>}
                  </button>
                )
              })}
            </div>

            {filteredSlots.length === 0 && !slots.isPending && (
              <p className="no-slot-notice">Không có khung giờ nào phù hợp bộ lọc.</p>
            )}

            {/* Phase 4: Smart Waitlist & Suggestion Block */}
            {selectedBookedSlot && (
              <div className="waitlist-card">
                <div className="waitlist-card__head">
                  <div className="waitlist-card__title">
                    <Zap size={16} />
                    <strong>Khung giờ {selectedBookedSlot.start_time} – {selectedBookedSlot.end_time} đã kín</strong>
                  </div>
                  <button
                    type="button"
                    className="button button--ghost button--small"
                    onClick={() => setSelectedBookedSlot(null)}
                  >
                    Đóng
                  </button>
                </div>
                <p className="waitlist-card__desc">
                  Bạn có thể tham gia <strong>Hàng đợi thông minh (Smart Waitlist)</strong> để được ưu tiên nhận giữ chỗ ngay lập tức khi có người hủy hoặc đổi giờ!
                </p>
                {user ? (
                  <button
                    type="button"
                    className="button button--brand button--small waitlist-btn"
                    onClick={() => joinWaitlist.mutate(selectedBookedSlot)}
                    disabled={joinWaitlist.isPending}
                  >
                    <BellRing size={14} /> {joinWaitlist.isPending ? 'Đang đăng ký...' : 'Vào hàng đợi'}
                  </button>
                ) : (
                  <Link to="/login" state={loginState} className="button button--brand button--small">
                    Đăng nhập để vào Hàng đợi
                  </Link>
                )}

                {/* Smart suggestions */}
                {suggestions.data && suggestions.data.length > 0 && (
                  <div className="suggestions-block">
                    <span className="suggestions-label">💡 Gợi ý khung giờ thay thế gần nhất:</span>
                    <div className="suggestions-list">
                      {suggestions.data.map((sug, idx) => (
                        <div key={idx} className="suggestion-item">
                          <div>
                            <span className="suggestion-badge">{sug.badge}</span>
                            <strong>{sug.field_name}</strong>
                            <small>{sug.date_label} · {sug.start_time} – {sug.end_time}</small>
                          </div>
                          <button
                            type="button"
                            className="button button--ghost button--small"
                            onClick={() => {
                              if (sug.field_slug === slug) {
                                changeDate(sug.date)
                                setSlotId(sug.time_slot_id)
                              } else {
                                // Sang sân khác thì mang theo ngày + giờ gợi ý để khỏi chọn lại.
                                window.location.href = `/fields/${sug.field_slug}?date=${sug.date}&start=${sug.start_time}`
                              }
                            }}
                          >
                            Chọn giờ này <ArrowRight size={12} />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Dynamic Price Breakdown */}
          {calculatedPrice && selectedSlot && (
            <div className="price-breakdown">
              <div className="price-breakdown__row">
                <span>Khung giờ chọn:</span>
                <strong>
                  {selectedSlot.start_time} – {selectedSlot.end_time} ({calculatedPrice.durationMinutes} phút)
                </strong>
              </div>
              {selectedSlot.price_per_hour && (
                <div className="price-breakdown__row">
                  <span>Giá riêng khung giờ:</span>
                  <strong>{formatMoney(selectedSlot.price_per_hour)}/h</strong>
                </div>
              )}
              <div className="price-breakdown__row price-breakdown__total">
                <span>Tổng tạm tính:</span>
                <strong>{formatMoney(calculatedPrice.total)}</strong>
              </div>
            </div>
          )}

          <label className="booking-label">
            <span>Ghi chú thêm (tùy chọn)</span>
            <textarea
              value={notes}
              rows={2}
              onChange={(event) => setNotes(event.target.value)}
              placeholder="Yêu cầu mượn bóng, nước uống, áo bib..."
            />
          </label>

          {!user ? (
            <Link className="button button--dark button--block" to="/login" state={loginState}>
              Đăng nhập để đặt sân
            </Link>
          ) : user.role !== 'customer' ? (
            <div className="notice notice--soft">Chỉ tài khoản khách hàng có thể đặt sân.</div>
          ) : (
            <button
              disabled={!selectedSlot || booking.isPending}
              onClick={() => setShowConfirmModal(true)}
              className="button button--brand button--block button--lg"
            >
              <CalendarDays size={18} />{' '}
              {booking.isPending ? 'Đang gửi...' : selectedSlot ? 'Gửi yêu cầu' : 'Chọn khung giờ'}
            </button>
          )}

          {booking.isSuccess && !selectedSlot && (
            <div className="notice notice--success" role="status">
              Đã gửi yêu cầu, đang chờ chủ sân duyệt để thanh toán. <Link to="/bookings"><u>Xem đơn của tôi</u></Link>
            </div>
          )}

          <div className="booking-guarantee">
            <ShieldCheck size={14} />
            <span>Sau khi chủ sân duyệt, bạn chuyển khoản đặt cọc hoặc trả hết để giữ sân.</span>
          </div>
        </aside>
      </div>

      {/* Mobile Sticky Booking Bar */}
      <div className="mobile-booking-bar">
        <div className="mobile-booking-bar__price">
          <span>Giá thuê từ</span>
          <strong>
            {calculatedPrice ? formatMoney(calculatedPrice.total) : `${formatMoney(item.price_per_hour)}/h`}
          </strong>
        </div>
        <button
          type="button"
          className="button button--brand"
          disabled={booking.isPending}
          onClick={() => (selectedSlot && user?.role === 'customer' ? setShowConfirmModal(true) : scrollToBooking())}
        >
          <CalendarDays size={16} />{' '}
          {booking.isPending ? 'Đang gửi...' : selectedSlot && user?.role === 'customer' ? 'Gửi yêu cầu' : 'Chọn giờ'}
        </button>
      </div>

      {showConfirmModal && selectedSlot && item && calculatedPrice && (
        <BookingConfirmModal
          isOpen={showConfirmModal}
          onClose={() => {
            setShowConfirmModal(false)
            setRecurringConflicts(null)
            setPendingRecurringConfig(null)
          }}
          onConfirm={(isRecurring, weeks) => {
            setPendingRecurringConfig({ isRecurring, weeks })
            booking.mutate({ isRecurring, weeks, skipConflicts: false })
          }}
          field={item}
          slot={selectedSlot}
          date={date}
          totalPrice={calculatedPrice.total}
          notes={notes}
          busy={booking.isPending}
          conflicts={recurringConflicts}
          onSkipConflicts={() => {
            if (pendingRecurringConfig) {
              booking.mutate({
                isRecurring: pendingRecurringConfig.isRecurring,
                weeks: pendingRecurringConfig.weeks,
                skipConflicts: true,
              })
            }
          }}
        />
      )}
    </div>
  )
}
