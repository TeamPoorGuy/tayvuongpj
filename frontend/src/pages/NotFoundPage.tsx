import { Compass, Home, ArrowLeft } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

export function NotFoundPage() {
  const navigate = useNavigate()

  return (
    <div className="shell page-section" style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div className="empty-page" style={{ maxWidth: 520, margin: '0 auto', textAlign: 'center', padding: '48px 24px' }}>
        <div
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: 80,
            height: 80,
            borderRadius: '50%',
            background: 'var(--pitch-surface, rgba(11, 48, 37, 0.08))',
            color: 'var(--pitch, #0b3025)',
            marginBottom: 24,
          }}
        >
          <Compass size={40} />
        </div>
        <h1 style={{ fontSize: 'clamp(2rem, 4vw, 2.5rem)', margin: '0 0 12px', fontWeight: 800 }}>404 - Không tìm thấy trang</h1>
        <p style={{ color: 'var(--text-muted, #64748b)', fontSize: 16, lineHeight: 1.6, margin: '0 0 28px' }}>
          Đường dẫn bạn truy cập có thể đã bị thay đổi, bị xóa hoặc không còn tồn tại trên SportHub.
        </p>
        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => navigate(-1)}
          >
            <ArrowLeft size={16} /> Quay lại
          </button>
          <Link to="/" className="button button--brand">
            <Home size={16} /> Về trang chủ
          </Link>
          <Link to="/fields" className="button button--ghost">
            Khám phá sân
          </Link>
        </div>
      </div>
    </div>
  )
}
