import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import {
  AlertTriangle,
  Building2,
  CalendarDays,
  CircleDollarSign,
  Coins,
  Inbox,
  Receipt,
  RotateCcw,
  ShieldCheck,
  TrendingUp,
} from 'lucide-react'
import { api } from '../../api/client'
import { formatMoney } from '../../utils/format'
import type { AdminDashboardData } from '../../types/api'

export function AdminDashboardPage() {
  const [period, setPeriod] = useState<'this_month' | 'last_month'>('this_month')

  const { data, isLoading, isError } = useQuery<AdminDashboardData>({
    queryKey: ['admin', 'dashboard', period],
    queryFn: async () => {
      return (await api.get<{ data: AdminDashboardData }>(`/admin/dashboard?period=${period}`)).data.data
    },
  })

  if (isLoading) {
    return <div className="query-state query-state--loading" aria-live="polite">Đang tải dữ liệu tổng quan quản trị...</div>
  }
  if (isError || !data) {
    return <div className="query-state query-state--error" role="alert">Không thể tải dữ liệu bảng điều khiển.</div>
  }

  const kpis = data.kpis
  const queue = data.action_queue
  const daily = data.daily_series ?? []

  // Max value for SVG chart scaling
  const maxGmv = Math.max(...daily.map((d) => d.gmv), 1000000)

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {/* Header & Bộ lọc kỳ */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 16 }}>
        <div>
          <h1 style={{ fontSize: 24, margin: '0 0 4px', color: 'var(--forest)' }}>Tổng quan hệ thống</h1>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
            Dữ liệu thống kê kỳ: <strong>{data.from_date}</strong> đến <strong>{data.to_date}</strong>
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <div className="button-group" style={{ display: 'inline-flex', background: 'var(--surface)', padding: 3, borderRadius: 'var(--r-md)', border: '1px solid var(--line)' }}>
            <button
              type="button"
              className={`button button--sm ${period === 'this_month' ? 'button--brand' : 'button--ghost'}`}
              onClick={() => setPeriod('this_month')}
            >
              Tháng này
            </button>
            <button
              type="button"
              className={`button button--sm ${period === 'last_month' ? 'button--brand' : 'button--ghost'}`}
              onClick={() => setPeriod('last_month')}
            >
              Tháng trước
            </button>
          </div>
        </div>
      </div>

      {/* Hàng việc cần xử lý (Action items queue) */}
      <div className="content-card" style={{ padding: 18, background: '#fff', borderLeft: '4px solid var(--pitch)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Inbox size={18} style={{ color: 'var(--pitch)' }} />
          <strong style={{ fontSize: 15, color: 'var(--forest)' }}>Hàng việc cần xử lý ngay</strong>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12 }}>
          <Link to="/admin/owners" className="stat-action" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 13, color: 'var(--soft)' }}>Hồ sơ chủ sân chờ duyệt</span>
              <ShieldCheck size={16} style={{ color: queue.pending_owners > 0 ? 'var(--warn)' : 'var(--muted)' }} />
            </div>
            <strong style={{ fontSize: 20, color: queue.pending_owners > 0 ? 'var(--warn)' : 'var(--forest)' }}>
              {queue.pending_owners}
            </strong>
          </Link>

          <Link to="/admin/fields" className="stat-action" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 13, color: 'var(--soft)' }}>Sân thể thao chờ duyệt</span>
              <Building2 size={16} style={{ color: queue.pending_fields > 0 ? 'var(--warn)' : 'var(--muted)' }} />
            </div>
            <strong style={{ fontSize: 20, color: queue.pending_fields > 0 ? 'var(--warn)' : 'var(--forest)' }}>
              {queue.pending_fields}
            </strong>
          </Link>

          <Link to="/admin/complaints" className="stat-action" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 13, color: 'var(--soft)' }}>Khiếu nại đang mở</span>
              <AlertTriangle size={16} style={{ color: queue.open_complaints > 0 ? 'var(--danger)' : 'var(--muted)' }} />
            </div>
            <strong style={{ fontSize: 20, color: queue.open_complaints > 0 ? 'var(--danger)' : 'var(--forest)' }}>
              {queue.open_complaints}
            </strong>
          </Link>

          <Link to="/admin/statements" className="stat-action" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 13, color: 'var(--soft)' }}>Kỳ chờ chi trả chủ sân</span>
              <Receipt size={16} style={{ color: queue.pending_payouts > 0 ? 'var(--pitch)' : 'var(--muted)' }} />
            </div>
            <strong style={{ fontSize: 20, color: queue.pending_payouts > 0 ? 'var(--pitch)' : 'var(--forest)' }}>
              {queue.pending_payouts}
            </strong>
          </Link>

          <Link to="/admin/bookings" className="stat-action" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 13, color: 'var(--soft)' }}>Đơn cần hoàn cọc</span>
              <RotateCcw size={16} style={{ color: queue.pending_refunds > 0 ? 'var(--danger)' : 'var(--muted)' }} />
            </div>
            <strong style={{ fontSize: 20, color: queue.pending_refunds > 0 ? 'var(--danger)' : 'var(--forest)' }}>
              {queue.pending_refunds}
            </strong>
          </Link>
        </div>
      </div>

      {/* KPI Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 16 }}>
        <div className="stat">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Tổng GMV (Giá trị đơn)</span>
            <CircleDollarSign size={18} style={{ color: 'var(--pitch)' }} />
          </div>
          <strong style={{ fontSize: 22, color: 'var(--forest)' }}>{formatMoney(kpis.gmv)}</strong>
          <small style={{ color: 'var(--muted)', display: 'block', marginTop: 4 }}>Đã thanh toán cọc/đủ: {formatMoney(kpis.paid_amount)}</small>
        </div>

        <div className="stat">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Hoa hồng phát sinh (ước tính)</span>
            <TrendingUp size={18} style={{ color: 'var(--pitch)' }} />
          </div>
          <strong style={{ fontSize: 22, color: 'var(--pitch)' }}>{formatMoney(kpis.commission_accrued)}</strong>
          <small style={{ color: 'var(--muted)', display: 'block', marginTop: 4 }}>
            Đã thu: {formatMoney(kpis.commission_collected)} · Chưa gồm tiền cọc bị phạt
          </small>
        </div>

        <div className="stat">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Công nợ hoa hồng chủ sân</span>
            <Coins size={18} style={{ color: kpis.commission_debt > 0 ? 'var(--warn)' : 'var(--muted)' }} />
          </div>
          <strong style={{ fontSize: 22, color: kpis.commission_debt > 0 ? 'var(--warn)' : 'var(--forest)' }}>
            {formatMoney(kpis.commission_debt)}
          </strong>
          <small style={{ color: 'var(--muted)', display: 'block', marginTop: 4 }}>Chưa thanh toán & đang báo chuyển</small>
        </div>

        <div className="stat">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Tổng đơn & Tỉ lệ huỷ</span>
            <CalendarDays size={18} style={{ color: 'var(--forest)' }} />
          </div>
          <strong style={{ fontSize: 22, color: 'var(--forest)' }}>{kpis.total_bookings} đơn</strong>
          <small style={{ color: 'var(--muted)', display: 'block', marginTop: 4 }}>
            Huỷ: {kpis.cancel_rate}% · No-show: {kpis.no_show_rate}%
          </small>
        </div>
      </div>

      {/* Biểu đồ cột SVG GMV & Hoa hồng theo ngày */}
      <div className="content-card" style={{ padding: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
          <div>
            <h2 style={{ fontSize: 16, margin: '0 0 4px', color: 'var(--forest)' }}>Biểu đồ tăng trưởng GMV & Hoa hồng theo ngày</h2>
            <small style={{ color: 'var(--muted)' }}>
              Cột xanh lá nhạt: GMV · Cột xanh đậm: Hoa hồng sàn ({formatMoney(kpis.commission_accrued)}) — chưa gồm tiền cọc bị phạt
            </small>
          </div>
          <div style={{ display: 'flex', gap: 14, fontSize: 12 }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
              <span style={{ width: 12, height: 12, background: 'var(--pitch-soft)', border: '1px solid var(--pitch)', borderRadius: 2 }} />
              GMV
            </span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
              <span style={{ width: 12, height: 12, background: 'var(--pitch)', borderRadius: 2 }} />
              Hoa hồng
            </span>
          </div>
        </div>

        {daily.length === 0 ? (
          <p style={{ textAlign: 'center', color: 'var(--muted)', padding: 30 }}>Chưa có dữ liệu giao dịch trong khoảng thời gian này.</p>
        ) : (
          <div style={{ overflowX: 'auto', paddingBottom: 8 }}>
            <div style={{ minWidth: Math.max(600, daily.length * 36), height: 180, position: 'relative', display: 'flex', alignItems: 'flex-end', gap: 8, paddingBottom: 24 }}>
              {daily.map((item, idx) => {
                const gmvHeight = Math.max(4, Math.round((item.gmv / maxGmv) * 140))
                const commHeight = Math.max(2, Math.round((item.commission / maxGmv) * 140))
                return (
                  <div
                    key={item.date || idx}
                    title={`${item.date}: GMV ${formatMoney(item.gmv)}, Hoa hồng ${formatMoney(item.commission)} (${item.booking_count} đơn)`}
                    style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', height: '100%', justifyContent: 'flex-end', cursor: 'pointer' }}
                  >
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 2, width: '100%', justifyContent: 'center' }}>
                      <div
                        style={{
                          width: '45%',
                          height: gmvHeight,
                          background: item.gmv > 0 ? 'var(--pitch-soft)' : '#f0f0f0',
                          border: item.gmv > 0 ? '1px solid var(--pitch)' : 'none',
                          borderRadius: '2px 2px 0 0',
                          transition: 'height 0.3s ease',
                        }}
                      />
                      <div
                        style={{
                          width: '45%',
                          height: commHeight,
                          background: item.commission > 0 ? 'var(--pitch)' : '#e0e0e0',
                          borderRadius: '2px 2px 0 0',
                          transition: 'height 0.3s ease',
                        }}
                      />
                    </div>
                    <span style={{ fontSize: 10, color: 'var(--muted)', marginTop: 6, position: 'absolute', bottom: 0 }}>
                      {item.label}
                    </span>
                  </div>
                )
              })}
            </div>
          </div>
        )}
      </div>

      {/* Top Sân thể thao trong kỳ */}
      <div className="content-card" style={{ padding: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
          <h2 style={{ fontSize: 16, margin: 0, color: 'var(--forest)' }}>Top sân thể thao sôi động</h2>
          <Link to="/admin/fields" style={{ fontSize: 12, color: 'var(--pitch)', textDecoration: 'none' }}>
            Xem tất cả →
          </Link>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {data.top_fields.length === 0 ? (
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Chưa có sân nào phát sinh đơn trong kỳ.</span>
          ) : (
            data.top_fields.map((f, i) => (
              <div key={f.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ width: 22, height: 22, borderRadius: '50%', background: i === 0 ? 'var(--pitch)' : 'var(--surface)', color: i === 0 ? '#fff' : 'var(--forest)', display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 700 }}>
                    {i + 1}
                  </span>
                  <div>
                    <strong style={{ fontSize: 13, display: 'block' }}>{f.name}</strong>
                    <small style={{ color: 'var(--muted)' }}>{f.field_type?.name ?? 'Sân'}</small>
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--forest)' }}>
                    {(f as any).bookings_count ?? 0} lượt đặt
                  </span>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  )
}
