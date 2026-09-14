import { Activity, Building2, CalendarDays, ChartNoAxesCombined, CircleUserRound, FolderTree, LogOut, MessageSquareText, ShieldCheck, UsersRound } from 'lucide-react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { AnimatePresence, motion } from 'framer-motion'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'

const customerNav = [
  { to: '/bookings', label: 'Lịch đặt của tôi', icon: CalendarDays },
  { to: '/profile', label: 'Hồ sơ cá nhân', icon: CircleUserRound },
]

const ownerNav = [
  { to: '/owner', label: 'Tổng quan', icon: ChartNoAxesCombined },
  { to: '/owner/fields', label: 'Sân của tôi', icon: Building2 },
  { to: '/owner/bookings', label: 'Đơn đặt sân', icon: CalendarDays },
  { to: '/owner/reviews', label: 'Đánh giá', icon: MessageSquareText },
  { to: '/owner/profile', label: 'Hồ sơ cơ sở', icon: CircleUserRound },
]

const becomeOwnerNav = [{ to: '/become-owner', label: 'Đăng ký làm chủ sân', icon: Building2 }]

const navigation = {
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
  const location = useLocation()
  useReveal([location.pathname])

  if (!user) return null
  const isAdmin = user.role === 'admin'
  const isApprovedOwner = user.field_owner_profile?.verification_status === 'approved'
  const items = isAdmin ? navigation.admin : [...customerNav, ...(isApprovedOwner ? ownerNav : becomeOwnerNav)]

  return (
    <div className={`dashboard ${isAdmin ? 'dashboard--admin' : ''}`}>
      <aside className="sidebar">
        <a className="sidebar__brand" href="/">
          <span><Activity /></span>
          <div>
            <strong>SportHub</strong>
            <small>{isAdmin ? 'System console' : isApprovedOwner ? 'Kênh chủ sân' : 'Tài khoản'}</small>
          </div>
        </a>
        <nav>
          {items.map(({ to, label, icon: Icon }) => {
            const isExact = to === '/admin' || to === '/owner'
            const isActive = isExact ? location.pathname === to : location.pathname.startsWith(to)
            return (
              <NavLink to={to} end={isExact} key={to}>
                {isActive && (
                  <motion.div
                    layoutId="sidebar-active-indicator"
                    className="sidebar__active-pill"
                    transition={{ type: 'spring', stiffness: 380, damping: 32 }}
                  />
                )}
                <Icon />
                <span>{label}</span>
              </NavLink>
            )
          })}
        </nav>
        <div className="sidebar__user">
          <div className="avatar">{user.name.slice(0, 1).toUpperCase()}</div>
          <div>
            <strong>{user.name}</strong>
            <small>{user.email}</small>
          </div>
        </div>
        <button onClick={async () => { await logout(); navigate('/') }}><LogOut /> Đăng xuất</button>
      </aside>
      <main className="dashboard__main">
        <header className="dashboard__top">
          <div>
            <span>{isAdmin ? 'Quản trị hệ thống' : isApprovedOwner ? 'Trung tâm vận hành' : 'Khu vực khách hàng'}</span>
            <strong>Xin chào, {user.name}</strong>
          </div>
          <a className="button button--ghost" href="/">Xem trang công khai</a>
        </header>
        <div className="dashboard__content">
          <AnimatePresence mode="wait">
            <motion.div
              key={location.pathname}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
            >
              <Outlet />
            </motion.div>
          </AnimatePresence>
        </div>
      </main>
    </div>
  )
}

