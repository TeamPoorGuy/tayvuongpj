import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { Building2 } from 'lucide-react'
import { type FormEvent } from 'react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { StatusBadge } from '../../components/StatusBadge'
import type { ApiEnvelope, OwnerProfile } from '../../types/api'
import { PageTitle } from './CustomerPages'

export function OwnerApplicationPage() {
  const client = useQueryClient()
  const application = useQuery({ queryKey: ['owner-application'], queryFn: async () => (await api.get<ApiEnvelope<OwnerProfile | null>>('/customer/owner-application')).data.data })
  const submit = useMutation({
    mutationFn: async (payload: FormData) => api.post('/customer/owner-application', payload),
    onSuccess: () => { void client.invalidateQueries({ queryKey: ['owner-application'] }); void client.invalidateQueries({ queryKey: ['auth', 'me'] }) },
  })

  const data = application.data
  const status = data?.verification_status

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    submit.mutate(new FormData(event.currentTarget))
  }

  return <>
    <PageTitle icon={<Building2 />} kicker="Chủ sân" title="Đăng ký làm chủ sân" copy="Điền đầy đủ thông tin để admin xét duyệt hồ sơ của bạn." />

    {status === 'approved' && <div className="notice notice--success">Hồ sơ của bạn đã được duyệt. <Link to="/owner">Vào trang quản lý chủ sân</Link>.</div>}
    {status === 'pending' && <div className="notice">Hồ sơ đang chờ admin xét duyệt. Vui lòng quay lại sau.</div>}
    {status === 'suspended' && <div className="notice notice--error">Quyền chủ sân đang bị tạm khoá{data?.rejection_reason ? `: ${data.rejection_reason}` : ''}. Vui lòng liên hệ quản trị viên.</div>}
    {status === 'rejected' && data?.rejection_reason && <div className="notice notice--error">Hồ sơ bị từ chối: {data.rejection_reason}. Vui lòng sửa và gửi lại.</div>}

    {status !== 'approved' && status !== 'pending' && status !== 'suspended' && (
      <form key={data?.id ?? 'new'} className="panel-form" onSubmit={onSubmit}>
        <h2>Thông tin chủ sân</h2>
        <div className="form-grid">
          <label><span>Họ tên chủ sân</span><input name="owner_name" defaultValue={data?.owner_name ?? ''} required /></label>
          <label><span>Số CCCD/CMND</span><input name="owner_id_number" defaultValue={data?.owner_id_number ?? ''} required /></label>
        </div>

        <h2>Cơ sở kinh doanh</h2>
        <label><span>Tên cơ sở</span><input name="business_name" defaultValue={data?.business_name ?? ''} required /></label>
        <div className="form-grid">
          <label><span>Địa chỉ kinh doanh</span><input name="business_address" defaultValue={data?.business_address ?? ''} required /></label>
          <label><span>SĐT kinh doanh</span><input name="business_phone" defaultValue={data?.business_phone ?? ''} required /></label>
        </div>
        <label><span>Mô tả</span><textarea name="description" defaultValue={data?.description ?? ''} /></label>

        <h2>Giấy phép kinh doanh</h2>
        <label><span>Số giấy phép</span><input name="business_license" defaultValue={data?.business_license ?? ''} required /></label>
        <label><span>Ảnh/PDF giấy phép kinh doanh{data?.documents?.some((d) => d.type === 'business_license') ? ' (đã có, chọn để thay)' : ''}</span><input name="documents[business_license]" type="file" accept="image/*,.pdf" required={!data?.documents?.some((d) => d.type === 'business_license')} /></label>
        <label><span>Giấy tờ quyền sử dụng sân / hợp đồng thuê (không bắt buộc)</span><input name="documents[land_right]" type="file" accept="image/*,.pdf" /></label>

        <h2>Ảnh CCCD</h2>
        <div className="form-grid">
          <label><span>Mặt trước</span><input name="documents[id_front]" type="file" accept="image/*,.pdf" required={!data?.documents?.some((d) => d.type === 'id_front')} /></label>
          <label><span>Mặt sau</span><input name="documents[id_back]" type="file" accept="image/*,.pdf" required={!data?.documents?.some((d) => d.type === 'id_back')} /></label>
        </div>

        <h2>Tài khoản nhận tiền</h2>
        <div className="form-grid">
          <label><span>Ngân hàng</span><input name="bank_name" defaultValue={data?.bank_name ?? ''} required /></label>
          <label><span>Số tài khoản</span><input name="bank_account_number" defaultValue={data?.bank_account_number ?? ''} inputMode="numeric" required /></label>
        </div>
        <label><span>Tên chủ tài khoản (trùng tên trên CCCD)</span><input name="bank_account_holder" defaultValue={data?.bank_account_holder ?? ''} required /></label>

        <label className="check"><input type="checkbox" name="accept_terms" value="1" required /> <span>Tôi cam kết thông tin là đúng sự thật, đồng ý chính sách huỷ/hoàn và xử lý tranh chấp của SportHub, và đồng ý để SportHub xử lý dữ liệu cá nhân theo Nghị định 13/2023/NĐ-CP.</span></label>

        {submit.isError && <div className="notice notice--error">{errorMessage(submit.error)}</div>}
        <button disabled={submit.isPending} className="button button--brand">{submit.isPending ? 'Đang gửi...' : status === 'rejected' ? 'Gửi lại hồ sơ' : 'Gửi hồ sơ đăng ký'}</button>
      </form>
    )}
    {status && <StatusBadge status={status} />}
  </>
}
