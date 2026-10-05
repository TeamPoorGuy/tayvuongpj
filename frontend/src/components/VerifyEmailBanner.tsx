import { useMutation } from '@tanstack/react-query'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { useAuth } from '../context/auth-context'

export function VerifyEmailBanner() {
  const { user } = useAuth()
  const resend = useMutation({ mutationFn: () => api.post('/auth/email/resend') })
  if (!user || user.role === 'admin' || user.email_verified) return null

  return (
    <div className="notice notice--warn verify-banner" role="status">
      Email <strong>{user.email}</strong> chưa được xác thực — bạn cần xác thực để đặt sân hoặc đăng ký làm chủ sân.{' '}
      {resend.isSuccess ? <strong>Đã gửi, hãy kiểm tra hộp thư.</strong>
        : <button className="link-button" disabled={resend.isPending} onClick={() => resend.mutate()}>{resend.isPending ? 'Đang gửi...' : 'Gửi lại email xác thực'}</button>}
      {resend.isError && <> {errorMessage(resend.error)}</>}
    </div>
  )
}
