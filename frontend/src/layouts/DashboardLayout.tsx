import { Activity, Building2, CalendarDays, ChartNoAxesCombined, CircleUserRound, FolderTree, LogOut, MessageSquareText, ShieldCheck, UsersRound } from 'lucide-react'
import { NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../context/auth-context'

const navigation = {
  customer: [
    { to: '/bookings', label: 'Lịch đặt của tôi', icon: CalendarDays },
    { to: '/profile', label: 'Hồ sơ cá nhân', icon: CircleUserRound },
  ],
  field_owner: [
    { to: '/owner', label: 'Tổng quan', icon: ChartNoAxesCombined },
    { to: '/owner/fields', label: 'Sân của tôi', icon: Building2 },
    { to: '/owner/bookings', label: 'Đơn đặt sân', icon: CalendarDays },
    { to: '/owner/reviews', label: 'Đánh giá', icon: MessageSquareText },
    { to: '/owner/profile', label: 'Hồ sơ cơ sở', icon: CircleUserRound },
  ],
  admin: [
    { to: '/admin', label: 'Tổng quan', icon: ChartNoAxesCombined },
    { to: '/admin/users', label: 'Người dùng', icon: UsersRound },
    { to: '/admin/owners', label: 'Duyệt chủ sân', icon: ShieldCheck },
    { to: '/admin/fields', label: 'Duyệt sân', icon: Building2 },
    { to: '/admin/bookings', label: 'Giao dịch', icon: CalendarDays },
    { to: '/admin/reviews', label: 'Đánh giá', icon: MessageSquareText },
    { to: '/admin/categories', label: 'Danh mục', icon: FolderTree },
  ],
}

export function DashboardLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  if (!user) return null
  const isAdmin = user.role === 'admin'

  return <div className={`dashboard ${isAdmin ? 'dashboard--admin' : ''}`}>
    <aside className="sidebar">
      <a className="sidebar__brand" href="/"><span><Activity /></span><div><strong>SportHub</strong><small>{isAdmin ? 'System console' : user.role === 'field_owner' ? 'Kênh chủ sân' : 'Tài khoản'}</small></div></a>
      <nav>{navigation[user.role].map(({ to, label, icon: Icon }) => <NavLink to={to} end={to === '/admin' || to === '/owner'} key={to}><Icon />{label}</NavLink>)}</nav>
      <div className="sidebar__user"><div className="avatar">{user.name.slice(0, 1).toUpperCase()}</div><div><strong>{user.name}</strong><small>{user.email}</small></div></div>
      <button onClick={async () => { await logout(); navigate('/') }}><LogOut /> Đăng xuất</button>
    </aside>
    <main className="dashboard__main"><header className="dashboard__top"><div><span>{isAdmin ? 'Quản trị hệ thống' : user.role === 'field_owner' ? 'Trung tâm vận hành' : 'Khu vực khách hàng'}</span><strong>Xin chào, {user.name}</strong></div><a className="button button--ghost" href="/">Xem trang công khai</a></header><div className="dashboard__content"><Outlet /></div></main>
  </div>
}
