import { useMutation, useQuery } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2, Copy, CreditCard, Download, QrCode, ShieldAlert, Timer } from 'lucide-react'
import { Fragment, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { useToast } from '../../components/Toast'
import { usePublicSettings } from '../../hooks/usePublicSettings'
import type { ApiEnvelope, Payment } from '../../types/api'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'
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
    <span className={`countdown-chip ${seconds < 300 ? 'countdown-chip--danger' : ''}`}>
      <Timer size={13} />{' '}
      {seconds > 0 ? `Giữ chỗ còn ${Math.floor(seconds / 60)}:${pad(seconds % 60)}` : 'Đã hết thời gian giữ chỗ'}
    </span>
  )
}

/** Trang chuyển khoản cho một lượt thanh toán (có thể gồm nhiều ô / nhiều sân / nhiều tuần). */
export function BookingPaymentPage() {
  const { id } = useParams()
  const toast = useToast()
  const { data: settings } = usePublicSettings()
  const [confirmCancel, setConfirmCancel] = useState(false)
  const [downloadingQr, setDownloadingQr] = useState(false)

  const query = useQuery({
    queryKey: ['customer', 'payment', id],
    queryFn: async () => (await api.get<ApiEnvelope<Payment>>(`/customer/payments/${id}`)).data.data,
    // Chờ ngân hàng báo có tiền: tự làm mới để đơn chuyển sang "đã xác nhận" mà không cần tải lại trang.
    refetchInterval: (state) => (state.state.data?.status === 'awaiting' ? 5_000 : false),
  })

  const changeOption = useMutation({
    mutationFn: async (option: 'deposit' | 'full') => api.patch(`/customer/payments/${id}`, { option }),
    onSuccess: () => void query.refetch(),
    onError: (err) => toast.error(errorMessage(err), 'Không đổi được hình thức thanh toán'),
  })

  const cancel = useMutation({
    mutationFn: async () => api.post(`/customer/payments/${id}/cancel`),
    onSuccess: () => {
      toast.success('Đã huỷ giữ chỗ.')
      setConfirmCancel(false)
      void query.refetch()
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể huỷ giữ chỗ'),
  })

  const copy = async (value: string, label: string) => {
    try {
      await navigator.clipboard.writeText(value)
      toast.success(`Đã sao chép ${label}.`)
    } catch {
      toast.error('Trình duyệt không cho sao chép, hãy chọn và sao chép thủ công.')
    }
  }

  const payment = query.data
  const title = (
    <PageTitle
      icon={<CreditCard />}
      kicker="Thanh toán"
      title="Chuyển khoản giữ sân"
      copy="Chuyển khoản vào tài khoản SportHub đúng số tiền và nội dung. Hệ thống tự xác nhận đơn khi nhận được tiền."
      action={
        <Link to="/bookings" className="button button--ghost">
          <ArrowLeft size={16} /> Đơn đặt của tôi
        </Link>
      }
    />
  )

  if (query.isPending) return <>{title}<div className="query-state query-state--loading">Đang tải thông tin thanh toán...</div></>
  if (query.isError || !payment) {
    return <>{title}<div className="notice notice--error" role="alert">{errorMessage(query.error) || 'Không tìm thấy lượt thanh toán.'}</div></>
  }

  const bookings = payment.bookings ?? []
  const depositAmount = Math.min(Math.ceil((payment.total * payment.deposit_percent) / 100 / 1000) * 1000, payment.total)

  const handleDownloadQr = async () => {
    if (!payment.bank.qr_url) return
    try {
      setDownloadingQr(true)
      const blob = await (await fetch(payment.bank.qr_url)).blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `vietqr-${payment.code}.png`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      window.open(payment.bank.qr_url, '_blank')
    } finally {
      setDownloadingQr(false)
    }
  }

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
      {bookings.map((b) => (
        <Fragment key={b.id}>
          {row(`${b.field?.name ?? 'Sân'} · ${formatDate(b.booking_date)}`, `${b.time_slot?.start_time} – ${b.time_slot?.end_time} · ${formatMoney(b.total_price)}`)}
        </Fragment>
      ))}
      {row('Tổng tiền thuê sân', formatMoney(payment.total))}
    </dl>
  )

  return (
    <>
      {title}
      <section className="content-card payment-card" style={{ maxWidth: 760 }}>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
          <strong>Mã thanh toán {payment.code}</strong>
          {payment.status === 'awaiting' && <Countdown until={payment.due_at} />}
        </div>

        {summary}

        {payment.status === 'awaiting' && (
          <>
            <div className="payment-options" role="radiogroup" aria-label="Hình thức thanh toán">
              <button type="button" role="radio" aria-checked={payment.option === 'deposit'} disabled={changeOption.isPending} className={`payment-option ${payment.option === 'deposit' ? 'is-active' : ''}`} onClick={() => changeOption.mutate('deposit')}>
                <small>Đặt cọc {payment.deposit_percent}%</small>
                <strong>{formatMoney(depositAmount)}</strong>
                <small>Còn {formatMoney(payment.total - depositAmount)} trả tại sân</small>
              </button>
              <button type="button" role="radio" aria-checked={payment.option === 'full'} disabled={changeOption.isPending} className={`payment-option ${payment.option === 'full' ? 'is-active' : ''}`} onClick={() => changeOption.mutate('full')}>
                <small>Trả hết</small>
                <strong>{formatMoney(payment.total)}</strong>
                <small>Không phải trả thêm khi đến sân</small>
              </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: payment.bank.qr_url ? 'repeat(auto-fit, minmax(260px, 1fr))' : '1fr', gap: 16, alignItems: 'start' }}>
              {payment.bank.qr_url && (
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', padding: 16, borderRadius: 12, background: 'var(--surface-sunken, #f8fafc)', border: '1px solid var(--border)', textAlign: 'center' }}>
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, fontWeight: 700, color: 'var(--pitch, #0b3025)', marginBottom: 10 }}>
                    <QrCode size={16} /> Quét mã VietQR
                  </span>
                  <img src={payment.bank.qr_url} alt="Mã VietQR thanh toán" style={{ width: '100%', maxWidth: 240, height: 'auto', display: 'block', borderRadius: 6, background: '#fff' }} />
                  <p style={{ margin: '10px 0 0', fontSize: 12, color: 'var(--muted)' }}>
                    Tự điền số tiền {formatMoney(payment.amount)} và nội dung {payment.code}
                  </p>
                  <button type="button" onClick={() => void handleDownloadQr()} disabled={downloadingQr} className="button button--ghost button--small" style={{ marginTop: 8 }}>
                    <Download size={13} /> {downloadingQr ? 'Đang tải...' : 'Tải mã QR'}
                  </button>
                </div>
              )}

              <dl className="payment-rows" style={{ borderRadius: 8 }}>
                {row('Số tiền cần chuyển', formatMoney(payment.amount), 'số tiền', 'payment-rows__amount', String(payment.amount))}
                {row('Ngân hàng', payment.bank.name)}
                {row('Số tài khoản', payment.bank.account_number, 'số tài khoản')}
                {row('Chủ tài khoản', payment.bank.account_holder)}
                {row('Nội dung chuyển khoản', payment.code, 'nội dung chuyển khoản')}
              </dl>
            </div>

            <div className="notice notice--warn" style={{ margin: '10px 0 0', fontSize: 13, lineHeight: 1.5 }}>
              <ShieldAlert size={18} />
              <span>
                <strong>Chỉ chuyển vào tài khoản SportHub ở trên.</strong> SportHub giữ tiền và chuyển cho chủ sân sau khi trận đấu diễn ra.
                Không chuyển thẳng cho chủ sân hay qua Zalo/số điện thoại riêng — giao dịch ngoài nền tảng không được bảo vệ hoặc hoàn tiền.
              </span>
            </div>

            <p className="form-help" style={{ margin: 0 }}>
              Ghi đúng nội dung <strong>{payment.code}</strong>; đơn được xác nhận tự động trong vài giây sau khi tiền vào. Huỷ đơn đã thanh toán:
              trước {settings?.refund_full_hours ?? 24} giờ hoàn 100%, trước {settings?.refund_partial_hours ?? 6} giờ hoàn{' '}
              {settings?.refund_partial_percent ?? 50}%, sát giờ hơn thì không hoàn.
            </p>

            <button type="button" className="button button--ghost" disabled={cancel.isPending} onClick={() => setConfirmCancel(true)}>
              Huỷ giữ chỗ
            </button>
          </>
        )}

        {payment.status === 'paid' && (
          <div className="notice notice--success">
            <CheckCircle2 size={18} /> SportHub đã nhận <strong>{formatMoney(payment.amount)}</strong> lúc {payment.paid_at ? formatDateTime(payment.paid_at) : ''}.
            Đơn đã được xác nhận{payment.option === 'deposit' && `, còn ${formatMoney(payment.total - payment.amount)} trả tại sân`}.
          </div>
        )}

        {payment.status === 'refund_required' && (
          <div className="notice notice--warn">
            SportHub đã nhận tiền nhưng giữ chỗ đã hết hạn và sân đã có người khác đặt. Khoản tiền sẽ được hoàn lại cho bạn; bạn có thể theo dõi ở mục Đơn đặt của tôi.
          </div>
        )}

        {(payment.status === 'expired' || payment.status === 'cancelled') && (
          <div className="notice notice--error">
            {payment.status === 'expired' ? 'Đã hết thời gian giữ chỗ nên đơn tự huỷ.' : 'Bạn đã huỷ giữ chỗ.'} Nếu bạn lỡ chuyển khoản, hãy liên hệ hỗ trợ kèm mã {payment.code}.
          </div>
        )}
      </section>

      <ConfirmModal
        isOpen={confirmCancel}
        onClose={() => setConfirmCancel(false)}
        onConfirm={() => cancel.mutate()}
        title="Huỷ giữ chỗ?"
        message={`Toàn bộ ${bookings.length} khung giờ trong lượt này sẽ được nhả cho người khác đặt.`}
        confirmText="Huỷ giữ chỗ"
        danger
        busy={cancel.isPending}
      />
    </>
  )
}
