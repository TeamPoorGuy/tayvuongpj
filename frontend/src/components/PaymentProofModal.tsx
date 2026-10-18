import { Download, FileText } from 'lucide-react'
import { useEffect, useState } from 'react'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { Modal } from './Modal'

interface PaymentProofModalProps {
  isOpen: boolean
  onClose: () => void
  proofUrl?: string | null
  title?: string
}

export function PaymentProofModal({
  isOpen,
  onClose,
  proofUrl,
  title = 'Biên lai chuyển khoản',
}: PaymentProofModalProps) {
  const [blobUrl, setBlobUrl] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!isOpen || !proofUrl) {
      setBlobUrl(null)
      setError(null)
      return
    }

    let active = true
    setLoading(true)
    setError(null)

    // Chuẩn hoá URL: bóc tách pathname nếu là URL tuyệt đối và bỏ tiền tố /api/v1 vì axios baseURL đã có /api/v1.
    let requestUrl = proofUrl
    try {
      if (proofUrl.startsWith('http://') || proofUrl.startsWith('https://')) {
        requestUrl = new URL(proofUrl).pathname
      }
    } catch {
      // Giữ nguyên requestUrl nếu parse URL thất bại
    }
    if (requestUrl.startsWith('/api/v1/')) {
      requestUrl = requestUrl.slice(7)
    }

    api
      .get(requestUrl, { responseType: 'blob' })
      .then((res) => {
        if (!active) return
        const url = URL.createObjectURL(res.data)
        setBlobUrl(url)
      })
      .catch((err) => {
        if (!active) return
        setError(errorMessage(err) || 'Không thể tải ảnh chứng từ thanh toán.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl)
      }
    }
  }, [isOpen, proofUrl])

  if (!isOpen) return null

  return (
    <Modal isOpen={isOpen} onClose={onClose} ariaLabel={title}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <div
            style={{
              display: 'inline-flex',
              padding: 8,
              borderRadius: 8,
              background: 'var(--pitch-surface, rgba(11, 48, 37, 0.08))',
              color: 'var(--pitch, #0b3025)',
            }}
          >
            <FileText size={20} />
          </div>
          <div>
            <h3 style={{ margin: 0, fontSize: 18, fontWeight: 700 }}>{title}</h3>
            <p style={{ margin: 0, fontSize: 13, color: 'var(--text-muted, #64748b)' }}>
              Hình ảnh sao kê / chứng từ chuyển khoản do khách hàng tải lên
            </p>
          </div>
        </div>

        {loading && (
          <div className="query-state query-state--loading" style={{ minHeight: 180, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            Đang tải ảnh biên lai...
          </div>
        )}

        {error && (
          <div className="notice notice--error" role="alert">
            {error}
          </div>
        )}

        {blobUrl && (
          <div
            style={{
              textAlign: 'center',
              backgroundColor: '#0f172a',
              borderRadius: 12,
              padding: 12,
              maxHeight: '65vh',
              overflow: 'auto',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <img
              src={blobUrl}
              alt="Biên lai thanh toán"
              style={{
                maxWidth: '100%',
                maxHeight: '60vh',
                objectFit: 'contain',
                borderRadius: 8,
                boxShadow: '0 4px 16px rgba(0,0,0,0.3)',
              }}
            />
          </div>
        )}

        <div className="modal__actions" style={{ marginTop: 8, justifyContent: 'space-between' }}>
          <div>
            {blobUrl && (
              <a
                href={blobUrl}
                download="bien-lai-chuyen-khoan.png"
                className="button button--ghost button--small"
                style={{ textDecoration: 'none' }}
              >
                <Download size={14} /> Tải ảnh về máy
              </a>
            )}
          </div>
          <button type="button" className="button button--brand" onClick={onClose}>
            Đóng
          </button>
        </div>
      </div>
    </Modal>
  )
}
