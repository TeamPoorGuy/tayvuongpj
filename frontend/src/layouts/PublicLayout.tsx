import { Menu, UserRound } from 'lucide-react'
import { Link, NavLink, Outlet } from 'react-router'
import { Brand } from '../components/Brand'
import { useAuth } from '../context/auth-context'

export function PublicLayout() {
  const { user } = useAuth()
  const workspace = user?.role === 'admin' ? '/admin' : user?.role === 'field_owner' ? '/owner' : '/bookings'
  return (
    <div className="app-shell">
      <header className="topbar">
        <div className="shell topbar__inner">
          <Brand />
          <nav className="topnav" aria-label="Điều hướng chính">
            <NavLink to="/" end>Trang chủ</NavLink>
            <NavLink to="/fields">Tìm sân</NavLink>
            <a href="#how-it-works">Cách hoạt động</a>
          </nav>
          <div className="topbar__actions">
            {user ? <Link className="button button--ghost" to={workspace}><UserRound size={16} /> {user.name}</Link> : <><Link className="button button--ghost" to="/login"><UserRound size={16} /> Đăng nhập</Link><Link className="button button--brand" to="/register">Tham gia ngay</Link></>}
            <button className="mobile-menu" aria-label="Mở menu"><Menu /></button>
          </div>
        </div>
      </header>
      <main><Outlet /></main>
      <footer className="footer">
        <div className="shell footer__grid">
          <div><Brand /><p>Nền tảng tìm và đặt sân thể thao dành cho cộng đồng Việt Nam.</p></div>
          <div><strong>Khám phá</strong><Link to="/fields">Tất cả sân</Link><Link to="/register">Trở thành chủ sân</Link></div>
          <div><strong>SportHub</strong><span>Hà Nội, Việt Nam</span><span>hotro@sporthub.vn</span></div>
        </div>
      </footer>
    </div>
  )
}
