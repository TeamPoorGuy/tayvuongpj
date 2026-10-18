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
  onConfirm: () => void
  field: SportsField
  slot: TimeSlot
  date: string
  totalPrice: number
  notes?: string
  busy?: boolean
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
}: BookingConfirmModalProps) {
  const { data: settings } = usePublicSettings()
  const [agreed, setAgreed] = useState(false)

  const depositPercent = settings?.deposit_percent ?? 30
  const depositAmount = Math.min(totalPrice, Math.ceil((totalPrice * depositPercent) / 100 / 1000) * 1000)
  const remainingAmount = Math.max(0, totalPrice - depositAmount)
  const paymentWindowHours = settings?.payment_window_hours ?? 2
  const freeCancelHours = settings?.free_cancel_hours ?? 4

  const handleClose = () => {
    setAgreed(false)
    onClose()
  }

  const handleConfirm = () => {
    if (!agreed || busy) return
    onConfirm()
    setAgreed(false)
  }

  return (
    <Modal isOpen={isOpen} onClose={handleClose} ariaLabel="Xác nhận yêu cầu đặt sân">
      <div style={{ maxWidth: 480 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
          <div style={{ width: 40, height: 40, borderRadius: 10, background: 'var(--pitch-soft)', display: 'grid', placeItems: 'center', color: 'var(--pitch)' }}>
            <Calendar size={20} />
          </div>
          <div>
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest)' }}>Xác nhận yêu cầu đặt sân</h2>
            <small style={{ color: 'var(--muted)' }}>Vui lòng kiểm tra lại thông tin và chính sách</small>
          </div>
        </div>

        {/* Thông tin đơn */}
        <div className="content-card" style={{ padding: 16, background: 'var(--surface)', marginBottom: 16, fontSize: 13 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ color: 'var(--muted)' }}>Sân thể thao:</span>
            <strong>{field.name}</strong>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ color: 'var(--muted)' }}>Lịch chơi:</span>
            <span>{slot.start_time} – {slot.end_time}, {formatDate(date)}</span>
          </div>
          {notes && (
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
              <span style={{ color: 'var(--muted)' }}>Ghi chú:</span>
              <span style={{ fontStyle: 'italic', maxWidth: 260, textAlign: 'right' }}>{notes}</span>
            </div>
          )}
          <hr style={{ border: 0, borderTop: '1px solid var(--line)', margin: '10px 0' }} />
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
            <span>Tổng tiền thuê sân:</span>
            <strong>{formatMoney(totalPrice)}</strong>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--pitch)', fontWeight: 600, marginBottom: 4 }}>
            <span>Tiền đặt cọc giữ chỗ ({depositPercent}%):</span>
            <span>{formatMoney(depositAmount)}</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: 'var(--muted)' }}>
            <span>Thanh toán còn lại tại sân:</span>
            <span>{formatMoney(remainingAmount)}</span>
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
