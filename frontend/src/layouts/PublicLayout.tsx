import { VerifyEmailBanner } from '../components/VerifyEmailBanner'
import { Building2, CalendarDays, LogOut, Menu, UserRound, X } from 'lucide-react'
import { useState, useEffect } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { AnimatePresence, motion } from 'framer-motion'
import { Brand } from '../components/Brand'
import { useAuth } from '../context/auth-context'
import { useReveal } from '../hooks/useReveal'
import { Avatar } from '../components/Avatar'

export function PublicLayout() {
  const { user, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const [mobileOpen, setMobileOpen] = useState(false)
  useReveal([location.pathname])

  // Close mobile drawer on route change
  useEffect(() => {
    setMobileOpen(false)
  }, [location.pathname])

  const isAdmin = user?.role === 'admin'
  const isApprovedOwner = user?.field_owner_profile?.verification_status === 'approved'
  const workspace = isAdmin ? '/admin' : isApprovedOwner ? '/owner' : '/bookings'
  const workspaceLabel = isAdmin ? 'Quản trị' : isApprovedOwner ? 'Kênh chủ sân' : 'Lịch đặt của tôi'

  return (
    <div className="app-shell">
      <VerifyEmailBanner />
      <header className="topbar">
        <div className="shell topbar__inner">
          <Brand />
          <nav className="topnav" aria-label="Điều hướng chính">
            <NavLink to="/" end>Trang chủ</NavLink>
            <NavLink to="/fields">Tìm sân</NavLink>
            <NavLink to="/matches">Tìm đối & Ghép đội</NavLink>
            <a href="/#how-it-works">Cách hoạt động</a>
          </nav>
          <div className="topbar__actions">
            {user ? (
              <div className="user-pill-wrap">
                {/* Mode Switcher for Approved Owners */}
                {isApprovedOwner && (
                  <Link
                    to="/bookings"
                    className="button button--ghost"
                    style={{ fontSize: 11, padding: '8px 12px' }}
                    title="Xem đơn đặt sân của bạn với vai trò khách"
                  >
                    <CalendarDays size={14} /> Lịch chơi của tôi
                  </Link>
                )}
                <Link className="button button--ghost user-pill" to={workspace}>
                  <Avatar name={user.name} src={user.avatar} className="user-avatar-tiny" />
                  <span className="user-pill__name">{user.name}</span>
                  <span className="badge badge--soft">{workspaceLabel}</span>
                </Link>
                <button
                  type="button"
                  className="button button--icon button--ghost"
                  title="Đăng xuất"
                  onClick={async () => { await logout(); navigate('/') }}
                >
                  <LogOut size={15} />
                </button>
              </div>
            ) : (
              <>
                <Link className="button button--ghost" to="/login"><UserRound size={16} /> Đăng nhập</Link>
                <Link className="button button--brand" to="/register">Tham gia ngay</Link>
              </>
            )}
            <button
              type="button"
              className="mobile-menu"
              aria-label={mobileOpen ? 'Đóng menu' : 'Mở menu'}
              onClick={() => setMobileOpen(!mobileOpen)}
            >
              {mobileOpen ? <X size={22} /> : <Menu size={22} />}
            </button>
          </div>
        </div>

        {/* Mobile Navigation Drawer */}
        <AnimatePresence>
          {mobileOpen && (
            <motion.div
              className="mobile-drawer"
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] }}
            >
              <div className="shell mobile-drawer__inner">
                <nav className="mobile-drawer__nav">
                  <NavLink to="/" end onClick={() => setMobileOpen(false)}>Trang chủ</NavLink>
                  <NavLink to="/fields" onClick={() => setMobileOpen(false)}>Tìm sân</NavLink>
                  <NavLink to="/matches" onClick={() => setMobileOpen(false)}>Tìm đối & Ghép đội</NavLink>
                  <a href="/#how-it-works" onClick={() => setMobileOpen(false)}>Cách hoạt động</a>
                </nav>
                <div className="mobile-drawer__actions">
                  {user ? (
                    <>
                      <div className="mobile-user-card">
                        <Avatar name={user.name} src={user.avatar} />
                        <div>
                          <strong>{user.name}</strong>
                          <small>{user.email}</small>
                        </div>
                      </div>
                      {isApprovedOwner ? (
                        <div className="form-grid" style={{ gap: 8 }}>
                          <Link className="button button--brand button--block" to="/owner" onClick={() => setMobileOpen(false)}>
                            <Building2 size={16} /> Kênh chủ sân
                          </Link>
                          <Link className="button button--ghost button--block" to="/bookings" onClick={() => setMobileOpen(false)}>
                            <CalendarDays size={16} /> Lịch đặt của tôi
                          </Link>
                        </div>
                      ) : (
                        <Link className="button button--brand button--block" to={workspace} onClick={() => setMobileOpen(false)}>
                          <CalendarDays size={16} /> Vào {workspaceLabel}
                        </Link>
                      )}
                      <button
                        className="button button--ghost button--block"
                        onClick={async () => { setMobileOpen(false); await logout(); navigate('/') }}
                      >
                        <LogOut size={16} /> Đăng xuất
                      </button>
                    </>
                  ) : (
                    <>
                      <Link className="button button--brand button--block" to="/register" onClick={() => setMobileOpen(false)}>
                        Tham gia ngay
                      </Link>
                      <Link className="button button--ghost button--block" to="/login" onClick={() => setMobileOpen(false)}>
                        <UserRound size={16} /> Đăng nhập
                      </Link>
                    </>
                  )}
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </header>

      <main>
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
      </main>

      <footer className="footer">
        <div className="shell footer__grid">
          <div className="reveal" style={{ '--i': 0 } as React.CSSProperties}>
            <Brand />
            <p>Nền tảng tìm và đặt sân thể thao nhanh chóng, minh bạch cho cộng đồng Việt Nam.</p>
          </div>
          <div className="reveal" style={{ '--i': 1 } as React.CSSProperties}>
            <strong>Khám phá</strong>
            <Link to="/fields">Tất cả sân</Link>
            <Link to={user ? (isApprovedOwner ? '/owner' : '/become-owner') : '/register'}>
              {isApprovedOwner ? 'Quản lý sân của bạn' : 'Trở thành chủ sân'}
            </Link>
            <a href="/#how-it-works">Quy trình đặt sân</a>
          </div>
          <div className="reveal" style={{ '--i': 2 } as React.CSSProperties}>
            <strong>SportHub</strong>
            <span>Hà Nội & TP. Hồ Chí Minh, Việt Nam</span>
            <span>hotro@sporthub.vn</span>
            <small>© {new Date().getFullYear()} SportHub Platform. All rights reserved.</small>
          </div>
        </div>
      </footer>
    </div>
  )
}
