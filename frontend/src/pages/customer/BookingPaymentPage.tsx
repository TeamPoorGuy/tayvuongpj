import { useMutation, useQuery } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2, Copy, CreditCard, ShieldAlert, Timer } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
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

  const query = useQuery({
    queryKey: ['customer', 'booking', id],
    queryFn: async () => (await api.get<ApiEnvelope<Booking>>(`/customer/bookings/${id}`)).data.data,
    // Đang chờ chủ sân duyệt/đối chiếu: tự làm mới để thấy kết quả mà không cần tải lại trang.
    refetchInterval: (state) => (state.state.data?.status === 'pending' ? 30_000 : false),
  })

  const report = useMutation({
    mutationFn: async () => api.post(`/customer/bookings/${id}/payment`, { option }),
    onSuccess: () => toast.success('Đã ghi nhận. Chủ sân sẽ đối chiếu và xác nhận.', 'Đã báo chuyển khoản'),
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
      <section className="content-card payment-card">
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

            <dl className="payment-rows">
              {row('Mã đơn', `#BK-${String(booking.id).padStart(5, '0')}`)}
              {row('Số tiền cần chuyển', formatMoney(amount), 'số tiền', 'payment-rows__amount', String(amount))}
              {row('Ngân hàng', booking.bank?.bank_name)}
              {row('Số tài khoản', booking.bank?.account_number, 'số tài khoản')}
              {row('Chủ tài khoản', booking.bank?.account_holder)}
              {row('Nội dung chuyển khoản', payment.code, 'nội dung chuyển khoản')}
            </dl>

            <div className="notice notice--warn" style={{ margin: '14px 0', fontSize: 13, lineHeight: 1.5 }}>
              <ShieldAlert size={18} />
              <span>
                <strong>Cảnh báo an toàn:</strong> Chỉ chuyển khoản đúng tài khoản hiển thị ở trên. SportHub tuyệt đối không yêu cầu chuyển qua Zalo/số điện thoại riêng. Mọi giao dịch ngoài nền tảng đều không được bảo vệ.
              </span>
            </div>

            <p className="form-help">
              Ghi đúng nội dung <strong>{payment.code}</strong> để chủ sân đối chiếu được giao dịch của bạn. Huỷ đơn trong thời hạn
              cho phép thì chủ sân hoàn lại tiền; huỷ sát giờ chơi hoặc vắng mặt thì mất tiền cọc.
            </p>
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
      </section>
    </>
  )
}
