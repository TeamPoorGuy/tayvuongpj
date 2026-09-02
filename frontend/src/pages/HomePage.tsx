import { useQuery } from '@tanstack/react-query'
import { ArrowRight, CalendarCheck, CheckCircle2, Search, ShieldCheck, Sparkles } from 'lucide-react'
import { Link, useNavigate } from 'react-router'
import { useState } from 'react'
import { api } from '../api/client'
import { FieldCard } from '../components/FieldCard'
import type { ApiEnvelope, HomeData } from '../types/api'

export function HomePage() {
  const navigate = useNavigate()
  const [keyword, setKeyword] = useState('')
  const { data, isPending, isError } = useQuery({
    queryKey: ['home'],
    queryFn: async () => (await api.get<ApiEnvelope<HomeData>>('/home')).data.data,
  })

  const search = (event: React.FormEvent) => {
    event.preventDefault()
    navigate(`/fields${keyword ? `?keyword=${encodeURIComponent(keyword)}` : ''}`)
  }

  return (
    <>
      <section className="hero">
        <div className="shell hero__grid">
          <div className="hero__copy">
            <span className="eyebrow"><Sparkles size={14} /> Một chạm đến trận đấu</span>
            <h1>Đặt sân nhanh.<br /><em>Chơi hết mình.</em></h1>
            <p>Tìm đúng sân, chọn đúng giờ và gửi yêu cầu đặt lịch trong vài phút.</p>
            <form className="hero-search" onSubmit={search}>
              <Search size={20} />
              <input value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tên sân, quận hoặc địa chỉ..." aria-label="Tìm sân" />
              <button className="button button--brand" type="submit">Tìm sân</button>
            </form>
            <div className="hero__proof"><span><CheckCircle2 /> Sân đã kiểm duyệt</span><span><CheckCircle2 /> Lịch trống rõ ràng</span><span><CheckCircle2 /> Không phí ẩn</span></div>
          </div>
          <aside className="hero__spotlight">
            <div className="spotlight__top"><span>Sẵn sàng vào sân?</span><strong>01</strong></div>
            <div className="spotlight__icon"><CalendarCheck size={34} /></div>
            <h2>Chọn môn.<br />Chọn giờ.<br />Vào trận.</h2>
            <Link to="/fields">Khám phá ngay <ArrowRight size={18} /></Link>
          </aside>
        </div>
      </section>

      <section className="section shell">
        <div className="section-heading"><div><span className="kicker">Chọn môn yêu thích</span><h2>Mỗi ngày một trận mới.</h2></div><Link to="/fields">Xem tất cả <ArrowRight size={16} /></Link></div>
        {isPending && <div className="loading-grid">{Array.from({ length: 5 }).map((_, index) => <span key={index} />)}</div>}
        {isError && <div className="notice notice--error">Không thể tải danh mục. Hãy kiểm tra API Laravel.</div>}
        <div className="category-grid">
          {data?.categories.map((category) => (
            <Link to={`/fields?category=${category.slug}`} className="category-card" key={category.id}>
              <span>{category.icon ?? '●'}</span><strong>{category.name}</strong><small>{category.field_types_count} loại sân</small>
            </Link>
          ))}
        </div>
      </section>

      <section className="section section--muted">
        <div className="shell">
          <div className="section-heading"><div><span className="kicker">Được cộng đồng lựa chọn</span><h2>Sân nổi bật gần đây.</h2></div><Link to="/fields">Khám phá toàn bộ <ArrowRight size={16} /></Link></div>
          <div className="field-grid">{data?.featured_fields.map((field) => <FieldCard field={field} key={field.id} />)}</div>
        </div>
      </section>

      <section id="how-it-works" className="benefits">
        <div className="shell benefits__grid">
          <article><Search /><div><strong>Tìm đúng sân</strong><p>Lọc theo môn, khu vực và mức giá phù hợp.</p></div></article>
          <article><CalendarCheck /><div><strong>Chọn lịch trống</strong><p>Xem giờ còn trống trước khi gửi yêu cầu.</p></div></article>
          <article><ShieldCheck /><div><strong>An tâm vào trận</strong><p>Thông tin sân và chủ sân được kiểm duyệt.</p></div></article>
        </div>
      </section>
    </>
  )
}
