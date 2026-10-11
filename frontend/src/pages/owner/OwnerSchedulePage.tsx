import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Calendar as CalendarIcon,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock,
  Clock3,
  DollarSign,
  Filter,
  Info,
  List,
  MapPin,
  Phone,
  UserCheck,
  ShieldCheck,
  User,
  XCircle,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { Modal } from '../../components/Modal'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import { getLocalTodayString } from '../../utils/date'
import type { Booking, Paginated, SportsField } from '../../types/api'

const currency = new Intl.NumberFormat('vi-VN')

function formatLocalDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const STATUS_CONFIG: Record<
  string,
  { label: string; bg: string; border: string; color: string; dot: string }
> = {
  pending: {
    label: 'Chờ duyệt',
    bg: '#fef3c7',
    border: '#fde68a',
    color: '#92400e',
    dot: '#f59e0b',
  },
  confirmed: {
    label: 'Đã xác nhận',
    bg: '#dcfce7',
    border: '#86efac',
    color: '#166534',
    dot: '#22c55e',
  },
  completed: {
    label: 'Hoàn thành',
    bg: '#e0f2fe',
    border: '#7dd3fc',
    color: '#075985',
    dot: '#0284c7',
  },
  cancelled: {
    label: 'Đã hủy',
    bg: '#f1f5f9',
    border: '#cbd5e1',
    color: '#475569',
    dot: '#94a3b8',
  },
  rejected: {
    label: 'Từ chối',
    bg: '#fee2e2',
    border: '#fca5a5',
    color: '#991b1b',
    dot: '#ef4444',
  },
}

function getWeekDates(centerDateStr: string): string[] {
  const d = new Date(centerDateStr)
  // Day of week: 0 is Sun, 1 is Mon...
  const day = d.getDay()
  // Diff to Monday
  const diffToMonday = day === 0 ? -6 : 1 - day
  const monday = new Date(d)
  monday.setDate(d.getDate() + diffToMonday)

  const week: string[] = []
  for (let i = 0; i < 7; i++) {
    const cur = new Date(monday)
    cur.setDate(monday.getDate() + i)
    week.push(formatLocalDate(cur))
  }
  return week
}

const WEEKDAY_NAMES = [
  'Thứ Hai',
  'Thứ Ba',
  'Thứ Tư',
  'Thứ Năm',
  'Thứ Sáu',
  'Thứ Bảy',
  'Chủ Nhật',
]

export function OwnerSchedulePage() {
  const client = useQueryClient()
  const toast = useToast()

  const todayStr = useMemo(() => getLocalTodayString(), [])
  const [selectedDate, setSelectedDate] = useState<string>(todayStr)
  const [viewMode, setViewMode] = useState<'day' | 'week'>('day')
  const [selectedFieldId, setSelectedFieldId] = useState<string>('')
  const [selectedStatus, setSelectedStatus] = useState<string>('')

  // Booking detail modal
  const [detailBooking, setDetailBooking] = useState<Booking | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [isRejecting, setIsRejecting] = useState(false)

  // 1. Fetch Owner Fields
  const fieldsQuery = useQuery({
    queryKey: ['owner', 'fields-all'],
    queryFn: async () => (await api.get<Paginated<SportsField>>('/field-owner/fields?per_page=50')).data.data,
  })

  const fields = fieldsQuery.data ?? []

  // 2. Compute date range
  const currentWeekDays = useMemo(() => getWeekDates(selectedDate), [selectedDate])
  const startDate = viewMode === 'week' ? currentWeekDays[0] : selectedDate
  const endDate = viewMode === 'week' ? currentWeekDays[6] : selectedDate

  // 3. Fetch Bookings for Date / Range
  const bookingsQuery = useQuery({
    queryKey: ['owner', 'schedule-bookings', startDate, endDate, selectedFieldId, selectedStatus],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (viewMode === 'day') {
        params.set('date', selectedDate)
      } else {
        params.set('start_date', startDate)
        params.set('end_date', endDate)
      }
      params.set('per_page', '200')
      if (selectedFieldId) params.set('field_id', selectedFieldId)
      if (selectedStatus) params.set('status', selectedStatus)

      return (await api.get<Paginated<Booking>>(`/field-owner/bookings?${params.toString()}`)).data.data
    },
  })

  const bookings = bookingsQuery.data ?? []

  // 4. Update status mutation
  const updateStatus = useMutation({
    mutationFn: async ({
      bookingId,
      status,
      reason,
    }: {
      bookingId: number
      status: string
      reason?: string
    }) =>
      api.patch(`/field-owner/bookings/${bookingId}/status`, {
        status,
        ...(reason ? { reason } : {}),
      }),
    onSuccess: (_, vars) => {
      const label =
        vars.status === 'confirmed'
          ? 'xác nhận'
          : vars.status === 'completed'
            ? 'hoàn thành'
            : 'từ chối'
      toast.success(`Đã ${label} đơn đặt sân #BK-${vars.bookingId}.`)
      setIsRejecting(false)
      setRejectReason('')
      setDetailBooking(null)
      void client.invalidateQueries({ queryKey: ['owner', 'schedule-bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Cập nhật trạng thái thất bại')
    },
  })

  // 5. Quick Check-In Mutation
  const checkIn = useMutation({
    mutationFn: async (booking: Booking) =>
      (await api.patch<{ message: string; data: Booking }>(`/field-owner/bookings/${booking.id}/check-in`)).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Đã xác nhận khách đến sân (+10 điểm uy tín cho khách)')
      setDetailBooking(null)
      void client.invalidateQueries({ queryKey: ['owner', 'schedule-bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xác nhận khách đến')
    },
  })

  // Navigation handlers
  const handlePrev = () => {
    const d = new Date(selectedDate)
    d.setDate(d.getDate() - (viewMode === 'week' ? 7 : 1))
    setSelectedDate(formatLocalDate(d))
  }

  const handleNext = () => {
    const d = new Date(selectedDate)
    d.setDate(d.getDate() + (viewMode === 'week' ? 7 : 1))
    setSelectedDate(formatLocalDate(d))
  }

  const handleToday = () => {
    setSelectedDate(todayStr)
  }

  // Active fields to display
  const displayFields = useMemo(() => {
    if (!selectedFieldId) return fields
    return fields.filter((f) => String(f.id) === selectedFieldId)
  }, [fields, selectedFieldId])

  // Extract all unique time slot ranges across active fields
  const timeSlots = useMemo(() => {
    const set = new Map<string, { start: string; end: string }>()
    displayFields.forEach((f) => {
      f.time_slots?.forEach((ts) => {
        const key = `${ts.start_time}-${ts.end_time}`
        if (!set.has(key)) {
          set.set(key, { start: ts.start_time, end: ts.end_time })
        }
      })
    })

    const list = Array.from(set.values()).sort((a, b) =>
      a.start.localeCompare(b.start),
    )

    // Fallback if field has no time slots defined yet
    if (list.length === 0) {
      for (let h = 6; h <= 22; h += 2) {
        const start = `${String(h).padStart(2, '0')}:00`
        const end = `${String(h + 2).padStart(2, '0')}:00`
        list.push({ start, end })
      }
    }
    return list
  }, [displayFields])

  // Bookings mapped by key: `${field_id}_${date}_${start_time}`
  const bookingMap = useMemo(() => {
    const map = new Map<string, Booking>()
    bookings.forEach((b) => {
      const datePart = b.booking_date.slice(0, 10)
      const startPart = b.time_slot?.start_time
      if (startPart) {
        const key = `${b.field?.id}_${datePart}_${startPart}`
        // Đơn đã huỷ/từ chối không chiếm khung giờ: không để nó ghi đè đơn đang hiệu lực.
        const inactive = b.status === 'cancelled' || b.status === 'rejected'
        if (!inactive || !map.has(key)) map.set(key, b)
      }
    })
    return map
  }, [bookings])

  return (
    <div className="owner-schedule-page">
      {/* Title & Navigation */}
      <div className="workspace-title">
        <div className="workspace-title__icon">
          <CalendarDays />
        </div>
        <div>
          <span>Vận hành sân bãi</span>
          <h1>Lịch Đặt Sân Trực Quan</h1>
          <p>
            Theo dõi trạng thái lịch đặt đa sân theo Ngày & Tuần, nhận diện màu sắc trạng thái và thao tác nhanh.
          </p>
        </div>
        <div className="workspace-title__action">
          <Link to="/owner/bookings" className="button button--ghost">
            <List size={16} /> Xem dạng bảng danh sách
          </Link>
        </div>
      </div>

      {/* Rules Notice Banner */}
      <div className="schedule-rules-banner">
        <Info size={18} />
        <div>
          <strong>Quy định vận hành & quản lý khung giờ</strong>
          <p>
            Khung giờ có màu vàng (Chờ duyệt) cần được xác nhận để giữ chỗ cho khách. Khi khách tới sân,
            bấm vào ô booking để <strong>xác nhận khách đã đến (+10 điểm uy tín)</strong>. Bạn có thể
            điều chỉnh giá giờ hoặc thêm khung giờ hoạt động tại{' '}
            <Link to="/owner/fields">Quản lý sân</Link>.
          </p>
        </div>
      </div>

      {/* Main Schedule Toolbar */}
      <div className="schedule-toolbar">
        {/* Date Navigator */}
        <div className="date-nav-group">
          <button type="button" className="button button--ghost button--icon" onClick={handlePrev} title="Lùi lại">
            <ChevronLeft size={18} />
          </button>
          <button type="button" className="button button--ghost" onClick={handleToday}>
            Hôm nay
          </button>
          <button type="button" className="button button--ghost button--icon" onClick={handleNext} title="Tiến tới">
            <ChevronRight size={18} />
          </button>

          <div className="date-display-badge">
            <CalendarIcon size={16} />
            <strong>
              {viewMode === 'day' ? (
                <>
                  {new Date(selectedDate).toLocaleDateString('vi-VN', {
                    weekday: 'long',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                  })}
                </>
              ) : (
                <>
                  Tuần {new Date(startDate).toLocaleDateString('vi-VN')} – {new Date(endDate).toLocaleDateString('vi-VN')}
                </>
              )}
            </strong>
            <input
              type="date"
              value={selectedDate}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="quick-date-input"
              title="Chọn ngày cụ thể"
            />
          </div>
        </div>

        {/* View Mode Toggle: Day vs Week */}
        <div className="view-mode-toggle">
          <button
            type="button"
            className={`toggle-btn ${viewMode === 'day' ? 'active' : ''}`}
            onClick={() => setViewMode('day')}
          >
            Theo ngày
          </button>
          <button
            type="button"
            className={`toggle-btn ${viewMode === 'week' ? 'active' : ''}`}
            onClick={() => setViewMode('week')}
          >
            Theo tuần
          </button>
        </div>

        {/* Filters */}
        <div className="filter-dropdowns">
          <div className="filter-item">
            <Filter size={14} />
            <select
              value={selectedFieldId}
              onChange={(e) => setSelectedFieldId(e.target.value)}
            >
              <option value="">Tất cả sân ({fields.length})</option>
              {fields.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.name}
                </option>
              ))}
            </select>
          </div>

          <div className="filter-item">
            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
            >
              <option value="">Tất cả trạng thái</option>
              <option value="pending">Chờ xử lý</option>
              <option value="confirmed">Đã xác nhận</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Đã hủy</option>
              <option value="rejected">Từ chối</option>
            </select>
          </div>
        </div>
      </div>

      {/* Status Color Legend */}
      <div className="schedule-legend">
        <span className="legend-title">Trạng thái:</span>
        {Object.entries(STATUS_CONFIG).map(([key, cfg]) => (
          <div key={key} className="legend-item">
            <span className="legend-dot" style={{ background: cfg.dot }} />
            <span>{cfg.label}</span>
          </div>
        ))}
        <div className="legend-item">
          <span className="legend-dot empty" />
          <span>Khung giờ trống</span>
        </div>
      </div>

      {/* MOBILE LIST VIEW (Auto on < 768px or toggle) */}
      <div className="mobile-schedule-wrap hide-on-desktop">
        {viewMode === 'week' && (
          <div className="mobile-week-day-tabs">
            {currentWeekDays.map((wDate, idx) => {
              const dayBookings = bookings.filter((b) => b.booking_date.slice(0, 10) === wDate)
              const isSelected = wDate === selectedDate
              const isToday = wDate === todayStr
              const d = new Date(wDate)
              return (
                <button
                  key={wDate}
                  type="button"
                  className={`mobile-day-pill ${isSelected ? 'active' : ''} ${isToday ? 'is-today' : ''}`}
                  onClick={() => setSelectedDate(wDate)}
                >
                  <span className="mobile-day-name">{WEEKDAY_NAMES[idx].replace('Thứ ', 'T')}</span>
                  <strong className="mobile-day-num">{d.getDate()}</strong>
                  {dayBookings.length > 0 && (
                    <span className="mobile-day-badge">{dayBookings.length}</span>
                  )}
                </button>
              )
            })}
          </div>
        )}

        <div className="mobile-view-head">
          <span>
            {viewMode === 'week' ? 'Ngày ' : 'Lịch ngày '}
            {new Date(selectedDate).toLocaleDateString('vi-VN', {
              weekday: 'short',
              day: '2-digit',
              month: '2-digit',
            })}
          </span>
          <span className="badge badge--soft">
            {bookings.filter((b) => b.booking_date.slice(0, 10) === selectedDate).length} lịch đặt
          </span>
        </div>

        <div className="mobile-field-groups">
          {displayFields.map((f) => {
            return (
              <div key={f.id} className="mobile-field-card">
                <div className="mobile-field-card__head">
                  <h3>{f.name}</h3>
                  <small>{currency.format(f.price_per_hour)}đ/h</small>
                </div>

                <div className="mobile-field-slots">
                  {timeSlots.map((ts, idx) => {
                    const booking = bookingMap.get(`${f.id}_${selectedDate}_${ts.start}`)
                    const cfg = booking ? STATUS_CONFIG[booking.status] ?? STATUS_CONFIG.pending : null

                    if (booking) {
                      return (
                        <div
                          key={idx}
                          className="mobile-booking-row"
                          style={{
                            background: cfg?.bg,
                            borderColor: cfg?.border,
                            color: cfg?.color,
                          }}
                          onClick={() => setDetailBooking(booking)}
                        >
                          <div className="mobile-booking-time">
                            <Clock3 size={14} />
                            <strong>{ts.start} – {ts.end}</strong>
                          </div>
                          <div className="mobile-booking-cust">
                            <span>{booking.customer?.name ?? 'Khách'}</span>
                            <small>{currency.format(booking.total_price)}đ</small>
                          </div>
                          <div className="mobile-booking-status">
                            <span className="mini-badge" style={{ background: cfg?.dot }}>
                              {cfg?.label}
                            </span>
                            {booking.checked_in_at && (
                              <span className="mini-badge checkin">✓ Đã đến</span>
                            )}
                          </div>
                        </div>
                      )
                    }

                    // Empty slot
                    return (
                      <div key={idx} className="mobile-empty-slot">
                        <span>{ts.start} – {ts.end}</span>
                        <small>Trống (Khả dụng)</small>
                      </div>
                    )
                  })}
                </div>
              </div>
            )
          })}
        </div>
      </div>

      {/* DESKTOP CALENDAR VIEW */}
      <div className="desktop-schedule-wrap hide-on-mobile">
        {/* DAY VIEW: Multi-field Columns */}
        {viewMode === 'day' && (
          <div className="day-grid-container">
            <div
              className="day-grid-table"
              style={{
                gridTemplateColumns: `100px repeat(${displayFields.length || 1}, minmax(220px, 1fr))`,
              }}
            >
              {/* Header: Timeline Column + Field Columns */}
              <div className="grid-header-cell time-col-head">
                <Clock size={16} /> Giờ
              </div>
              {displayFields.map((f) => (
                <div key={f.id} className="grid-header-cell field-col-head">
                  <strong>{f.name}</strong>
                  <small>{currency.format(f.price_per_hour)}đ/h</small>
                </div>
              ))}

              {/* Rows for each Time Slot */}
              {timeSlots.map((ts, rowIdx) => (
                <div className="grid-row-group" key={rowIdx} style={{ display: 'contents' }}>
                  {/* Time label */}
                  <div className="time-cell">
                    <strong>{ts.start}</strong>
                    <span>{ts.end}</span>
                  </div>

                  {/* Each Field's cell for this slot */}
                  {displayFields.map((f) => {
                    const booking = bookingMap.get(`${f.id}_${selectedDate}_${ts.start}`)
                    const cfg = booking ? STATUS_CONFIG[booking.status] ?? STATUS_CONFIG.pending : null

                    return (
                      <div key={f.id} className="schedule-slot-cell">
                        {booking ? (
                          <div
                            className="booking-block"
                            style={{
                              backgroundColor: cfg?.bg,
                              borderColor: cfg?.border,
                              color: cfg?.color,
                            }}
                            onClick={() => setDetailBooking(booking)}
                            title="Bấm để xem chi tiết và thao tác đơn"
                          >
                            <div className="booking-block__top">
                              <span className="booking-status-indicator" style={{ background: cfg?.dot }} />
                              <strong className="booking-customer-name">
                                {booking.customer?.name ?? 'Khách đặt sân'}
                              </strong>
                              {booking.checked_in_at && (
                                <span className="checked-in-tag" title="Chủ sân đã xác nhận khách đến">
                                  ✓ Đã đến
                                </span>
                              )}
                            </div>
                            <div className="booking-block__meta">
                              <span>#BK-{booking.id}</span>
                              <strong>{currency.format(booking.total_price)}đ</strong>
                            </div>
                          </div>
                        ) : (
                          <div className="empty-slot-block">
                            <span>Khả dụng</span>
                            <small>{currency.format(f.price_per_hour)}đ</small>
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* WEEK VIEW: 7 Day Columns */}
        {viewMode === 'week' && (
          <div className="week-grid-container">
            <div className="week-grid-table">
              {currentWeekDays.map((wDate, idx) => {
                const dayBookings = bookings.filter((b) => b.booking_date.slice(0, 10) === wDate)
                const isSelectedDate = wDate === selectedDate
                const isToday = wDate === todayStr

                return (
                  <div
                    key={wDate}
                    className={`week-day-col ${isSelectedDate ? 'selected-day' : ''} ${isToday ? 'today-col' : ''}`}
                  >
                    <div className="week-day-head" onClick={() => setSelectedDate(wDate)}>
                      <span className="week-day-name">{WEEKDAY_NAMES[idx]}</span>
                      <strong className="week-day-date">
                        {new Date(wDate).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })}
                      </strong>
                      {isToday && <span className="today-badge">Hôm nay</span>}
                      <small className="day-count-badge">{dayBookings.length} lịch</small>
                    </div>

                    <div className="week-day-slots">
                      {dayBookings.map((b) => {
                        const cfg = STATUS_CONFIG[b.status] ?? STATUS_CONFIG.pending
                        return (
                          <div
                            key={b.id}
                            className="week-booking-card"
                            style={{
                              background: cfg.bg,
                              borderColor: cfg.border,
                              color: cfg.color,
                            }}
                            onClick={() => setDetailBooking(b)}
                          >
                            <div className="week-booking-card__top">
                              <span className="booking-status-indicator" style={{ background: cfg.dot }} />
                              <span className="week-card-time">
                                {b.time_slot?.start_time} - {b.time_slot?.end_time}
                              </span>
                            </div>
                            <strong className="week-card-field">{b.field?.name}</strong>
                            <div className="week-card-cust">
                              <span>{b.customer?.name}</span>
                              <strong>{currency.format(b.total_price)}đ</strong>
                            </div>
                          </div>
                        )
                      })}

                      {dayBookings.length === 0 && (
                        <p className="week-empty-text">Chưa có lịch đặt</p>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}
      </div>

      {/* BOOKING DETAIL & QUICK ACTION MODAL */}
      <Modal
        isOpen={!!detailBooking}
        onClose={() => {
          setDetailBooking(null)
          setIsRejecting(false)
        }}
        title={`Chi tiết đơn #BK-${detailBooking?.id ?? ''}`}
      >
        {detailBooking && (
          <div className="booking-detail-modal-body">
            {/* Top Status Bar */}
            <div className="detail-status-bar">
              <StatusBadge status={detailBooking.status} />
              {detailBooking.checked_in_at ? (
                <span className="badge badge--success">
                  <CheckCircle2 size={13} /> Đã đến sân ({new Date(detailBooking.checked_in_at).toLocaleTimeString('vi-VN')})
                </span>
              ) : (
                <span className="badge badge--soft">Chưa check-in</span>
              )}
            </div>

            {/* Information Grid */}
            <div className="detail-info-grid">
              <div className="detail-item">
                <span className="detail-item__label">
                  <User size={13} /> Khách hàng
                </span>
                <strong>{detailBooking.customer?.name ?? '—'}</strong>
                <small>{detailBooking.customer?.email}</small>
                {detailBooking.customer?.phone && (
                  <small>
                    <Phone size={11} /> {detailBooking.customer.phone}
                  </small>
                )}
                <div style={{ marginTop: 4 }}>
                  <span className="badge badge--soft" style={{ fontSize: '0.72rem' }}>
                    <ShieldCheck size={11} /> Điểm uy tín: {detailBooking.customer?.trust_score ?? 100}đ
                  </span>
                </div>
              </div>

              <div className="detail-item">
                <span className="detail-item__label">
                  <MapPin size={13} /> Sân & Khung giờ
                </span>
                <strong>{detailBooking.field?.name}</strong>
                <small>{detailBooking.field?.address}</small>
                <div className="detail-time-highlight">
                  <CalendarDays size={13} />
                  <span>
                    {new Date(detailBooking.booking_date).toLocaleDateString('vi-VN')} (
                    {detailBooking.time_slot?.start_time} – {detailBooking.time_slot?.end_time})
                  </span>
                </div>
              </div>

              <div className="detail-item">
                <span className="detail-item__label">
                  <DollarSign size={13} /> Thanh toán
                </span>
                <strong style={{ fontSize: '1.1rem', color: 'var(--forest)' }}>
                  {currency.format(detailBooking.total_price)}đ
                </strong>
                <small>Thanh toán tại sân khi nhận sân</small>
              </div>

              <div className="detail-item">
                <span className="detail-item__label">
                  <UserCheck size={13} /> Đối chiếu khách
                </span>
                <code className="checkin-code-box">#BK-{String(detailBooking.id).padStart(5, '0')}</code>
                {detailBooking.customer?.phone && <small>{detailBooking.customer.phone}</small>}
              </div>
            </div>

            {detailBooking.notes && (
              <div className="detail-notes-box">
                <span>Ghi chú từ khách:</span>
                <p>&quot;{detailBooking.notes}&quot;</p>
              </div>
            )}

            {detailBooking.cancel_reason && (
              <div className="detail-cancel-box">
                <span>Lý do hủy/từ chối:</span>
                <p>{detailBooking.cancel_reason}</p>
              </div>
            )}

            {/* Reject reason input view */}
            {isRejecting ? (
              <div className="reject-reason-form">
                <label>
                  <span>Lý do từ chối đơn đặt</span>
                  <textarea
                    value={rejectReason}
                    onChange={(e) => setRejectReason(e.target.value)}
                    placeholder="VD: Sân bảo trì đột xuất, trùng lịch giải đấu..."
                    rows={3}
                    autoFocus
                  />
                </label>
                <div className="modal__actions" style={{ marginTop: 12 }}>
                  <button
                    type="button"
                    className="button button--ghost"
                    onClick={() => setIsRejecting(false)}
                  >
                    Hủy
                  </button>
                  <button
                    type="button"
                    className="button button--danger"
                    disabled={updateStatus.isPending}
                    onClick={() =>
                      updateStatus.mutate({
                        bookingId: detailBooking.id,
                        status: 'rejected',
                        reason: rejectReason || 'Chủ sân từ chối đơn này.',
                      })
                    }
                  >
                    {updateStatus.isPending ? 'Đang gửi...' : 'Xác nhận từ chối'}
                  </button>
                </div>
              </div>
            ) : (
              /* Modal Actions Toolbar */
              <div className="modal__actions" style={{ marginTop: 24, justifyContent: 'space-between' }}>
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setDetailBooking(null)}
                >
                  Đóng
                </button>

                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {detailBooking.status === 'pending' && (
                    <>
                      <button
                        type="button"
                        className="button button--brand"
                        disabled={updateStatus.isPending}
                        onClick={() =>
                          updateStatus.mutate({ bookingId: detailBooking.id, status: 'confirmed' })
                        }
                      >
                        <CheckCircle2 size={16} /> Xác nhận đơn
                      </button>
                      <button
                        type="button"
                        className="button button--ghost button--danger"
                        onClick={() => setIsRejecting(true)}
                      >
                        <XCircle size={16} /> Từ chối
                      </button>
                    </>
                  )}

                  {detailBooking.status === 'confirmed' && (
                    <>
                      {!detailBooking.checked_in_at && (
                        <button
                          type="button"
                          className="button button--ghost"
                          disabled={checkIn.isPending}
                          onClick={() => checkIn.mutate(detailBooking)}
                          title="Xác nhận khách đã tới sân và cộng +10 điểm uy tín"
                        >
                          <UserCheck size={15} /> Khách đã đến (+10đ)
                        </button>
                      )}
                      <button
                        type="button"
                        className="button button--brand"
                        disabled={updateStatus.isPending}
                        onClick={() =>
                          updateStatus.mutate({ bookingId: detailBooking.id, status: 'completed' })
                        }
                      >
                        Hoàn tất lượt chơi
                      </button>
                    </>
                  )}
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
