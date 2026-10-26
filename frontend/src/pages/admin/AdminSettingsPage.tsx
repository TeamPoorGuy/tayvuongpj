import { digitsOnly, toBankHolder } from '../../utils/input'
import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Building2, CircleDollarSign, Headphones, Save, ShieldCheck, Sliders } from 'lucide-react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { useToast } from '../../components/Toast'
import { PageTitle } from '../customer/CustomerPages'

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
    if (Object.values(formData).some((v) => v === '' || v === null)) {
      toast.error('Không được để trống ô cấu hình nào.', 'Vui lòng điền đầy đủ.')
      return
    }
    mutation.mutate(formData)
  }

  if (isLoading) {
    return <div className="query-state query-state--loading" aria-live="polite">Đang tải cấu hình hệ thống...</div>
  }
  if (isError) {
    return <div className="query-state query-state--error" role="alert">Không thể tải cấu hình.</div>
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24, maxWidth: 960 }}>
      <PageTitle
        icon={<Sliders />}
        kicker="Quản trị tham số"
        title="Cấu hình hệ thống"
        copy="Thiết lập các tham số vận hành: chính sách cọc & hoàn tiền, hạn chi trả chủ sân, hoa hồng nền tảng và tài khoản nhận tiền thu hộ."
      />

      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
        {/* Nhóm 1: Chính sách cọc & Đặt chỗ */}
        <div className="content-card panel-form" style={{ padding: 24 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
            <ShieldCheck size={22} style={{ color: 'var(--forest)' }} />
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest)' }}>Chính sách đặt cọc & Huỷ đơn</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 20 }}>
            <label style={{ margin: 0 }}>
              <span>Tỉ lệ đặt cọc (%)</span>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.deposit_percent ?? 30}
                onChange={(e) => handleChange('deposit_percent', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Mặc định 30% tổng tiền đơn</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Hạn mức rút tự duyệt mỗi 24 giờ (đ)</span>
              <input
                type="number"
                min="0"
                max="100000000"
                step="100000"
                value={formData.withdraw_auto_limit ?? 2000000}
                onChange={(e) => handleChange('withdraw_auto_limit', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Tổng các lệnh rút tự duyệt của một người trong 24 giờ không vượt mức này; vượt thì chờ admin chuyển khoản</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Thời gian giữ chỗ chờ chuyển khoản (phút)</span>
              <input
                type="number"
                min="5"
                max="120"
                value={formData.payment_hold_minutes ?? 15}
                onChange={(e) => handleChange('payment_hold_minutes', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Quá hạn chưa nhận tiền thì nhả chỗ</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Hoàn 100% nếu huỷ trước (giờ)</span>
              <input
                type="number"
                min="1"
                max="168"
                value={formData.refund_full_hours ?? 24}
                onChange={(e) => handleChange('refund_full_hours', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Mốc hoàn toàn bộ tiền đã chuyển</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Hoàn một phần nếu huỷ trước (giờ)</span>
              <input
                type="number"
                min="0"
                max="168"
                value={formData.refund_partial_hours ?? 6}
                onChange={(e) => handleChange('refund_partial_hours', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Phải nhỏ hơn mốc hoàn 100%</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Tỉ lệ hoàn một phần (%)</span>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.refund_partial_percent ?? 50}
                onChange={(e) => handleChange('refund_partial_percent', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Sát giờ hơn mốc trên thì không hoàn</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Trừ điểm uy tín nếu huỷ dưới (giờ)</span>
              <input
                type="number"
                min="1"
                max="72"
                value={formData.free_cancel_hours ?? 4}
                onChange={(e) => handleChange('free_cancel_hours', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Huỷ sát hơn giờ này bị -15 điểm</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Số lượt giữ chỗ chưa thanh toán tối đa</span>
              <input
                type="number"
                min="1"
                max="20"
                value={formData.max_pending_bookings ?? 3}
                onChange={(e) => handleChange('max_pending_bookings', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Tránh spam giữ chỗ nhiều sân</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Cho phép đặt sân trước tối đa (ngày)</span>
              <input
                type="number"
                min="1"
                max="365"
                value={formData.max_advance_days ?? 30}
                onChange={(e) => handleChange('max_advance_days', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Không cho đặt quá xa tránh bỏ trống</small>
            </label>
          </div>
        </div>

        {/* Nhóm 2: Hoa hồng & Đối soát */}
        <div className="content-card panel-form" style={{ padding: 24 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
            <CircleDollarSign size={22} style={{ color: 'var(--forest)' }} />
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest)' }}>Hoa hồng nền tảng & Chi trả chủ sân</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 20 }}>
            <label style={{ margin: 0 }}>
              <span>Tỉ lệ hoa hồng sàn (%)</span>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.commission_percent ?? 8}
                onChange={(e) => handleChange('commission_percent', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Thu trên giá trị đơn hoàn thành / no-show</small>
            </label>

            <label style={{ margin: 0 }}>
              <span>Hạn SportHub chi trả chủ sân (ngày)</span>
              <input
                type="number"
                min="1"
                max="30"
                value={formData.statement_due_days ?? 7}
                onChange={(e) => handleChange('statement_due_days', e.target.value === '' ? '' : Number(e.target.value))}
              />
              <small style={{ color: 'var(--muted)', fontSize: 12 }}>Hạn cam kết chuyển khoản sau khi chốt đối soát tháng</small>
            </label>
          </div>
        </div>

        {/* Nhóm 3: Tài khoản nhận hoa hồng nền tảng */}
        <div className="content-card panel-form" style={{ padding: 24 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
            <Building2 size={22} style={{ color: 'var(--forest)' }} />
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest)' }}>Tài khoản ngân hàng nhận tiền khách thanh toán (thu hộ)</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 20 }}>
            <label style={{ gridColumn: '1 / -1', margin: 0 }}>
              <span>Tên ngân hàng</span>
              <input
                type="text"
                value={formData.platform_bank_name ?? ''}
                onChange={(e) => handleChange('platform_bank_name', e.target.value)}
                placeholder="Ví dụ: Vietcombank - Chi nhánh Thăng Long"
              />
            </label>

            <label style={{ margin: 0 }}>
              <span>Số tài khoản</span>
              <input
                type="text"
                value={formData.platform_bank_account ?? ''}
                onChange={(e) => handleChange('platform_bank_account', digitsOnly(e.target.value, 20))}
                placeholder="1234567890"
              />
            </label>

            <label style={{ margin: 0 }}>
              <span>Chủ tài khoản (viết hoa không dấu)</span>
              <input
                type="text"
                value={formData.platform_bank_holder ?? ''}
                onChange={(e) => handleChange('platform_bank_holder', toBankHolder(e.target.value))}
                placeholder="CONG TY SPORTHUB VIET NAM"
              />
            </label>
          </div>
        </div>

        {/* Nhóm 4: Kênh hỗ trợ */}
        <div className="content-card panel-form" style={{ padding: 24 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
            <Headphones size={22} style={{ color: 'var(--forest)' }} />
            <h2 style={{ fontSize: 18, margin: 0, color: 'var(--forest)' }}>Kênh hỗ trợ người dùng & chủ sân</h2>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 20 }}>
            <label style={{ margin: 0 }}>
              <span>Email hỗ trợ</span>
              <input
                type="email"
                value={formData.support_email ?? ''}
                onChange={(e) => handleChange('support_email', e.target.value)}
                placeholder="hotro@sporthub.vn"
              />
            </label>

            <label style={{ margin: 0 }}>
              <span>Hotline hỗ trợ</span>
              <input
                type="text"
                value={formData.support_hotline ?? ''}
                onChange={(e) => handleChange('support_hotline', e.target.value.replace(/[^\d ]/g, '').slice(0, 15))}
                placeholder="1900 6868"
              />
            </label>
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8, marginBottom: 24 }}>
          <button type="submit" className="button button--brand button--large" disabled={mutation.isPending}>
            <Save size={18} style={{ marginRight: 8 }} />
            {mutation.isPending ? 'Đang lưu cấu hình...' : 'Lưu cấu hình hệ thống'}
          </button>
        </div>
      </form>
    </div>
  )
}
