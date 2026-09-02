import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ArrowRight, LockKeyhole, Mail, ShieldCheck } from 'lucide-react'
import { errorMessage } from '../api/errors'
import { Brand } from '../components/Brand'
import { useAuth } from '../context/auth-context'
import type { User } from '../types/api'

const destination = (user: User) => user.role === 'admin' ? '/admin' : user.role === 'field_owner' ? '/owner' : '/bookings'

export function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  if (user) return <Navigate to={destination(user)} replace />

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setBusy(true); setError('')
    const values = new FormData(event.currentTarget)
    try { const next = await login({ email: String(values.get('email')), password: String(values.get('password')), remember: values.get('remember') === 'on' }); navigate((location.state as { from?: string })?.from ?? destination(next)) }
    catch (reason) { setError(errorMessage(reason)) } finally { setBusy(false) }
  }

  return <AuthShell title="Chào mừng trở lại" copy="Đăng nhập để quản lý lịch chơi và sân của bạn."><form onSubmit={submit} className="form-stack"><label><span>Email</span><div className="input-icon"><Mail /><input name="email" type="email" required placeholder="ban@email.com" /></div></label><label><span>Mật khẩu</span><div className="input-icon"><LockKeyhole /><input name="password" type="password" required placeholder="••••••••" /></div></label><label className="check-line"><input name="remember" type="checkbox" /> Ghi nhớ đăng nhập</label>{error && <div className="notice notice--error">{error}</div>}<button disabled={busy} className="button button--brand button--block">{busy ? 'Đang đăng nhập...' : 'Đăng nhập'} <ArrowRight size={16} /></button><p className="form-switch">Chưa có tài khoản? <Link to="/register">Đăng ký ngay</Link></p></form></AuthShell>
}

export function RegisterPage() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [role, setRole] = useState('customer')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  if (user) return <Navigate to={destination(user)} replace />

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setBusy(true); setError('')
    const values = Object.fromEntries(new FormData(event.currentTarget).entries())
    try { const next = await register({ ...values, role }); navigate(destination(next)) }
    catch (reason) { setError(errorMessage(reason)) } finally { setBusy(false) }
  }

  return <AuthShell title="Tạo tài khoản SportHub" copy="Chọn vai trò và bắt đầu hành trình thể thao của bạn."><form onSubmit={submit} className="form-stack"><div className="role-switch"><button type="button" className={role === 'customer' ? 'active' : ''} onClick={() => setRole('customer')}>Khách hàng</button><button type="button" className={role === 'field_owner' ? 'active' : ''} onClick={() => setRole('field_owner')}>Chủ sân</button></div><div className="form-grid"><label><span>Họ và tên</span><input name="name" required /></label><label><span>Số điện thoại</span><input name="phone" required /></label></div><label><span>Email</span><input name="email" type="email" required /></label><label><span>Địa chỉ</span><input name="address" /></label>{role === 'field_owner' && <><label><span>Tên cơ sở</span><input name="business_name" required /></label><div className="form-grid"><label><span>Địa chỉ kinh doanh</span><input name="business_address" required /></label><label><span>SĐT kinh doanh</span><input name="business_phone" required /></label></div></>}<div className="form-grid"><label><span>Mật khẩu</span><input name="password" type="password" required /></label><label><span>Xác nhận mật khẩu</span><input name="password_confirmation" type="password" required /></label></div>{error && <div className="notice notice--error">{error}</div>}<button disabled={busy} className="button button--brand button--block">{busy ? 'Đang tạo...' : 'Tạo tài khoản'} <ArrowRight size={16} /></button><p className="form-switch">Đã có tài khoản? <Link to="/login">Đăng nhập</Link></p></form></AuthShell>
}

function AuthShell({ title, copy, children }: { title: string; copy: string; children: React.ReactNode }) {
  return <div className="auth-page"><aside><div className="auth-story"><ShieldCheck size={38} /><span>Sân chuẩn · lịch rõ · chơi vui</span><h1>Mọi trận đấu hay đều bắt đầu từ một lựa chọn đúng.</h1><p>SportHub kết nối người chơi với những địa điểm thể thao đáng tin cậy.</p></div></aside><main><div className="auth-form"><Brand /><div className="auth-heading"><h2>{title}</h2><p>{copy}</p></div>{children}</div></main></div>
}
