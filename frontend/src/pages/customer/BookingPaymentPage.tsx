import { useMutation, useQuery } from '@tanstack/react-query'
import {
  ArrowLeft,
  CheckCircle2,
  Copy,
  CreditCard,
  Download,
  Eye,
  QrCode,
  ShieldAlert,
  Timer,
  Upload,
  X,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { PaymentProofModal } from '../../components/PaymentProofModal'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import type { ApiEnvelope, Booking } from '../../types/api'
import { formatDate, formatMoney } from '../../utils/format'
import { PageTitle } from './CustomerPages'

const secondsUntil = (until: string) => Math.max(0, Math.floor((new Date(until).getTime() - Date.now()) / 1000))

function Countdown({ until }: { until: string }) {
  const [seconds, setSeconds] = useState(() => secondsUntil(until))

  useEffect(() => {
    const timer = setInterval(() => setSeconds(secondsUntil(until)), 1000)
    return () => clearInterval(timer)
  }, [until])

  const pad = (value: number) => value.toString().padStart(2, '0')
  return (
    <span className={`countdown-chip ${seconds < 600 ? 'countdown-chip--danger' : ''}`}>
      <Timer size={13} />{' '}
      {seconds > 0
        ? `Còn ${Math.floor(seconds / 3600)}:${pad(Math.floor((seconds % 3600) / 60))}:${pad(seconds % 60)} để chuyển khoản`
        : 'Đã hết hạn chuyển khoản'}
    </span>
  )
}

export function BookingPaymentPage() {
  const { id } = useParams()
  const toast = useToast()
  const [option, setOption] = useState<'deposit' | 'full'>('deposit')
  const [checked, setChecked] = useState(false)
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [proofPreview, setProofPreview] = useState<string | null>(null)
  const [showProofModal, setShowProofModal] = useState(false)
  const [downloadingQr, setDownloadingQr] = useState(false)

  const handleDownloadQr = async () => {
    if (!vietQrUrl) return
    try {
      setDownloadingQr(true)
      const res = await fetch(vietQrUrl)
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `vietqr-${payment?.code ?? 'booking'}.png`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } catch {
      window.open(vietQrUrl, '_blank')
    } finally {
      setDownloadingQr(false)
    }
  }

  const query = useQuery({
    queryKey: ['customer', 'booking', id],
    queryFn: async () => (await api.get<ApiEnvelope<Booking>>(`/customer/bookings/${id}`)).data.data,
    // Đang chờ chủ sân duyệt/đối chiếu: tự làm mới để thấy kết quả mà không cần tải lại trang.
    refetchInterval: (state) => (state.state.data?.status === 'pending' ? 30_000 : false),
  })

  const report = useMutation({
    mutationFn: async () => {
      const fd = new FormData()
      fd.append('option', option)
      if (proofFile) {
        fd.append('proof', proofFile)
      }
      return api.post(`/customer/bookings/${id}/payment`, fd)
    },
    onSuccess: () => {
      toast.success('Đã ghi nhận. Chủ sân sẽ đối chiếu và xác nhận.', 'Đã báo chuyển khoản')
      setProofFile(null)
      setProofPreview(null)
      void query.refetch()
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể ghi nhận'),
  })

  const copy = async (value: string, label: string) => {
    try {
      await navigator.clipboard.writeText(value)
      toast.success(`Đã sao chép ${label}.`)
    } catch {
      toast.error('Trình duyệt không cho sao chép, hãy chọn và sao chép thủ công.')
    }
  }

  const handleProofChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
      toast.error('Vui lòng chọn tệp ảnh hợp lệ (JPG, PNG, WebP).')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Kích thước ảnh biên lai tối đa là 5MB.')
      return
    }
    setProofFile(file)
    setProofPreview(URL.createObjectURL(file))
  }

  const removeProof = () => {
    setProofFile(null)
    if (proofPreview) {
      URL.revokeObjectURL(proofPreview)
      setProofPreview(null)
    }
  }

  const booking = query.data
  const payment = booking?.payment
  const title = (
    <PageTitle
      icon={<CreditCard />}
      kicker="Thanh toán"
      title="Chuyển khoản giữ sân"
      copy="Chuyển khoản trực tiếp cho chủ sân đúng số tiền và nội dung, rồi bấm xác nhận để chủ sân đối chiếu."
      action={
        <Link to="/bookings" className="button button--ghost">
          <ArrowLeft size={16} /> Đơn đặt của tôi
        </Link>
      }
    />
  )

  if (query.isPending) return <>{title}<div className="query-state query-state--loading">Đang tải đơn đặt sân...</div></>
  if (query.isError || !booking || !payment) {
    return <>{title}<div className="notice notice--error" role="alert">{errorMessage(query.error) || 'Không tìm thấy đơn đặt sân.'}</div></>
  }

  const amount = option === 'full' ? booking.total_price : payment.deposit_amount

  // VietQR URL if bank BIN and account number are present
  const vietQrUrl =
    booking.bank?.bank_bin && booking.bank?.account_number
      ? `https://img.vietqr.io/image/${booking.bank.bank_bin}-${booking.bank.account_number}-compact2.png?amount=${amount}&addInfo=${encodeURIComponent(payment.code ?? '')}&accountName=${encodeURIComponent(booking.bank.account_holder ?? '')}`
      : null

  // copyValue: giá trị thô để dán vào app ngân hàng (vd số tiền không có dấu chấm).
  const row = (label: string, value: string | null | undefined, copyLabel?: string, className?: string, copyValue?: string) => (
    <div>
      <dt>{label}</dt>
      <dd className={className}>
        {value ?? '—'}
        {copyLabel && value && (
          <button type="button" className="button button--icon button--ghost" title={`Sao chép ${copyLabel}`} onClick={() => void copy(copyValue ?? value, copyLabel)}>
            <Copy size={14} />
          </button>
        )}
      </dd>
    </div>
  )

  const summary = (
    <dl className="payment-rows">
      {row('Mã đơn', `#BK-${String(booking.id).padStart(5, '0')}`)}
      {row('Sân', booking.field?.name)}
      {row('Lịch chơi', `${booking.time_slot?.start_time} – ${booking.time_slot?.end_time}, ${formatDate(booking.booking_date)}`)}
      {row('Tổng tiền thuê sân', formatMoney(booking.total_price))}
    </dl>
  )

  return (
    <>
      {title}
      <section className="content-card payment-card" style={{ maxWidth: 760 }}>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
          <StatusBadge status={booking.status} payment={payment.status} />
          {payment.status === 'awaiting' && payment.due_at && <Countdown until={payment.due_at} />}
        </div>

        {booking.status === 'pending' && !payment.status && (
          <>
            {summary}
            <div className="notice notice--soft">Đơn đang chờ chủ sân duyệt. Thông tin chuyển khoản sẽ hiện ở đây ngay sau khi được duyệt.</div>
          </>
        )}

        {booking.status === 'pending' && payment.status === 'awaiting' && (
          <>
            <div className="payment-options" role="radiogroup" aria-label="Hình thức thanh toán">
              <button type="button" role="radio" aria-checked={option === 'deposit'} className={`payment-option ${option === 'deposit' ? 'is-active' : ''}`} onClick={() => setOption('deposit')}>
                <small>Đặt cọc {payment.deposit_percent}%</small>
                <strong>{formatMoney(payment.deposit_amount)}</strong>
                <small>Còn {formatMoney(booking.total_price - payment.deposit_amount)} trả tại sân</small>
              </button>
              <button type="button" role="radio" aria-checked={option === 'full'} className={`payment-option ${option === 'full' ? 'is-active' : ''}`} onClick={() => setOption('full')}>
                <small>Trả hết</small>
                <strong>{formatMoney(booking.total_price)}</strong>
                <small>Không phải trả thêm khi đến sân</small>
              </button>
            </div>

            {/* VietQR & Transfer Details Grid */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: vietQrUrl ? 'repeat(auto-fit, minmax(280px, 1fr))' : '1fr',
                gap: 16,
                alignItems: 'start',
              }}
            >
              {vietQrUrl && (
                <div
                  style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    padding: 16,
                    borderRadius: 12,
                    background: 'var(--surface-sunken, #f8fafc)',
                    border: '1px solid var(--border)',
                    textAlign: 'center',
                  }}
                >
                  <span
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: 6,
                      fontSize: 13,
                      fontWeight: 700,
                      color: 'var(--pitch, #0b3025)',
                      marginBottom: 10,
                    }}
                  >
                    <QrCode size={16} /> Quét mã VietQR
                  </span>
                  <div
                    style={{
                      background: '#fff',
                      padding: 8,
                      borderRadius: 12,
                      boxShadow: '0 2px 10px rgba(0,0,0,0.06)',
                      display: 'inline-block',
                    }}
                  >
                    <img
                      src={vietQrUrl}
                      alt="Mã VietQR thanh toán"
                      style={{
                        width: '100%',
                        maxWidth: 240,
                        height: 'auto',
                        display: 'block',
                        borderRadius: 6,
                      }}
                    />
                  </div>
                  <p style={{ margin: '10px 0 0', fontSize: 12, color: 'var(--text-muted, #64748b)' }}>
                    Tự động điền số tiền {formatMoney(amount)} & nội dung {payment.code}
                  </p>
                  <button
                    type="button"
                    onClick={handleDownloadQr}
                    disabled={downloadingQr}
                    className="button button--ghost button--small"
                    style={{ marginTop: 8 }}
                  >
                    <Download size={13} /> {downloadingQr ? 'Đang tải...' : 'Tải mã QR'}
                  </button>
                </div>
              )}

              <div>
                <dl className="payment-rows" style={{ borderRadius: 8 }}>
                  {row('Mã đơn', `#BK-${String(booking.id).padStart(5, '0')}`)}
                  {row('Số tiền cần chuyển', formatMoney(amount), 'số tiền', 'payment-rows__amount', String(amount))}
                  {row('Ngân hàng', booking.bank?.bank_name)}
                  {row('Số tài khoản', booking.bank?.account_number, 'số tài khoản')}
                  {row('Chủ tài khoản', booking.bank?.account_holder)}
                  {row('Nội dung chuyển khoản', payment.code, 'nội dung chuyển khoản')}
                </dl>
              </div>
            </div>

            <div className="notice notice--warn" style={{ margin: '10px 0 0', fontSize: 13, lineHeight: 1.5 }}>
              <ShieldAlert size={18} />
              <span>
                <strong>Cảnh báo an toàn:</strong> Chỉ chuyển khoản đúng tài khoản hiển thị ở trên. SportHub tuyệt đối không yêu cầu chuyển qua Zalo/số điện thoại riêng. Mọi giao dịch ngoài nền tảng đều không được bảo vệ.
              </span>
            </div>

            <p className="form-help" style={{ margin: 0 }}>
              Ghi đúng nội dung <strong>{payment.code}</strong> để chủ sân đối chiếu được giao dịch của bạn. Huỷ đơn trong thời hạn
              cho phép thì chủ sân hoàn lại tiền; huỷ sát giờ chơi hoặc vắng mặt thì mất tiền cọc.
            </p>

            {/* Receipt / Proof Upload */}
            <div
              style={{
                padding: 14,
                borderRadius: 10,
                border: '1px dashed var(--border)',
                background: 'var(--surface-sunken, #f8fafc)',
              }}
            >
              <span style={{ fontSize: 13, fontWeight: 600, display: 'block', marginBottom: 6 }}>
                Đính kèm biên lai chuyển khoản (tùy chọn)
              </span>
              <p style={{ margin: '0 0 10px', fontSize: 12, color: 'var(--text-muted, #64748b)' }}>
                Giúp chủ sân đối chiếu và xác nhận nhanh hơn (ảnh chụp màn hình chuyển khoản thành công).
              </p>

              {proofPreview ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  <img
                    src={proofPreview}
                    alt="Xem trước biên lai"
                    style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--border)' }}
                  />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <strong style={{ fontSize: 13, display: 'block', textOverflow: 'ellipsis', overflow: 'hidden', whiteSpace: 'nowrap' }}>
                      {proofFile?.name}
                    </strong>
                    <small style={{ color: 'var(--text-muted)' }}>
                      {(proofFile ? proofFile.size / 1024 : 0).toFixed(0)} KB
                    </small>
                  </div>
                  <button
                    type="button"
                    className="button button--ghost button--icon button--small"
                    onClick={removeProof}
                    title="Xóa ảnh này"
                  >
                    <X size={16} />
                  </button>
                </div>
              ) : (
                <label
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 8,
                    cursor: 'pointer',
                    padding: '8px 14px',
                    borderRadius: 6,
                    border: '1px solid var(--border)',
                    background: '#fff',
                    fontSize: 13,
                    fontWeight: 600,
                  }}
                >
                  <Upload size={14} /> Chọn ảnh biên lai...
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    style={{ display: 'none' }}
                    onChange={handleProofChange}
                  />
                </label>
              )}
            </div>

            <label className="check-line">
              <input type="checkbox" checked={checked} onChange={(event) => setChecked(event.target.checked)} />
              Tôi đã chuyển đúng {formatMoney(amount)} với nội dung {payment.code}
            </label>
            <button type="button" className="button button--brand button--block" disabled={!checked || report.isPending} onClick={() => report.mutate()}>
              {report.isPending ? 'Đang ghi nhận...' : 'Tôi đã chuyển khoản'}
            </button>
          </>
        )}

        {booking.status === 'pending' && payment.status === 'reported' && (
          <>
            {summary}
            <div className="notice notice--soft">
              Bạn đã báo chuyển <strong>{formatMoney(payment.amount ?? 0)}</strong> ({payment.option === 'full' ? 'trả hết' : 'đặt cọc'}) với nội dung{' '}
              <strong>{payment.code}</strong>. Đang chờ chủ sân đối chiếu; trang này sẽ tự cập nhật.
            </div>
          </>
        )}

        {payment.status === 'paid' && (
          <>
            {summary}
            <div className="notice notice--success">
              <CheckCircle2 size={18} /> Chủ sân đã nhận <strong>{formatMoney(payment.amount ?? 0)}</strong>. Đơn đã được xác nhận
              {payment.option === 'deposit' && `, còn ${formatMoney(booking.total_price - (payment.amount ?? 0))} trả tại sân`}.
            </div>
          </>
        )}

        {(booking.status === 'cancelled' || booking.status === 'rejected') && (
          <>
            {summary}
            <div className="notice notice--error">Đơn đã đóng{booking.cancel_reason ? `: ${booking.cancel_reason}` : '.'}</div>
          </>
        )}

        {payment.proof_url && (
          <div style={{ marginTop: 8 }}>
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={() => setShowProofModal(true)}
            >
              <Eye size={14} /> Xem ảnh biên lai đã gửi
            </button>
          </div>
        )}
      </section>

      {/* Payment Proof Viewer */}
      {payment?.proof_url && (
        <PaymentProofModal
          isOpen={showProofModal}
          onClose={() => setShowProofModal(false)}
          proofUrl={payment.proof_url}
        />
      )}
    </>
  )
}
