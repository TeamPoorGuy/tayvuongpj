import { digitsOnly } from '../utils/input'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FocusEvent, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate, useSearchParams } from 'react-router'
import { motion } from 'framer-motion'
import { ArrowRight, LockKeyhole, Mail, ShieldCheck } from 'lucide-react'
import { api } from '../api/client'
import { errorMessage, fieldErrors } from '../api/errors'
import { Brand } from '../components/Brand'
import { useAuth } from '../context/auth-context'
import type { User } from '../types/api'

const googleUrl = `${import.meta.env.VITE_BACKEND_URL ?? 'http://127.0.0.1:8011'}/auth/google/redirect`

function GoogleButton() {
  return <>
    <a className="google-btn" href={googleUrl}>
      <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/><path fill="#FBBC05" d="M10.5 28.7A14.5 14.500 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.600 10.8l7.900-6.1z"/><path fill="#34A853" d="M24 48c6.500 0 11.9-2.1 15.900-5.800l-7.500-5.800c-2.1 1.400-4.800 2.300-8.400 2.300-6.300 0-11.600-4.100-13.500-9.800l-7.900 6.100C6.500 42.600 14.600 48 24 48z"/></svg>
      Tiếp tục với Google
    </a>
    <small style={{ display: 'block', textAlign: 'center', margin: '6px 0 12px', fontSize: 11, color: 'var(--muted)', lineHeight: 1.4 }}>
      Bằng việc tiếp tục với Google, bạn đồng ý với{' '}
      <Link to="/dieu-khoan" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>Điều khoản</Link>
      {' '}và{' '}
      <Link to="/chinh-sach-bao-mat" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>Bảo mật</Link>
      {' '}của SportHub.
    </small>
    <div className="or-divider">hoặc</div>
  </>
}

const destination = (user: User) => {
  if (user.role === 'admin') return '/admin'
  return localStorage.getItem('sporthub:last-mode') === 'owner' && user.field_owner_profile?.verification_status === 'approved'
    ? '/owner'
    : '/bookings'
}

function sanitizeRedirect(target?: string | null): string | null {
  if (!target) return null
  if (target.startsWith('/') && !target.startsWith('//') && !target.includes('\\')) {
    return target
  }
  return null
}

export function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()
  const fromParam = sanitizeRedirect(params.get('from') || (location.state as { from?: string })?.from)
  const urlError = params.get('error') === 'disabled' ? 'Tài khoản của bạn đã bị khóa.' : params.get('error') ? 'Đăng nhập Google không thành công. Vui lòng thử lại.' : ''
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  // Đăng nhập xong phải quay về trang khách đang đặt dở (from), không phải trang mặc định của vai trò.
  if (user) return <Navigate to={fromParam ?? destination(user)} replace />

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setBusy(true); setError('')
    const values = new FormData(event.currentTarget)
    try { const next = await login({ email: String(values.get('email')), password: String(values.get('password')), remember: values.get('remember') === 'on' }); navigate(fromParam ?? destination(next)) }
    catch (reason) { setError(errorMessage(reason)) } finally { setBusy(false) }
  }

  return <AuthShell title="Chào mừng trở lại" copy="Đăng nhập để quản lý lịch chơi và sân của bạn."><form onSubmit={submit} className="form-stack"><GoogleButton /><label><span>Email</span><div className="input-icon"><Mail /><input name="email" type="email" required autoComplete="email" placeholder="ban@email.com" /></div></label><label><span>Mật khẩu</span><div className="input-icon"><LockKeyhole /><input name="password" type="password" required autoComplete="current-password" placeholder="Nhập mật khẩu" /></div></label><div className="form-row-between"><label className="check-line"><input name="remember" type="checkbox" /> Ghi nhớ đăng nhập</label><Link className="form-link" to="/forgot-password">Quên mật khẩu?</Link></div>{(error || urlError) && <div className="notice notice--error">{error || urlError}</div>}<button disabled={busy} className="button button--brand button--block">{busy ? 'Đang đăng nhập...' : 'Đăng nhập'} <ArrowRight size={16} /></button><p className="form-switch">Chưa có tài khoản? <Link to={`/register${fromParam ? `?from=${encodeURIComponent(fromParam)}` : ''}`} state={{ from: fromParam }}>Đăng ký ngay</Link></p></form></AuthShell>
}

/** Thông báo cho từng lỗi kiểm tra của trình duyệt; lỗi pattern lấy từ thuộc tính title của ô. */
const REGISTER_REQUIRED: Record<string, string> = {
  name: 'Vui lòng nhập họ và tên.',
  phone: 'Vui lòng nhập số điện thoại.',
  email: 'Vui lòng nhập email.',
  password: 'Vui lòng nhập mật khẩu.',
  password_confirmation: 'Vui lòng nhập lại mật khẩu.',
}

function registerFieldMessage(form: HTMLFormElement, el: HTMLInputElement): string {
  const { validity } = el
  if (validity.valueMissing) return REGISTER_REQUIRED[el.name] ?? 'Vui lòng điền ô này.'
  if (validity.typeMismatch) return 'Email chưa đúng định dạng.'
  if (validity.tooShort) return 'Mật khẩu tối thiểu 8 ký tự.'
  if (validity.patternMismatch) return el.title || 'Giá trị chưa đúng định dạng.'
  if (el.name === 'password_confirmation' && el.value !== (form.elements.namedItem('password') as HTMLInputElement).value) {
    return 'Mật khẩu xác nhận không khớp.'
  }
  return ''
}

export function RegisterPage() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()
  const fromParam = sanitizeRedirect(params.get('from') || (location.state as { from?: string })?.from)
  const [error, setError] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  const [termsAccepted, setTermsAccepted] = useState(false)
  if (user) return <Navigate to={fromParam ?? destination(user)} replace />

  const setFieldError = (name: string, message: string) =>
    setErrors((prev) => {
      const next = { ...prev }
      if (message) next[name] = message
      else delete next[name]
      return next
    })

  // Thuộc tính chung cho mỗi ô: kiểm tra khi rời ô, viền đỏ, aria để trình đọc màn hình đọc được lỗi.
  const field = (name: string) => ({
    name,
    id: `reg-${name}`,
    'aria-invalid': errors[name] ? (true as const) : undefined,
    'aria-describedby': errors[name] ? `reg-${name}-error` : undefined,
    className: errors[name] ? 'input--invalid' : undefined,
    onBlur: (event: FocusEvent<HTMLInputElement>) => {
      const el = event.currentTarget
      if (el.value === '' && !el.required) return setFieldError(name, '')
      // Ô chưa động tới thì không báo "bắt buộc" ngay khi chỉ lướt qua; chỉ báo khi đã có nhập hoặc khi bấm gửi.
      if (el.value === '' && el.validity.valueMissing) return
      setFieldError(name, registerFieldMessage(el.form as HTMLFormElement, el))
    },
    onInput: () => errors[name] && setFieldError(name, ''),
  })
  const errorText = (name: string) =>
    errors[name] ? <span id={`reg-${name}-error`} className="input-error" role="alert">{errors[name]}</span> : null

  const focusFirstInvalid = (form: HTMLFormElement, found: Record<string, string>) => {
    const first = [...form.elements].find((el) => el instanceof HTMLInputElement && found[el.name]) as HTMLInputElement | undefined
    first?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    first?.focus({ preventScroll: true })
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    setError('')

    // Tự kiểm tra tất cả ô (form có noValidate) rồi cuộn tới ô sai đầu tiên.
    const found: Record<string, string> = {}
    for (const el of form.elements) {
      if (el instanceof HTMLInputElement && el.name in REGISTER_REQUIRED) {
        const message = registerFieldMessage(form, el)
        if (message) found[el.name] = message
      }
    }
    if (!termsAccepted) found.terms_accepted = 'Bạn cần đồng ý với Điều khoản dịch vụ và Chính sách bảo mật.'
    setErrors(found)
    if (Object.keys(found).length > 0) {
      focusFirstInvalid(form, found)
      return
    }

    setBusy(true)
    const values = Object.fromEntries(new FormData(form).entries())
    values.terms_accepted = '1'
    try { const next = await register(values); navigate(fromParam ?? destination(next)) }
    catch (reason) {
      // Lỗi từ máy chủ gắn đúng ô; lỗi khác (mạng, quá số lần thử...) hiện chung bên dưới.
      const server = fieldErrors(reason)
      if (Object.keys(server).length > 0) { setErrors(server); focusFirstInvalid(form, server) }
      else setError(errorMessage(reason))
    } finally { setBusy(false) }
  }

  return (
    <AuthShell title="Tạo tài khoản SportHub" copy="Bắt đầu hành trình thể thao của bạn.">
      <form onSubmit={submit} noValidate className="form-stack">
        <GoogleButton />
        <div className="form-grid">
          <label><span>Họ và tên</span><input {...field('name')} required maxLength={100} pattern="[\p{L}\s'.\-]+" title="Họ tên chỉ gồm chữ cái, không chứa chữ số hoặc ký tự đặc biệt." autoComplete="name" placeholder="Nguyễn Văn A" />{errorText('name')}</label>
          <label><span>Số điện thoại</span><input {...field('phone')} type="tel" inputMode="numeric" required autoComplete="tel" placeholder="0901234567" maxLength={11} pattern="0[0-9]{9,10}" onInput={(e) => { e.currentTarget.value = digitsOnly(e.currentTarget.value, 11); if (errors.phone) setFieldError('phone', '') }} title="Số điện thoại gồm 10–11 chữ số và bắt đầu bằng 0." />{errorText('phone')}</label>
        </div>
        <label><span>Email</span><input {...field('email')} type="email" required autoComplete="email" placeholder="ban@email.com" />{errorText('email')}</label>
        <label><span>Địa chỉ <small className="optional">(không bắt buộc)</small></span><input name="address" autoComplete="street-address" placeholder="Số nhà, đường, quận/huyện" /></label>
        <div className="form-grid">
          <label><span>Mật khẩu</span><input {...field('password')} type="password" required minLength={8} maxLength={72} pattern="(?=.*[A-Za-z])(?=.*\d).{8,72}" title="Mật khẩu từ 8 ký tự, gồm cả chữ và số." autoComplete="new-password" placeholder="Tối thiểu 8 ký tự, gồm chữ và số" />{errorText('password')}</label>
          <label><span>Xác nhận mật khẩu</span><input {...field('password_confirmation')} type="password" required minLength={8} autoComplete="new-password" placeholder="Nhập lại mật khẩu" />{errorText('password_confirmation')}</label>
        </div>
        <label className="check-line" style={{ display: 'flex', alignItems: 'flex-start', gap: 8, fontSize: 13, marginTop: 4 }}>
          <input
            id="reg-terms_accepted"
            name="terms_accepted"
            type="checkbox"
            checked={termsAccepted}
            aria-invalid={errors.terms_accepted ? true : undefined}
            aria-describedby={errors.terms_accepted ? 'reg-terms_accepted-error' : undefined}
            onChange={(e) => { setTermsAccepted(e.target.checked); if (e.target.checked) setFieldError('terms_accepted', '') }}
            style={{ marginTop: 2 }}
          />
          <span style={{ lineHeight: 1.4 }}>
            Tôi đã đọc và đồng ý với{' '}
            <Link to="/dieu-khoan" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>
              Điều khoản dịch vụ
            </Link>{' '}
            và{' '}
            <Link to="/chinh-sach-bao-mat" target="_blank" style={{ color: 'var(--pitch)', textDecoration: 'underline' }}>
              Chính sách bảo mật
            </Link>{' '}
            của SportHub.
          </span>
        </label>
        {errors.terms_accepted && <span id="reg-terms_accepted-error" className="input-error" role="alert">{errors.terms_accepted}</span>}
        {error && <div className="notice notice--error" role="alert">{error}</div>}
        <button disabled={busy} className="button button--brand button--block">
          {busy ? 'Đang tạo...' : 'Tạo tài khoản'} <ArrowRight size={16} />
        </button>
        <p className="form-switch">Đã có tài khoản? <Link to={`/login${fromParam ? `?from=${encodeURIComponent(fromParam)}` : ''}`} state={{ from: fromParam }}>Đăng nhập</Link></p>
      </form>
    </AuthShell>
  )
}

export function ForgotPasswordPage() {
  const send = useMutation({ mutationFn: (email: string) => api.post('/auth/forgot-password', { email }) })
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); send.mutate(String(new FormData(event.currentTarget).get('email'))) }
  return <AuthShell title="Quên mật khẩu" copy="Nhập email, chúng tôi sẽ gửi liên kết đặt lại mật khẩu."><form onSubmit={submit} className="form-stack"><label><span>Email</span><div className="input-icon"><Mail /><input name="email" type="email" required placeholder="ban@email.com" /></div></label>{send.isSuccess && <div className="notice notice--success">{send.data.data.message}</div>}{send.isError && <div className="notice notice--error">{errorMessage(send.error)}</div>}<button disabled={send.isPending} className="button button--brand button--block">{send.isPending ? 'Đang gửi...' : 'Gửi liên kết'} <ArrowRight size={16} /></button><p className="form-switch"><Link to="/login">Quay lại đăng nhập</Link></p></form></AuthShell>
}

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const reset = useMutation({ mutationFn: (payload: Record<string, FormDataEntryValue>) => api.post('/auth/reset-password', { ...payload, token: params.get('token'), email: params.get('email') }) })
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); reset.mutate(Object.fromEntries(new FormData(event.currentTarget).entries())) }
  if (!params.get('token') || !params.get('email')) return <AuthShell title="Liên kết không hợp lệ" copy="Liên kết đặt lại mật khẩu thiếu thông tin hoặc đã bị cắt ngắn."><div className="form-stack"><div className="notice notice--error">Không thể đặt lại mật khẩu từ liên kết này.</div><Link className="button button--brand button--block" to="/forgot-password">Yêu cầu liên kết mới</Link></div></AuthShell>
  return <AuthShell title="Đặt lại mật khẩu" copy={`Tạo mật khẩu mới cho ${params.get('email') ?? 'tài khoản của bạn'}.`}>{reset.isSuccess ? <div className="form-stack"><div className="notice notice--success">{reset.data.data.message}</div><Link className="button button--brand button--block" to="/login">Đăng nhập</Link></div> : <form onSubmit={submit} className="form-stack"><label><span>Mật khẩu mới</span><input name="password" type="password" required /></label><label><span>Xác nhận mật khẩu</span><input name="password_confirmation" type="password" required /></label>{reset.isError && <div className="notice notice--error">{errorMessage(reset.error)}</div>}<button disabled={reset.isPending} className="button button--brand button--block">{reset.isPending ? 'Đang lưu...' : 'Đặt lại mật khẩu'}</button></form>}</AuthShell>
}

export function EmailVerifiedPage() {
  const [params] = useSearchParams()
  const ok = params.get('status') === 'success'
  const client = useQueryClient()
  // Làm mới trạng thái đăng nhập để thanh nhắc "chưa xác thực" biến mất ngay.
  useEffect(() => { if (ok) void client.invalidateQueries({ queryKey: ['auth', 'me'] }) }, [ok, client])
  return <AuthShell title={ok ? 'Xác thực email thành công' : 'Liên kết không hợp lệ'} copy={ok ? 'Tài khoản của bạn đã sẵn sàng để đặt sân.' : 'Liên kết xác thực sai hoặc đã hết hạn. Hãy đăng nhập và bấm "Gửi lại email xác thực".'}><div className="form-stack"><div className={`notice ${ok ? 'notice--success' : 'notice--error'}`}>{ok ? 'Email đã được xác thực.' : 'Không thể xác thực email.'}</div><Link className="button button--brand button--block" to="/">Về trang chủ</Link></div></AuthShell>
}

function AuthShell({ title, copy, children }: { title: string; copy: string; children: React.ReactNode }) {
  return (
    <div className="auth-page">
      <aside>
        <motion.div
          className="auth-story"
          initial={{ opacity: 0, x: -16 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] }}
        >
          <ShieldCheck size={38} />
          <span>Sân chuẩn · lịch rõ · chơi vui</span>
          <h1>Mọi trận đấu hay đều bắt đầu từ một lựa chọn đúng.</h1>
          <p>SportHub kết nối người chơi với những địa điểm thể thao đáng tin cậy.</p>
        </motion.div>
      </aside>
      <main>
        <motion.div
          className="auth-form"
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        >
          <Brand />
          <div className="auth-heading">
            <h2>{title}</h2>
            <p>{copy}</p>
          </div>
          {children}
        </motion.div>
      </main>
    </div>
  )
}
