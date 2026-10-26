import { useState } from 'react'
import { Link } from 'react-router'
import { Cookie, Check } from 'lucide-react'

export function CookieNotice() {
  const [accepted, setAccepted] = useState(() => {
    try {
      return localStorage.getItem('sporthub:cookie-accepted') === '1'
    } catch {
      return false
    }
  })

  if (accepted) return null

  const handleAccept = () => {
    try {
      localStorage.setItem('sporthub:cookie-accepted', '1')
    } catch {
      // Ignore
    }
    setAccepted(true)
  }

  return (
    <div
      role="region"
      aria-label="Thông báo Cookie và bảo mật"
      style={{
        position: 'fixed',
        bottom: 20,
        right: 20,
        zIndex: 50,
        maxWidth: 380,
        background: 'var(--paper)',
        borderRadius: 14,
        padding: '16px 18px',
        boxShadow: '0 10px 30px rgba(11, 48, 37, 0.16)',
        border: '1px solid var(--line)',
        animation: 'slideUp 0.3s ease-out',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
        <div
          style={{
            width: 36,
            height: 36,
            borderRadius: 10,
            background: 'var(--pitch-soft)',
            color: 'var(--pitch)',
            display: 'grid',
            placeItems: 'center',
            flexShrink: 0,
          }}
        >
          <Cookie size={18} />
        </div>
        <div style={{ fontSize: 13, lineHeight: 1.5, color: 'var(--ink)' }}>
          <strong style={{ display: 'block', marginBottom: 2, color: 'var(--forest)' }}>
            Thông báo Cookie & Quyền riêng tư
          </strong>
          SportHub sử dụng cookie kỹ thuật và lưu trữ cục bộ để duy trì phiên đăng nhập và tối ưu trải nghiệm.{' '}
          <Link
            to="/chinh-sach-bao-mat"
            style={{ color: 'var(--pitch)', fontWeight: 600, textDecoration: 'underline' }}
          >
            Xem chi tiết
          </Link>
          <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end' }}>
            <button
              type="button"
              className="button button--brand button--small"
              onClick={handleAccept}
              style={{ fontSize: 12, padding: '6px 14px' }}
            >
              <Check size={13} /> Đồng ý
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
