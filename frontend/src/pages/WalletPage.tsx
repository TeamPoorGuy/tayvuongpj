import { useMutation, useQuery } from '@tanstack/react-query'
import { ArrowDownToLine, ArrowUpFromLine, Clock, Wallet } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { Modal } from '../components/Modal'
import { Pagination } from '../components/Pagination'
import { QueryState } from '../components/QueryState'
import { useToast } from '../components/Toast'
import { useWallet } from '../hooks/useWallet'
import type { ApiEnvelope, Payment, WalletTransaction, Withdrawal, WithdrawalInfo } from '../types/api'
import { VIETNAMESE_BANKS } from '../utils/banks'
import { formatDate, formatDateTime, formatMoney } from '../utils/format'
import { digitsOnly, toBankHolder } from '../utils/input'
import { Empty, PageTitle } from './customer/CustomerPages'

const TYPE_LABELS: Record<WalletTransaction['type'], string> = {
  refund: 'Hoàn tiền',
  topup: 'Nạp ví',
  booking_payment: 'Thanh toán đặt sân',
  booking_payment_return: 'Hoàn số dư giữ chỗ',
  withdrawal: 'Rút về ngân hàng',
  withdrawal_return: 'Hoàn lệnh rút',
  owner_earning: 'Tiền đơn đặt sân',
  owner_adjustment: 'Điều chỉnh đơn',
}

const WITHDRAW_STATUS: Record<Withdrawal['status'], { label: string; className: string }> = {
  pending: { label: 'Chờ chuyển khoản', className: 'status status--pending' },
  paid: { label: 'Đã chuyển', className: 'status status--approved' },
  rejected: { label: 'Bị từ chối', className: 'status status--rejected' },
}

const QUICK_TOPUPS = [100000, 200000, 500000, 1000000]

/** Ví SportHub: số dư, nạp tiền (quét QR), rút về ngân hàng và lịch sử. Dùng chung cho khách và chủ sân. */
export function WalletPage({ owner = false }: { owner?: boolean }) {
  const toast = useToast()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [topupOpen, setTopupOpen] = useState(false)
  const [withdrawOpen, setWithdrawOpen] = useState(false)
  const [topupAmount, setTopupAmount] = useState('200000')
  const [amount, setAmount] = useState('')
  const [bankBin, setBankBin] = useState('')
  const [accountNumber, setAccountNumber] = useState('')
  const [accountHolder, setAccountHolder] = useState('')

  const wallet = useWallet(page)
  const info = useQuery({
    queryKey: ['wallet', 'withdrawals'],
    refetchInterval: 30_000,
    queryFn: async () => (await api.get<WithdrawalInfo>('/wallet/withdrawals')).data,
  })

  const balance = wallet.data?.data.balance ?? 0
  const bankOf = (bin?: string) => VIETNAMESE_BANKS.find((b) => b.bin === bin)
  const hasPending = info.data?.data.some((w) => w.status === 'pending') ?? false

  const topup = useMutation({
    mutationFn: async () => (await api.post<ApiEnvelope<Payment>>('/wallet/topups', { amount: Number(topupAmount) })).data.data,
    onSuccess: (payment) => navigate(`/payments/${payment.id}`),
    onError: (err) => toast.error(errorMessage(err), 'Không tạo được lệnh nạp'),
  })

  const withdraw = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ message: string }>('/wallet/withdrawals', {
          amount: Number(amount),
          ...(info.data?.bank_locked ? {} : { bank_bin: bankBin, account_number: accountNumber, account_holder: accountHolder }),
        })
      ).data,
    onSuccess: (res) => {
      toast.success(res.message)
      setWithdrawOpen(false)
      setAmount('')
    },
    onError: (err) => toast.error(errorMessage(err), 'Không rút được tiền'),
  })

  const openWithdraw = () => {
    const saved = info.data?.saved_bank
    setBankBin(saved?.bank_bin ?? '')
    setAccountNumber(saved?.account_number ?? '')
    setAccountHolder(saved?.account_holder ?? '')
    setAmount(String(Math.floor(balance)))
    setWithdrawOpen(true)
  }

  const submitWithdraw = (e: FormEvent) => {
    e.preventDefault()
    withdraw.mutate()
  }

  const autoLimit = info.data?.auto_limit ?? 0
  const minAmount = info.data?.min_amount ?? 50000
  const withdrawAmount = Number(amount)
  const goesToAdmin = withdrawAmount > autoLimit

  return (
    <>
      <PageTitle
        icon={<Wallet />}
        kicker="Tài chính"
        title="Ví SportHub"
        copy={owner ? 'Tiền thuê sân sau khi trừ hoàn tiền và hoa hồng tự về ví khi đơn qua 48 giờ không có khiếu nại. Rút về tài khoản trong hồ sơ cơ sở bất cứ lúc nào.' : 'Tiền hoàn về thẳng ví, dùng để đặt sân lần sau hoặc rút về ngân hàng.'}
      />

      <section className="data-panel" style={{ marginBottom: 24 }}>
        <div className="data-panel__body">
          <div className="stats-grid-3" style={{ marginBottom: 0 }}>
            <div className="stat-card stat-card--highlight">
              <span className="stat-card__label">Số dư ví</span>
              <strong className="stat-card__val">{formatMoney(balance)}</strong>
              <small className="stat-card__note">{balance < 0 ? 'Ví đang âm do hoàn tiền sau khi nhận; chưa rút được.' : 'Dùng được ngay'}</small>
            </div>
            {owner && (
              <div className="stat-card">
                <span className="stat-card__label">Sắp về ví</span>
                <strong className="stat-card__val">{formatMoney(wallet.data?.data.pending_earnings ?? 0)}</strong>
                <small className="stat-card__note"><Clock size={12} /> Đơn đã thanh toán, chờ hết hạn khiếu nại 48 giờ</small>
              </div>
            )}
            <div className="stat-card" style={{ display: 'grid', gap: 8, alignContent: 'center' }}>
              {!owner && (
                <button type="button" className="button button--brand" onClick={() => setTopupOpen(true)}>
                  <ArrowDownToLine size={16} /> Nạp tiền
                </button>
              )}
              <button type="button" className="button button--ghost" disabled={balance < minAmount || hasPending} onClick={openWithdraw} title={hasPending ? 'Đang có lệnh rút chờ xử lý' : undefined}>
                <ArrowUpFromLine size={16} /> Rút về ngân hàng
              </button>
              {hasPending && <small className="text-muted">Đang có một lệnh rút chờ xử lý.</small>}
            </div>
          </div>
        </div>
      </section>

      {(info.data?.data.length ?? 0) > 0 && (
        <section className="data-panel" style={{ marginBottom: 24 }}>
          <div className="data-panel__head"><div><span>Rút tiền</span><h2>Lệnh rút gần đây</h2></div></div>
          <div className="table-wrap">
            <table>
              <thead><tr><th>Thời gian</th><th>Số tiền</th><th>Về tài khoản</th><th>Trạng thái</th></tr></thead>
              <tbody>
                {info.data?.data.map((w) => (
                  <tr key={w.id}>
                    <td>{formatDateTime(w.created_at)}</td>
                    <td><strong>{formatMoney(w.amount)}</strong></td>
                    <td>{w.bank.name} · {w.bank.account_number}<br /><small>{w.bank.account_holder}</small></td>
                    <td>
                      <span className={WITHDRAW_STATUS[w.status].className}><i />{WITHDRAW_STATUS[w.status].label}</span>
                      {w.auto && w.status === 'paid' && <small style={{ display: 'block' }}>Tự động duyệt</small>}
                      {w.reason && <small style={{ display: 'block' }}>{w.reason}</small>}
                      {w.reference && <small style={{ display: 'block' }}>GD: {w.reference}</small>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      <section className="data-panel">
        <div className="data-panel__head"><div><span>Lịch sử</span><h2>Biến động số dư</h2></div></div>
        <QueryState
          isPending={wallet.isPending}
          isError={wallet.isError}
          isEmpty={(wallet.data?.data.transactions.length ?? 0) === 0}
          empty={<Empty text="Chưa có giao dịch ví nào." />}
        >
          <div className="table-wrap">
            <table>
              <thead><tr><th>Thời gian</th><th>Nội dung</th><th>Số tiền</th><th>Số dư sau</th></tr></thead>
              <tbody>
                {wallet.data?.data.transactions.map((t) => (
                  <tr key={t.id}>
                    <td>{formatDate(t.created_at)}<br /><small>{formatDateTime(t.created_at).slice(0, 5)}</small></td>
                    <td><strong>{TYPE_LABELS[t.type]}</strong><br /><small>{t.description}</small></td>
                    <td><strong style={{ color: t.amount < 0 ? 'var(--danger, #b91c1c)' : 'var(--pitch)' }}>{t.amount > 0 ? '+' : ''}{formatMoney(t.amount)}</strong></td>
                    <td>{formatMoney(t.balance_after)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} lastPage={wallet.data?.meta.last_page ?? 1} total={wallet.data?.meta.total} onPageChange={setPage} />
        </QueryState>
      </section>

      <Modal isOpen={topupOpen} onClose={() => setTopupOpen(false)} title="Nạp tiền vào ví" asForm onSubmit={(e) => { e.preventDefault(); topup.mutate() }}>
        <div className="form-stack" style={{ maxWidth: 420 }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
            {QUICK_TOPUPS.map((v) => (
              <button key={v} type="button" className={`button button--small ${Number(topupAmount) === v ? 'button--brand' : 'button--ghost'}`} onClick={() => setTopupAmount(String(v))}>
                {formatMoney(v)}
              </button>
            ))}
          </div>
          <label>
            <span>Số tiền nạp (tối thiểu 10.000đ)</span>
            <input inputMode="numeric" required value={topupAmount} onChange={(e) => setTopupAmount(digitsOnly(e.target.value, 9))} />
          </label>
          <p className="form-help" style={{ margin: 0 }}>Bạn sẽ được chuyển tới mã QR chuyển khoản vào tài khoản SportHub; tiền tự cộng vào ví khi ngân hàng báo có.</p>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
            <button type="button" className="button button--ghost" onClick={() => setTopupOpen(false)}>Đóng</button>
            <button type="submit" className="button button--brand" disabled={topup.isPending || Number(topupAmount) < 10000}>{topup.isPending ? 'Đang tạo...' : 'Tiếp tục'}</button>
          </div>
        </div>
      </Modal>

      <Modal isOpen={withdrawOpen} onClose={() => setWithdrawOpen(false)} title="Rút tiền về ngân hàng" asForm onSubmit={submitWithdraw}>
        <div className="form-stack" style={{ maxWidth: 440 }}>
          <label>
            <span>Số tiền rút (tối thiểu {formatMoney(minAmount)}, tối đa {formatMoney(balance)})</span>
            <input inputMode="numeric" required value={amount} onChange={(e) => setAmount(digitsOnly(e.target.value, 9))} />
          </label>

          {info.data?.bank_locked ? (
            <div className="notice notice--success" style={{ fontSize: 13, lineHeight: 1.5 }}>
              Rút về tài khoản trong hồ sơ cơ sở: <strong>{bankOf(info.data.saved_bank?.bank_bin)?.short_name ?? info.data.saved_bank?.bank_bin}</strong> · {info.data.saved_bank?.account_number} · {info.data.saved_bank?.account_holder}
            </div>
          ) : (
            <>
              <label>
                <span>Ngân hàng</span>
                <select required value={bankBin} onChange={(e) => setBankBin(e.target.value)}>
                  <option value="">Chọn ngân hàng</option>
                  {VIETNAMESE_BANKS.map((b) => <option key={b.bin} value={b.bin}>{b.short_name} — {b.name}</option>)}
                </select>
              </label>
              <label>
                <span>Số tài khoản</span>
                <input inputMode="numeric" required minLength={6} value={accountNumber} onChange={(e) => setAccountNumber(digitsOnly(e.target.value, 20))} />
              </label>
              <label>
                <span>Tên chủ tài khoản (in hoa, không dấu)</span>
                <input required value={accountHolder} onChange={(e) => setAccountHolder(toBankHolder(e.target.value))} placeholder="NGUYEN VAN A" />
              </label>
            </>
          )}

          <p className="form-help" style={{ margin: 0 }}>
            {goesToAdmin
              ? `Lệnh trên ${formatMoney(autoLimit)} cần Ban quản trị chuyển khoản. Tiền được giữ khỏi ví ngay, bị từ chối sẽ hoàn lại.`
              : 'Lệnh này được duyệt tự động và chuyển ngay.'}
          </p>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
            <button type="button" className="button button--ghost" onClick={() => setWithdrawOpen(false)}>Đóng</button>
            <button type="submit" className="button button--brand" disabled={withdraw.isPending || withdrawAmount < minAmount || withdrawAmount > balance}>
              {withdraw.isPending ? 'Đang gửi...' : 'Rút tiền'}
            </button>
          </div>
        </div>
      </Modal>
    </>
  )
}
