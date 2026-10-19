import { useState } from 'react'
import { GraduationCap, X } from 'lucide-react'

export function DemoNoticeBanner() {
  const [dismissed, setDismissed] = useState(() => {
    try {
      return localStorage.getItem('sporthub:demo-banner-dismissed') === '1'
    } catch {
      return false
    }
  })

  if (dismissed) return null

  const handleDismiss = () => {
    try {
      localStorage.setItem('sporthub:demo-banner-dismissed', '1')
    } catch {
      // Ignore localStorage failures
    }
    setDismissed(true)
  }

  return (
    <aside
      aria-label="Thông báo phiên bản thử nghiệm"
      style={{
        background: 'linear-gradient(90deg, #0b3025, #14493a)',
        color: '#e4f1e6',
        fontSize: 12,
        fontWeight: 500,
        padding: '6px 16px',
        borderBottom: '1px solid rgba(185, 239, 82, 0.25)',
      }}
    >
      <div
        className="shell"
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: 12,
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 4,
              color: 'var(--lime)',
              fontWeight: 700,
              textTransform: 'uppercase',
              letterSpacing: '0.05em',
            }}
          >
            <GraduationCap size={15} /> Bản demo đồ án
          </span>
          <span style={{ color: '#d5e0da' }}>
            Nền tảng phục vụ mục đích nghiên cứu & học tập. Mọi dữ liệu sân bãi và chuyển khoản không phát sinh giao dịch thật.
          </span>
        </div>
        <button
          type="button"
          onClick={handleDismiss}
          style={{
            background: 'transparent',
            border: 0,
            color: '#b8c9bf',
            cursor: 'pointer',
            padding: 4,
            display: 'grid',
            placeItems: 'center',
            borderRadius: 4,
          }}
          title="Tắt thông báo này"
          aria-label="Đóng thông báo demo"
        >
          <X size={14} />
        </button>
      </div>
    </aside>
  )
}
