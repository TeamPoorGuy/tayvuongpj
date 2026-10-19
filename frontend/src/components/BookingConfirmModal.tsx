import { useState } from 'react'
import { Link } from 'react-router'
import { Calendar, Clock, ShieldCheck } from 'lucide-react'
import { Modal } from './Modal'
import { usePublicSettings } from '../hooks/usePublicSettings'
import { formatMoney, formatDate } from '../utils/format'
import type { SportsField, TimeSlot } from '../types/api'

interface BookingConfirmModalProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: (isRecurring: boolean, weeks: number) => void
  field: SportsField
  slot: TimeSlot
  date: string
  totalPrice: number
  notes?: string
  busy?: boolean
  conflicts?: Array<{ date: string; reason: string }> | null
  onSkipConflicts?: () => void
}

export function BookingConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  field,
  slot,
  date,
  totalPrice,
  notes,
  busy = false,
  conflicts = null,
  onSkipConflicts,
}: BookingConfirmModalProps) {
  const { data: settings } = usePublicSettings()
  const [agreed, setAgreed] = useState(false)
  const [isRecurring, setIsRecurring] = useState(false)
  const [weeks, setWeeks] = useState(4)

  const effectiveTotalPrice = totalPrice * (isRecurring ? weeks : 1)
  const depositPercent = settings?.deposit_percent ?? 30
  const perMatchDeposit = Math.min(totalPrice, Math.ceil((totalPrice * depositPercent) / 100 / 1000) * 1000)
  const depositAmount = isRecurring ? perMatchDeposit : Math.min(totalPrice, Math.ceil((totalPrice * depositPercent) / 100 / 1000) * 1000)
  const remainingAmount = Math.max(0, effectiveTotalPrice - (isRecurring ? perMatchDeposit * weeks : depositAmount))
  const paymentWindowHours = settings?.payment_window_hours ?? 2
  const freeCancelHours = settings?.free_cancel_hours ?? 4

  const handleClose = () => {
    setAgreed(false)
    setIsRecurring(false)
    onClose()
  }

  const handleConfirm = () => {
    if (!agreed || busy) return
    onConfirm(isRecurring, weeks)
    setAgreed(false)
  }

  return (
    <Modal isOpen={isOpen} onClose={handleClose} ariaLabel="Xác nhận yêu cầu đặt sân">
      <div style={{ maxWidth: 500 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
          <div style={{ width: 40, height: 40, borderRadius: 10, background: 'var(--pitch-soft, rgba(11, 48, 37, 0.08))', display: 'grid', placeItems: 'center', color: 'var(--pitch, #0b3025)' }}>
            <Calendar size={20} />
          </div>
          <div>
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest, #0b3025)' }}>
              {isRecurring ? 'Xác nhận đặt cố định hàng tuần' : 'Xác nhận yêu cầu đặt sân'}
            </h2>
            <small style={{ color: 'var(--muted)' }}>Vui lòng kiểm tra lại thông tin và chính sách</small>
          </div>
        </div>

        {/* Tuỳ chọn đặt cố định định kỳ */}
        <div style={{ padding: '12px 14px', background: 'var(--surface-sunken, #f8fafc)', borderRadius: 10, border: '1px solid var(--border)', marginBottom: 16 }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer', margin: 0, fontWeight: 600, fontSize: 13 }}>
            <input
              type="checkbox"
              checked={isRecurring}
              onChange={(e) => setIsRecurring(e.target.checked)}
            />
            <span>Lặp lại cố định hằng tuần (Dành cho đội bóng sinh hoạt)</span>
          </label>
          {isRecurring && (
            <div style={{ marginTop: 10, paddingTop: 10, borderTop: '1px dashed var(--border)', display: 'flex', alignItems: 'center', gap: 12 }}>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>Số tuần duy trì:</span>
              <select
                value={weeks}
                onChange={(e) => setWeeks(Number(e.target.value))}
                style={{ padding: '4px 8px', borderRadius: 6, fontSize: 13, background: 'var(--surface)', border: '1px solid var(--border)' }}
              >
                <option value={2}>2 tuần liên tiếp</option>
                <option value={3}>3 tuần liên tiếp</option>
                <option value={4}>4 tuần (1 tháng)</option>
                <option value={6}>6 tuần</option>
                <option value={8}>8 tuần (2 tháng)</option>
              </select>
            </div>
          )}
        </div>

        {/* Cảnh báo trùng ngày nếu có */}
        {conflicts && conflicts.length > 0 && (
          <div className="notice notice--error" style={{ marginBottom: 16, fontSize: 13 }}>
            <div style={{ fontWeight: 700, marginBottom: 4 }}>
              Có {conflicts.length} ngày trong chuỗi bị trùng lịch hoặc sân nghỉ:
            </div>
            <ul style={{ margin: '4px 0 8px 16px', padding: 0, fontSize: 12 }}>
              {conflicts.map((c, idx) => (
                <li key={idx}>
                  <strong>{formatDate(c.date)}</strong>: {c.reason}
                </li>
              ))}
            </ul>
            {onSkipConflicts && (
              <button
                type="button"
                className="button button--brand button--small"
                onClick={onSkipConflicts}
                disabled={busy}
                style={{ marginTop: 6 }}
              >
                Bỏ qua các ngày trùng & Đặt các ngày còn lại
              </button>
            )}
          </div>
        )}

        {/* Thông tin đơn */}
        <div className="content-card" style={{ padding: 16, background: 'var(--surface)', marginBottom: 16, fontSize: 13 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ color: 'var(--muted)' }}>Sân thể thao:</span>
            <strong>{field.name}</strong>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ color: 'var(--muted)' }}>Lịch chơi:</span>
            <span>
              {slot.start_time} – {slot.end_time}, {formatDate(date)}
              {isRecurring ? ` (và ${weeks - 1} tuần tiếp theo)` : ''}
            </span>
          </div>
          {notes && (
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
              <span style={{ color: 'var(--muted)' }}>Ghi chú:</span>
              <span style={{ fontStyle: 'italic', maxWidth: 260, textAlign: 'right' }}>{notes}</span>
            </div>
          )}
          <hr style={{ border: 0, borderTop: '1px solid var(--line)', margin: '10px 0' }} />
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
            <span>{isRecurring ? `Tổng tiền (${weeks} buổi):` : 'Tổng tiền thuê sân:'}</span>
            <strong>{formatMoney(effectiveTotalPrice)}</strong>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--pitch)', fontWeight: 600, marginBottom: 4 }}>
            <span>{isRecurring ? `Tiền cọc mỗi buổi (${depositPercent}%):` : `Tiền đặt cọc giữ chỗ (${depositPercent}%):`}</span>
            <span>{formatMoney(perMatchDeposit)}</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: 'var(--muted)' }}>
            <span>{isRecurring ? 'Thanh toán cọc:' : 'Thanh toán còn lại tại sân:'}</span>
            <span>{isRecurring ? 'Từng buổi trước trận đấu' : formatMoney(remainingAmount)}</span>
          </div>
        </div>

        {/* Tóm tắt chính sách & hạn chuyển tiền */}
        <div style={{ fontSize: 12, lineHeight: 1.6, color: 'var(--soft)', marginBottom: 16, display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
            <Clock size={14} style={{ color: 'var(--pitch)', marginTop: 2, flexShrink: 0 }} />
            <span>
              <strong>Hạn chuyển khoản:</strong> Sau khi chủ sân duyệt, bạn có <strong>{paymentWindowHours} giờ</strong> để chuyển khoản cọc giữ sân. Quá hạn đơn sẽ tự huỷ.
            </span>
          </div>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
            <ShieldCheck size={14} style={{ color: 'var(--pitch)', marginTop: 2, flexShrink: 0 }} />
            <span>
              <strong>Chính sách hoàn cọc:</strong> Hủy trước giờ chơi tối thiểu <strong>{freeCancelHours} giờ</strong> (hoặc trong <strong>2 giờ sau khi duyệt</strong> nếu đặt cận giờ) để được hoàn 100% cọc. Hủy sát giờ bị trừ 15 điểm uy tín và mất cọc.
            </span>
          </div>
        </div>

        {/* Checkbox cam kết */}
        <div style={{ marginBottom: 20 }}>
          <label className="check-line" style={{ display: 'flex', alignItems: 'flex-start', gap: 8, fontSize: 13, cursor: 'pointer' }}>
            <input
              type="checkbox"
              checked={agreed}
              onChange={(e) => setAgreed(e.target.checked)}
              style={{ marginTop: 3 }}
            />
            <span style={{ lineHeight: 1.4 }}>
              Tôi đã đọc và đồng ý với{' '}
              <Link to="/chinh-sach-huy-hoan" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>
                Chính sách huỷ/hoàn cọc
              </Link>{' '}
              và{' '}
              <Link to="/dieu-khoan" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>
                Điều khoản sử dụng
              </Link>{' '}
              của SportHub.
            </span>
          </label>
        </div>

        {/* Actions */}
        <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
          <button
            type="button"
            disabled={busy}
            className="button button--ghost"
            onClick={handleClose}
          >
            Hủy bỏ
          </button>
          <button
            type="button"
            disabled={!agreed || busy}
            className="button button--brand"
            onClick={handleConfirm}
          >
            {busy ? 'Đang gửi...' : 'Xác nhận đặt sân'}
          </button>
        </div>
      </div>
    </Modal>
  )
}
