import { VerifyEmailBanner } from '../components/VerifyEmailBanner'
import {
  Activity,
  AlertCircle,
  AlertTriangle,
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
  Sliders,
  Trophy,
  UsersRound,
  Wallet,
} from 'lucide-react'
import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { api } from '../api/client'
import { AnimatePresence, motion } from 'framer-motion'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'
import { Avatar } from '../components/Avatar'

const customerNav = [
  { to: '/bookings', label: 'Lịch chơi của tôi', icon: CalendarDays },
  { to: '/wallet', label: 'Ví SportHub', icon: Wallet },
  { to: '/profile', label: 'Hồ sơ cá nhân', icon: CircleUserRound },
]

const ownerNav = [
  { to: '/owner', label: 'Tổng quan sân', icon: ChartNoAxesCombined },
  { to: '/owner/schedule', label: 'Lịch sân trực quan', icon: CalendarDays },
  { to: '/owner/fields', label: 'Quản lý sân', icon: Building2 },
  { to: '/owner/bookings', label: 'Đơn đặt sân', icon: CalendarDays },
  { to: '/owner/wallet', label: 'Ví & Rút tiền', icon: Wallet },
  { to: '/owner/statements', label: 'Báo cáo đối soát', icon: Receipt },
  { to: '/owner/reviews', label: 'Đánh giá khách', icon: MessageSquareText },
  { to: '/owner/complaints', label: 'Khiếu nại', icon: AlertCircle },
  { to: '/owner/profile', label: 'Hồ sơ cơ sở', icon: CircleUserRound },
]

const becomeOwnerNav = [{ to: '/become-owner', label: 'Đăng ký làm chủ sân', icon: Building2 }]

const adminGroups = [
  {
    title: 'Tổng quan',
    items: [
      { to: '/admin', label: 'Tổng quan', icon: ChartNoAxesCombined },
    ],
  },
  {
    title: 'Vận hành',
    items: [
      { to: '/admin/bookings', label: 'Đơn đặt sân', icon: CalendarDays },
      { to: '/admin/payments', label: 'Giao dịch thu hộ', icon: Receipt },
      { to: '/admin/withdrawals', label: 'Lệnh rút tiền', icon: Wallet },
      { to: '/admin/complaints', label: 'Khiếu nại', icon: AlertTriangle },
      { to: '/admin/matches', label: 'Phòng tìm đối', icon: Trophy },
    ],
  },
  {
    title: 'Đối tác',
    items: [
      { to: '/admin/owners', label: 'Duyệt chủ sân', icon: ShieldCheck },
      { to: '/admin/fields', label: 'Duyệt sân', icon: Building2 },
      { to: '/admin/statements', label: 'Báo cáo đối soát', icon: Coins },
    ],
  },
  {
    title: 'Cộng đồng',
    items: [
      { to: '/admin/users', label: 'Người dùng', icon: UsersRound },
      { to: '/admin/reviews', label: 'Đánh giá', icon: MessageSquareText },
    ],
  },
  {
    title: 'Hệ thống',
    items: [
      { to: '/admin/categories', label: 'Danh mục', icon: FolderTree },
      { to: '/admin/settings', label: 'Cấu hình sàn', icon: Sliders },
      { to: '/admin/logs', label: 'Nhật ký admin', icon: Activity },
    ],
  },
]

export function DashboardLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  useReveal([location.pathname])

  // Số việc đang chờ theo đường dẫn menu (khiếu nại cần phản hồi, đơn chờ duyệt...), tự làm mới mỗi phút.
  const badges = useQuery({
    queryKey: ['badges', user?.id],
    enabled: !!user,
    refetchInterval: 60_000,
    queryFn: async () => (await api.get<{ data: Record<string, number> }>('/me/badges')).data.data,
  }).data
  const badge = (to: string) => (badges?.[to] ? <b className="sidebar__badge" aria-label={`${badges[to]} việc đang chờ`}>{badges[to]}</b> : null)

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

  // Determine navigation items for non-admin
  const items = isApprovedOwner
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
          {isAdmin ? (
            adminGroups.map((group) => (
              <div key={group.title} style={{ marginBottom: 10 }}>
                <div style={{ padding: '6px 14px 2px', fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: 'rgba(255, 255, 255, 0.45)' }}>
                  {group.title}
                </div>
                {group.items.map(({ to, label, icon: Icon }) => {
                  const isExact = to === '/admin'
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
                      <Icon size={16} />
                      <span>{label}</span>
                      {badge(to)}
                    </NavLink>
                  )
                })}
              </div>
            ))
          ) : (
            items.map(({ to, label, icon: Icon }) => {
              const isExact = to === '/owner' || to === '/bookings'
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
                  <Icon size={16} />
                  <span>{label}</span>
                  {badge(to)}
                </NavLink>
              )
            })
          )}
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
            navigate('/', { replace: true })
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
                navigate('/', { replace: true })
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
