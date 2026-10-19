import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Building2, CircleDollarSign, Headphones, Save, ShieldCheck } from 'lucide-react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { useToast } from '../../components/Toast'

export function AdminSettingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [formData, setFormData] = useState<Record<string, any>>({})

  const { data, isLoading, isError } = useQuery<{ data: Record<string, any> }>({
    queryKey: ['admin', 'settings'],
    queryFn: async () => (await api.get<{ data: Record<string, any> }>('/admin/settings')).data,
  })

  useEffect(() => {
    if (data?.data) {
      setFormData(data.data)
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: async (payload: Record<string, any>) => {
      return (await api.put('/admin/settings', payload)).data
    },
    onSuccess: () => {
      toast.success('Cập nhật cấu hình hệ thống thành công!')
      void client.invalidateQueries({ queryKey: ['admin', 'settings'] })
      void client.invalidateQueries({ queryKey: ['public-settings'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu cấu hình hệ thống.')
    },
  })

  const handleChange = (key: string, val: any) => {
    setFormData((prev) => ({ ...prev, [key]: val }))
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    mutation.mutate(formData)
  }

  if (isLoading) {
    return <div className="query-state query-state--loading" aria-live="polite">Đang tải cấu hình hệ thống...</div>
  }
  if (isError) {
    return <div className="query-state query-state--error" role="alert">Không thể tải cấu hình.</div>
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      <div>
        <h1 style={{ fontSize: 24, margin: '0 0 4px', color: 'var(--forest)' }}>Cấu hình hệ thống</h1>
        <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
          Thiết lập các tham số vận hành: chính sách cọc, hạn thanh toán, hoa hồng nền tảng và tài khoản nhận phí.
        </p>
      </div>

      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 24, maxWidth: 840 }}>
        {/* Nhóm 1: Chính sách cọc & Đặt chỗ */}
        <div className="content-card" style={{ padding: 22 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
            <ShieldCheck size={20} style={{ color: 'var(--pitch)' }} />
            <h2 style={{ fontSize: 17, margin: 0, color: 'var(--forest)' }}>Chính sách đặt cọc & Huỷ đơn</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 16 }}>
            <label>
              <span>Tỉ lệ đặt cọc (%)</span>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.deposit_percent ?? 30}
                onChange={(e) => handleChange('deposit_percent', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Mặc định 30% tổng tiền đơn</small>
            </label>

            <label>
              <span>Hạn chuyển khoản cọc sau duyệt (giờ)</span>
              <input
                type="number"
                min="1"
                max="72"
                value={formData.payment_window_hours ?? 2}
                onChange={(e) => handleChange('payment_window_hours', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Quá hạn đơn tự động huỷ</small>
            </label>

            <label>
              <span>Huỷ miễn phí trước giờ chơi (giờ)</span>
              <input
                type="number"
                min="1"
                max="72"
                value={formData.free_cancel_hours ?? 4}
                onChange={(e) => handleChange('free_cancel_hours', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Huỷ sát hơn giờ này mất cọc & -15 điểm</small>
            </label>

            <label>
              <span>Ân hạn huỷ miễn phí sau duyệt (giờ)</span>
              <input
                type="number"
                min="1"
                max="24"
                value={formData.confirmed_cancel_window_hours ?? 2}
                onChange={(e) => handleChange('confirmed_cancel_window_hours', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Cho phép huỷ hoàn 100% cọc sau khi duyệt</small>
            </label>

            <label>
              <span>Số đơn chờ duyệt tối đa mỗi khách</span>
              <input
                type="number"
                min="1"
                max="20"
                value={formData.max_pending_bookings ?? 3}
                onChange={(e) => handleChange('max_pending_bookings', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Tránh spam giữ chỗ nhiều sân</small>
            </label>

            <label>
              <span>Cho phép đặt sân trước tối đa (ngày)</span>
              <input
                type="number"
                min="1"
                max="365"
                value={formData.max_advance_days ?? 30}
                onChange={(e) => handleChange('max_advance_days', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Không cho đặt quá xa tránh bỏ trống</small>
            </label>
          </div>
        </div>

        {/* Nhóm 2: Hoa hồng & Đối soát */}
        <div className="content-card" style={{ padding: 22 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
            <CircleDollarSign size={20} style={{ color: 'var(--pitch)' }} />
            <h2 style={{ fontSize: 17, margin: 0, color: 'var(--forest)' }}>Hoa hồng nền tảng & Kỳ đối soát</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 16 }}>
            <label>
              <span>Tỉ lệ hoa hồng sàn (%)</span>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.commission_percent ?? 8}
                onChange={(e) => handleChange('commission_percent', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Thu trên giá trị đơn hoàn thành / no-show</small>
            </label>

            <label>
              <span>Hạn chủ sân nộp phí (ngày)</span>
              <input
                type="number"
                min="1"
                max="30"
                value={formData.statement_due_days ?? 7}
                onChange={(e) => handleChange('statement_due_days', Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 11 }}>Quá hạn tự động tạm khoá duyệt đơn</small>
            </label>
          </div>
        </div>

        {/* Nhóm 3: Tài khoản nhận hoa hồng nền tảng */}
        <div className="content-card" style={{ padding: 22 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
            <Building2 size={20} style={{ color: 'var(--pitch)' }} />
            <h2 style={{ fontSize: 17, margin: 0, color: 'var(--forest)' }}>Tài khoản ngân hàng nhận hoa hồng của SportHub</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 16 }}>
            <label style={{ gridColumn: '1 / -1' }}>
              <span>Tên ngân hàng</span>
              <input
                type="text"
                value={formData.platform_bank_name ?? ''}
                onChange={(e) => handleChange('platform_bank_name', e.target.value)}
                placeholder="Ví dụ: Vietcombank - Chi nhánh Thăng Long"
              />
            </label>

            <label>
              <span>Số tài khoản</span>
              <input
                type="text"
                value={formData.platform_bank_account ?? ''}
                onChange={(e) => handleChange('platform_bank_account', e.target.value)}
                placeholder="1234567890"
              />
            </label>

            <label>
              <span>Chủ tài khoản (viết hoa không dấu)</span>
              <input
                type="text"
                value={formData.platform_bank_holder ?? ''}
                onChange={(e) => handleChange('platform_bank_holder', e.target.value)}
                placeholder="CONG TY SPORTHUB VIET NAM"
              />
            </label>
          </div>
        </div>

        {/* Nhóm 4: Kênh hỗ trợ */}
        <div className="content-card" style={{ padding: 22 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
            <Headphones size={20} style={{ color: 'var(--pitch)' }} />
            <h2 style={{ fontSize: 17, margin: 0, color: 'var(--forest)' }}>Kênh hỗ trợ người dùng & chủ sân</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 16 }}>
            <label>
              <span>Email hỗ trợ</span>
              <input
                type="email"
                value={formData.support_email ?? ''}
                onChange={(e) => handleChange('support_email', e.target.value)}
                placeholder="hotro@sporthub.vn"
              />
            </label>

            <label>
              <span>Hotline hỗ trợ</span>
              <input
                type="text"
                value={formData.support_hotline ?? ''}
                onChange={(e) => handleChange('support_hotline', e.target.value)}
                placeholder="1900 6868"
              />
            </label>
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
          <button type="submit" className="button button--brand button--lg" disabled={mutation.isPending}>
            <Save size={18} style={{ marginRight: 6 }} />
            {mutation.isPending ? 'Đang lưu cấu hình...' : 'Lưu cấu hình hệ thống'}
          </button>
        </div>
      </form>
    </div>
  )
}
