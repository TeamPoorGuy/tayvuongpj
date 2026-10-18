import { VerifyEmailBanner } from '../components/VerifyEmailBanner'
import {
  Activity,
  Building2,
  CalendarDays,
  ChartNoAxesCombined,
  CircleUserRound,
  Coins,
  FolderTree,
  LogOut,
  MessageSquareText,
  Receipt,
  ShieldCheck,
  Trophy,
  UsersRound,
} from 'lucide-react'
import { useState, useEffect } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { AnimatePresence, motion } from 'framer-motion'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'
import { Avatar } from '../components/Avatar'

const customerNav = [
  { to: '/bookings', label: 'Lịch chơi của tôi', icon: CalendarDays },
  { to: '/profile', label: 'Hồ sơ cá nhân', icon: CircleUserRound },
]

const ownerNav = [
  { to: '/owner', label: 'Tổng quan sân', icon: ChartNoAxesCombined },
  { to: '/owner/schedule', label: 'Lịch sân trực quan', icon: CalendarDays },
  { to: '/owner/fields', label: 'Quản lý sân', icon: Building2 },
  { to: '/owner/bookings', label: 'Đơn đặt sân', icon: CalendarDays },
  { to: '/owner/statements', label: 'Đối soát & Phí sàn', icon: Receipt },
  { to: '/owner/reviews', label: 'Đánh giá khách', icon: MessageSquareText },
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
    { to: '/admin/statements', label: 'Đối soát tháng', icon: Coins },
    { to: '/admin/matches', label: 'Phòng tìm đối', icon: Trophy },
    { to: '/admin/reviews', label: 'Đánh giá', icon: MessageSquareText },
    { to: '/admin/categories', label: 'Danh mục', icon: FolderTree },
  ],
}

export function DashboardLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  useReveal([location.pathname])

  const isAdmin = user?.role === 'admin'
  const isApprovedOwner = user?.field_owner_profile?.verification_status === 'approved'

  // Mode switcher for approved owners
  const [activeMode, setActiveMode] = useState<'owner' | 'customer'>(() => {
    if (location.pathname.startsWith('/owner')) return 'owner'
    return localStorage.getItem('sporthub:last-mode') === 'owner' && isApprovedOwner ? 'owner' : 'customer'
  })

  useEffect(() => {
    if (location.pathname.startsWith('/owner')) {
      setActiveMode('owner')
      localStorage.setItem('sporthub:last-mode', 'owner')
    } else if (location.pathname === '/bookings' || location.pathname === '/profile') {
      setActiveMode('customer')
      localStorage.setItem('sporthub:last-mode', 'customer')
    }
  }, [location.pathname, isApprovedOwner])

  if (!user) return null

  // Determine navigation items
  const items = isAdmin
    ? navigation.admin
    : isApprovedOwner
    ? activeMode === 'owner'
      ? ownerNav
      : customerNav
    : [...customerNav, ...becomeOwnerNav]

  return (
    <div className={`dashboard ${isAdmin ? 'dashboard--admin' : ''}`}>
      <aside className="sidebar">
          <Link className="sidebar__brand" to="/">
          <span>
            <Activity />
          </span>
          <div>
            <strong>SportHub</strong>
            <small>
              {isAdmin
                ? 'System console'
                : isApprovedOwner
                ? activeMode === 'owner'
                  ? 'Kênh chủ sân'
                  : 'Khu vực khách hàng'
                : 'Tài khoản khách'}
            </small>
          </div>
          </Link>

        {/* Mode Switcher for Approved Owners */}
        {isApprovedOwner && (
          <div className="mode-switcher">
            <button
              type="button"
              className={`mode-switcher__item ${activeMode === 'owner' ? 'mode-switcher__item--active' : ''}`}
              onClick={() => {
                setActiveMode('owner')
                localStorage.setItem('sporthub:last-mode', 'owner')
                navigate('/owner')
              }}
            >
              <Building2 size={13} /> Chủ sân
            </button>
            <button
              type="button"
              className={`mode-switcher__item ${activeMode === 'customer' ? 'mode-switcher__item--active' : ''}`}
              onClick={() => {
                setActiveMode('customer')
                localStorage.setItem('sporthub:last-mode', 'customer')
                navigate('/bookings')
              }}
            >
              <CalendarDays size={13} /> Khách đặt
            </button>
          </div>
        )}

        <nav>
          {items.map(({ to, label, icon: Icon }) => {
            const isExact = to === '/admin' || to === '/owner' || to === '/bookings'
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
          <Avatar name={user.name} src={user.avatar} />
          <div>
            <strong>{user.name}</strong>
            <small>{user.email}</small>
          </div>
        </div>
        <button
          onClick={async () => {
            await logout()
            navigate('/')
          }}
        >
          <LogOut /> Đăng xuất
        </button>
      </aside>

      <main className="dashboard__main">
        <header className="dashboard__top">
          <div>
            <span>
              {isAdmin
                ? 'Quản trị hệ thống'
                : isApprovedOwner
                ? activeMode === 'owner'
                  ? 'Trung tâm vận hành cơ sở'
                  : 'Lịch sử đặt sân cá nhân'
                : 'Khu vực khách hàng'}
            </span>
            <strong>Xin chào, {user.name}</strong>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <Link className="button button--ghost" to="/">
              Xem website
            </Link>
            <button
              type="button"
              className="button button--ghost button--small mobile-logout-btn"
              onClick={async () => {
                await logout()
                navigate('/')
              }}
              title="Đăng xuất"
              aria-label="Đăng xuất"
            >
              <LogOut size={14} />
            </button>
          </div>
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
              <VerifyEmailBanner />
              <Outlet />
            </motion.div>
          </AnimatePresence>
        </div>
      </main>
    </div>
  )
}
