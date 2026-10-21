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
  ArrowRight,
  BellRing,
  Zap,
} from 'lucide-react'
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router'
import { useEffect, useState, useMemo } from 'react'
import axios from 'axios'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'
import { useToast } from '../components/Toast'
import { FieldMap } from '../components/FieldMap'
import { addDays, formatDay, getLocalTodayString } from '../utils/date'
import type { ApiEnvelope, GridCell, GridField, SlotSuggestion, SportsField } from '../types/api'
import { Avatar } from '../components/Avatar'
import { formatMoney, formatDate } from '../utils/format'
import { BookingConfirmModal } from '../components/BookingConfirmModal'
import { BookingGrid, BookingGridLegend } from '../components/BookingGrid'
import { bookedRunAround, cellKey, mergeSelection, type GridBlock } from '../utils/grid'
import { loadSelectionDraft, saveSelectionDraft } from '../utils/selectionDraft'
import { usePublicSettings } from '../hooks/usePublicSettings'

const minutesOf = (time: string) => {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}
const shiftTime = (time: string, minutes: number) => {
  const total = minutesOf(time) + minutes
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

export function FieldDetailPage() {

  const { slug = '' } = useParams()
  const { user } = useAuth()
  const { success, error } = useToast()
  const queryClient = useQueryClient()

  const todayStr = useMemo(() => getLocalTodayString(), [])
  const [searchParams] = useSearchParams()
  // Cho phép mở từ trang tìm đối với ngày đã chọn (?date=YYYY-MM-DD), bỏ qua ngày quá khứ.
  const dateParam = searchParams.get('date')
  // Ô đã chọn trước khi rời trang (vd sang đăng nhập): khôi phục khi quay lại, trừ khi mở từ liên kết gợi ý có ngày/giờ riêng.
  const [draft] = useState(() => (searchParams.get('date') || searchParams.get('start') ? null : loadSelectionDraft(slug, todayStr)))
  const [date, setDate] = useState(
    dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam) && dateParam >= todayStr ? dateParam : (draft?.date ?? todayStr),
  )
  // Gợi ý / trang tìm đối mở kèm ?start=HH:MM(&end=HH:MM): chọn sẵn khoảng đó trên sân đang xem nếu còn trống.
  const [hint, setHint] = useState<{ fieldId?: number; start: string; end?: string } | null>(() => {
    const start = searchParams.get('start')
    return start ? { start, end: searchParams.get('end') ?? undefined } : null
  })
  const [picked, setPicked] = useState<Set<string>>(() => new Set(draft?.keys))
  const location = useLocation()
  const loginState = { from: location.pathname + location.search }
  const [notes, setNotes] = useState('')
  const [activeImg, setActiveImg] = useState(0)
  const [bookedRange, setBookedRange] = useState<{ field: GridField; start: string; end: string } | null>(null)
  const [showConfirmModal, setShowConfirmModal] = useState(false)
  const { data: settings } = usePublicSettings()
  const minMinutes = settings?.min_minutes ?? 60

  const field = useQuery({
    queryKey: ['field', slug],
    queryFn: async () => (await api.get<ApiEnvelope<SportsField>>(`/fields/${slug}`)).data.data,
  })

  const [recurringConflicts, setRecurringConflicts] = useState<Array<{ date: string; reason: string }> | null>(null)
  const [pendingRecurringConfig, setPendingRecurringConfig] = useState<{ isRecurring: boolean; weeks: number } | null>(null)

  const schedule = useQuery({
    queryKey: ['schedule', field.data?.id, date],
    enabled: !!field.data,
    queryFn: async () =>
      (await api.get<ApiEnvelope<{ date: string; fields: GridField[] }>>(`/fields/${field.data?.id}/schedule?date=${date}`)).data.data,
  })

  const gridFields = useMemo(() => schedule.data?.fields ?? [], [schedule.data?.fields])
  const viewedRow = gridFields[0]
  const isDateClosed = !!viewedRow?.is_closed
  const closureReason = viewedRow?.closure_reason

  // Chỉ giữ các ô vẫn còn trống (ô khôi phục từ lần trước có thể đã có người đặt trong lúc khách đi đăng nhập).
  const stillFree = useMemo(() => {
    if (gridFields.length === 0) return picked
    const free = new Set(gridFields.flatMap((f) => f.cells.filter((c) => c.state === 'free').map((c) => cellKey(f.id, c.start))))
    return new Set([...picked].filter((key) => free.has(key)))
  }, [gridFields, picked])
  const droppedCount = picked.size - stillFree.size

  const selected = useMemo(() => {
    if (!hint || stillFree.size > 0) return stillFree
    const row = gridFields.find((f) => f.id === hint.fieldId) ?? viewedRow
    if (!row) return stillFree
    const end = hint.end ?? shiftTime(hint.start, minMinutes)
    const cells = row.cells.filter((c) => c.start >= hint.start && c.end <= end)
    return cells.length > 0 && cells.every((c) => c.state === 'free') ? new Set(cells.map((c) => cellKey(row.id, c.start))) : stillFree
  }, [hint, stillFree, gridFields, viewedRow, minMinutes])

  useEffect(() => {
    saveSelectionDraft(slug, date, selected)
  }, [slug, date, selected])

  const blocks = useMemo(() => mergeSelection(gridFields, selected), [gridFields, selected])
  const total = blocks.reduce((sum, b) => sum + b.price, 0)
  const durationOf = (b: GridBlock) => minutesOf(b.end) - minutesOf(b.start)
  const hasShortBlock = blocks.some((b) => durationOf(b) < minMinutes)

  const toggleCell = (row: GridField, cell: GridCell) => {
    const next = new Set(selected)
    const key = cellKey(row.id, cell.start)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    setHint(null)
    setPicked(next)
    setBookedRange(null)
  }

  const suggestions = useQuery({
    queryKey: ['suggestions', bookedRange?.field.slug, bookedRange?.start, bookedRange?.end, date],
    enabled: !!bookedRange,
    queryFn: async () =>
      (await api.get<ApiEnvelope<SlotSuggestion[]>>(
        `/fields/${bookedRange?.field.slug}/suggestions?start_time=${bookedRange?.start}&end_time=${bookedRange?.end}&date=${date}`,
      )).data.data,
  })

  const joinWaitlist = useMutation({
    mutationFn: async (range: { field: GridField; start: string; end: string }) =>
      api.post('/customer/waitlist', {
        sports_field_id: range.field.id,
        start_time: range.start,
        end_time: range.end,
        booking_date: date,
      }),
    onSuccess: () => {
      success(
        'Đã tham gia hàng đợi thành công! Hệ thống sẽ ưu tiên gửi email giữ chỗ cho bạn ngay khi có người hủy.',
        'Hàng đợi thông minh (Waitlist)',
      )
      setBookedRange(null)
    },
    onError: (err) => {
      error(errorMessage(err), 'Không thể vào hàng đợi')
    },
  })

  const navigate = useNavigate()
  const booking = useMutation({
    mutationFn: async ({ isRecurring, weeks, skipConflicts }: { isRecurring: boolean; weeks: number; skipConflicts?: boolean }) => {
      if (isRecurring) {
        const block = blocks[0]
        return (await api.post('/customer/bookings/recurring', {
          sports_field_id: block.fieldId,
          start_time: block.start,
          end_time: block.end,
          start_date: date,
          weeks,
          notes,
          skip_conflicts: skipConflicts ?? false,
        })).data
      }
      return (await api.post('/customer/bookings', {
        booking_date: date,
        items: blocks.map((b) => ({ sports_field_id: b.fieldId, start_time: b.start, end_time: b.end })),
        notes,
      })).data
    },
    onSuccess: (res, vars) => {
      setShowConfirmModal(false)
      setRecurringConflicts(null)
      setPendingRecurringConfig(null)
      setPicked(new Set())
      setHint(null)
      setNotes('')
      success(vars.isRecurring ? `Đã giữ chỗ ${vars.weeks} buổi. Thanh toán một lần cho cả chuỗi.` : 'Đã giữ chỗ. Hãy chuyển khoản để hoàn tất.', 'Đã giữ chỗ')
      void queryClient.invalidateQueries({ queryKey: ['schedule'] })
      void queryClient.invalidateQueries({ queryKey: ['customer', 'bookings'] })
      const paymentId = vars.isRecurring ? res?.data?.payment?.id : res?.payment?.id
      if (paymentId) navigate(`/payments/${paymentId}`)
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

  const changeDate = (next: string) => {
    setDate(next)
    setPicked(new Set())
    setHint(null)
    setBookedRange(null)
  }

  const weekDays = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(todayStr, i)), [todayStr])

  const freeCount = gridFields.reduce((n, f) => n + f.cells.filter((c) => c.state === 'free').length, 0)
  const isDateFullyBooked = !schedule.isPending && gridFields.some((f) => f.cells.length > 0) && freeCount === 0
  const tomorrowDateStr = addDays(date, 1)
  const canBook = blocks.length > 0 && !hasShortBlock

  const scrollToGrid = () => {
    document.getElementById('booking-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
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
          <button type="button" className="button button--brand" style={{ marginTop: 14 }} onClick={scrollToGrid}>
            <CalendarDays size={16} /> Chọn giờ & đặt sân
          </button>
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

      {/* Lưới đặt sân 30 phút: mỗi hàng là một sân trong cụm, chọn nhiều ô rồi thanh toán một lần */}
      <section className="content-card booking-grid-section" id="booking-grid">
        <div className="booking-grid-section__head">
          <h2>Đặt lịch theo sân</h2>
          <BookingGridLegend />
        </div>

        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', marginBottom: 12 }}>
          <input
            type="date"
            aria-label="Ngày vào sân"
            min={todayStr}
            value={date}
            onChange={(event) => event.target.value && changeDate(event.target.value)}
            style={{ maxWidth: 180 }}
          />
          <div className="day-strip" role="group" aria-label="Chọn nhanh ngày" style={{ flex: 1, minWidth: 260 }}>
            {weekDays.map((day) => {
              const [label, rest] = formatDay(day).split(' · ')
              return (
                <button type="button" key={day} className={day === date ? 'active' : ''} aria-pressed={day === date} onClick={() => changeDate(day)}>
                  <strong>{label}</strong>
                  <small>{rest.slice(0, 5)}</small>
                </button>
              )
            })}
          </div>
        </div>

        {schedule.isPending && <div className="query-state query-state--loading">Đang tải lịch sân...</div>}

        {isDateClosed && (
          <div className="notice notice--warn" style={{ marginBottom: 12 }}>
            <AlertTriangle size={18} />
            <div>
              <strong>Sân tạm đóng cửa vào ngày này:</strong> {closureReason || 'Bảo trì hoặc nghỉ lễ theo lịch của chủ sân.'}
            </div>
          </div>
        )}

        {!isDateClosed && isDateFullyBooked && (
          <div className="fully-booked-notice">
            <div className="fully-booked-head">
              <Info size={15} />
              <strong>{formatDay(date)} đã kín lịch!</strong>
            </div>
            <p>Bấm vào ô đã đặt để vào <strong>hàng chờ</strong>, hoặc xem ngày kế tiếp.</p>
            <button type="button" className="button button--brand button--small" onClick={() => changeDate(tomorrowDateStr)}>
              <CalendarDays size={13} /> Xem ngày mai ({formatDay(tomorrowDateStr)})
            </button>
          </div>
        )}

        {!schedule.isPending && (
          <BookingGrid
            fields={gridFields}
            selected={selected}
            onToggle={toggleCell}
            onBookedClick={(row, cell) => setBookedRange({ field: row, ...bookedRunAround(row, cell) })}
          />
        )}

        {bookedRange && (
          <div className="waitlist-card" style={{ marginTop: 14 }}>
            <div className="waitlist-card__head">
              <div className="waitlist-card__title">
                <Zap size={16} />
                <strong>{bookedRange.field.name} · {bookedRange.start} – {bookedRange.end} đã có người đặt</strong>
              </div>
              <button type="button" className="button button--ghost button--small" onClick={() => setBookedRange(null)}>
                Đóng
              </button>
            </div>
            <p className="waitlist-card__desc">
              Tham gia <strong>hàng chờ</strong> để được ưu tiên giữ chỗ ngay khi người đặt huỷ.
            </p>
            {user ? (
              <button
                type="button"
                className="button button--brand button--small waitlist-btn"
                onClick={() => joinWaitlist.mutate(bookedRange)}
                disabled={joinWaitlist.isPending}
              >
                <BellRing size={14} /> {joinWaitlist.isPending ? 'Đang đăng ký...' : 'Vào hàng chờ'}
              </button>
            ) : (
              <Link to="/login" state={loginState} className="button button--brand button--small">
                Đăng nhập để vào hàng chờ
              </Link>
            )}

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
                          if (gridFields.some((f) => f.id === sug.field_id)) {
                            changeDate(sug.date)
                            setHint({ fieldId: sug.field_id, start: sug.start_time, end: sug.end_time })
                          } else {
                            // Sang sân khác thì mang theo ngày + giờ gợi ý để khỏi chọn lại.
                            window.location.href = `/fields/${sug.field_slug}?date=${sug.date}&start=${sug.start_time}&end=${sug.end_time}`
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
      </section>

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
                <Info /> Giữ chỗ 15 phút để chuyển khoản · Huỷ sớm được hoàn tiền theo chính sách huỷ
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

          <div className="booking-label">
            <span className="field-label">Khung giờ đã chọn ({formatDay(date)})</span>
            {droppedCount > 0 && (
              <div className="notice notice--warn" role="status" style={{ margin: '6px 0 10px', fontSize: 13 }}>
                <AlertTriangle size={16} />
                <span>{droppedCount} ô bạn chọn trước đó vừa có người đặt nên đã được bỏ chọn.</span>
              </div>
            )}
            {blocks.length === 0 ? (
              <p className="form-help" style={{ margin: '6px 0 12px' }}>
                Bấm các ô trống trên lưới đặt sân (mỗi ô 30 phút). Có thể chọn nhiều sân, nhiều khung giờ rồi thanh toán một lần.
              </p>
            ) : (
              <ul className="selection-list">
                {blocks.map((b) => (
                  <li key={`${b.fieldId}-${b.start}`} className={durationOf(b) < minMinutes ? 'is-short' : ''}>
                    <span>
                      <strong>{b.fieldName}</strong> · {b.start}–{b.end}
                      {durationOf(b) < minMinutes && <small> (tối thiểu {minMinutes} phút)</small>}
                    </span>
                    <strong>{formatMoney(b.price)}</strong>
                  </li>
                ))}
              </ul>
            )}
            {blocks.length > 0 && (
              <div className="price-breakdown">
                <div className="price-breakdown__row price-breakdown__total">
                  <span>Tổng tạm tính:</span>
                  <strong>{formatMoney(total)}</strong>
                </div>
              </div>
            )}
          </div>

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
            <>
              <Link className="button button--dark button--block" to="/login" state={loginState}>
                Đăng nhập để đặt sân
              </Link>
              {blocks.length > 0 && <p className="form-help" style={{ margin: '8px 0 0', textAlign: 'center' }}>Các ô bạn đã chọn sẽ được giữ lại sau khi đăng nhập.</p>}
            </>
          ) : user.role !== 'customer' ? (
            <div className="notice notice--soft">Chỉ tài khoản khách hàng có thể đặt sân.</div>
          ) : (
            <button
              disabled={!canBook || booking.isPending}
              onClick={() => setShowConfirmModal(true)}
              className="button button--brand button--block button--lg"
            >
              <CalendarDays size={18} />{' '}
              {booking.isPending ? 'Đang giữ chỗ...' : blocks.length ? 'Đặt & thanh toán' : 'Chọn ô trên lưới'}
            </button>
          )}

          {booking.isSuccess && blocks.length === 0 && (
            <div className="notice notice--success" role="status">
              Đã giữ chỗ, hãy chuyển khoản để hoàn tất. <Link to="/bookings"><u>Xem đơn của tôi</u></Link>
            </div>
          )}

          <div className="booking-guarantee">
            <ShieldCheck size={14} />
            <span>Thanh toán qua SportHub: đơn xác nhận ngay khi nhận tiền, được hoàn tiền nếu chủ sân huỷ.</span>
          </div>
        </aside>
      </div>

      {/* Mobile Sticky Booking Bar */}
      <div className="mobile-booking-bar">
        <div className="mobile-booking-bar__price">
          <span>Giá thuê từ</span>
          <strong>
            {blocks.length ? formatMoney(total) : `${formatMoney(item.price_per_hour)}/h`}
          </strong>
        </div>
        <button
          type="button"
          className="button button--brand"
          disabled={booking.isPending}
          onClick={() => (canBook && user?.role === 'customer' ? setShowConfirmModal(true) : scrollToGrid())}
        >
          <CalendarDays size={16} />{' '}
          {booking.isPending ? 'Đang giữ chỗ...' : canBook && user?.role === 'customer' ? 'Đặt & thanh toán' : 'Chọn giờ'}
        </button>
      </div>

      {showConfirmModal && canBook && item && (
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
          blocks={blocks}
          date={date}
          totalPrice={total}
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
