import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../context/auth-context'
import type { Role } from '../types/api'

export function ProtectedRoute({ roles }: { roles: Role[] }) {
  const { user, isLoading } = useAuth()
  const location = useLocation()
  if (isLoading) return <div className="page-loader">Đang xác thực...</div>
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />
  if (!roles.includes(user.role)) return <Navigate to="/" replace />
  return <Outlet />
}
