import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useLocation } from 'react-router'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { useAuth } from '../context/auth-context'

/**
 * Khi hệ thống bắt xác thực email (backend trả email_verified=false), tài khoản chưa xác thực
 * chỉ thấy màn hình này cho tới khi bấm link trong email. Ở chế độ MAIL_MAILER=log backend luôn
 * trả email_verified=true nên cổng này không bao giờ hiện.
 */
export function VerifyGate({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth()
  const location = useLocation()
  const client = useQueryClient()
  const resend = useMutation({ mutationFn: () => api.post('/auth/email/resend') })

  const blocked = user && user.role !== 'admin' && user.email_verified === false && location.pathname !== '/email-verified'
  if (!blocked) return <>{children}</>

  return (
    <div className="auth-page">
      <main>
        <div className="auth-form">
          <div className="auth-heading">
            <h2>Xác thực email để tiếp tục</h2>
            <p>
              Chúng tôi đã gửi liên kết xác thực tới <strong>{user.email}</strong>. Bấm vào liên kết trong email để
              kích hoạt tài khoản.
            </p>
          </div>
          <div className="form-stack">
            {resend.isSuccess && <div className="notice notice--success">Đã gửi lại, hãy kiểm tra hộp thư (cả mục spam).</div>}
            {resend.isError && <div className="notice notice--error">{errorMessage(resend.error)}</div>}
            <button className="button button--brand button--block" disabled={resend.isPending} onClick={() => resend.mutate()}>
              {resend.isPending ? 'Đang gửi...' : 'Gửi lại email xác thực'}
            </button>
            <button className="button button--ghost button--block" onClick={() => void client.invalidateQueries({ queryKey: ['auth', 'me'] })}>
              Tôi đã xác thực xong
            </button>
            <button className="button button--ghost button--block" onClick={() => void logout()}>
              Đăng xuất
            </button>
          </div>
        </div>
      </main>
    </div>
  )
}
