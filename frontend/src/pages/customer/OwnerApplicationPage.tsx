import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import {
  ArrowLeft,
  ArrowRight,
  Building2,
  CheckCircle2,
  FileCheck2,
  FileText,
  IdCard,
  Send,
  ShieldAlert,
  Store,
  UserCheck,
} from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { useToast } from '../../components/Toast'
import type { ApiEnvelope, OwnerProfile } from '../../types/api'
import { PageTitle } from './CustomerPages'

const STEPS = [
  { id: 1, label: 'Chủ cơ sở', icon: UserCheck, desc: 'Họ tên & số CCCD' },
  { id: 2, label: 'Cơ sở kinh doanh', icon: Store, desc: 'Tên, địa chỉ & hotline' },
  { id: 3, label: 'Giấy phép & Sân', icon: FileText, desc: 'GPKD & quyền sử dụng' },
  { id: 4, label: 'Xác minh CCCD', icon: IdCard, desc: 'Ảnh CCCD 2 mặt & Gửi' },
]

export function OwnerApplicationPage() {
  const client = useQueryClient()
  const { success, error } = useToast()
  const [step, setStep] = useState(1)

  const application = useQuery({
    queryKey: ['owner-application'],
    queryFn: async () =>
      (await api.get<ApiEnvelope<OwnerProfile | null>>('/customer/owner-application')).data.data,
  })

  const submit = useMutation({
    mutationFn: async (payload: FormData) => api.post('/customer/owner-application', payload),
    onSuccess: () => {
      success('Hồ sơ đăng ký chủ sân đã được gửi đến ban quản trị!', 'Gửi hồ sơ thành công')
      void client.invalidateQueries({ queryKey: ['owner-application'] })
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err) => {
      error(errorMessage(err), 'Không thể gửi hồ sơ')
    },
  })

  const data = application.data
  const status = data?.verification_status

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    submit.mutate(new FormData(event.currentTarget))
  }

  return (
    <>
      <PageTitle
        icon={<Building2 />}
        kicker="Đối tác kinh doanh"
        title="Đăng ký làm chủ sân"
        copy="Hoàn thiện 4 bước để ban quản trị thẩm định cơ sở thể thao của bạn."
      />

      {status === 'approved' && (
        <div className="notice notice--success">
          <CheckCircle2 size={18} /> Hồ sơ của bạn đã được phê duyệt thành công.{' '}
          <Link to="/owner" style={{ fontWeight: 800, textDecoration: 'underline', marginLeft: 6 }}>
            Vào Trung tâm quản lý chủ sân
          </Link>
        </div>
      )}

      {status === 'pending' && (
        <div className="notice notice--soft">
          <FileCheck2 size={18} /> Hồ sơ đang trong hàng đợi xét duyệt bởi ban quản trị. Kết quả sẽ được gửi
          qua email và thông báo tại đây.
        </div>
      )}

      {status === 'suspended' && (
        <div className="notice notice--error">
          <ShieldAlert size={18} /> Quyền chủ sân của bạn đang bị tạm ngưng
          {data?.rejection_reason ? `: ${data.rejection_reason}` : ''}. Vui lòng liên hệ ban quản trị để giải quyết.
        </div>
      )}

      {status === 'rejected' && data?.rejection_reason && (
        <div className="notice notice--error">
          <ShieldAlert size={18} /> Hồ sơ trước đây bị từ chối: <strong>{data.rejection_reason}</strong>. Vui lòng cập nhật
          lại thông tin chính xác theo các bước dưới đây và gửi lại.
        </div>
      )}

      {status !== 'approved' && status !== 'pending' && status !== 'suspended' && (
        <div className="wizard-card">
          {/* Step Progress Bar */}
          <div className="wizard-stepper">
            {STEPS.map((s) => {
              const Icon = s.icon
              const isPassed = step > s.id
              const isCurrent = step === s.id
              return (
                <button
                  key={s.id}
                  type="button"
                  className={`wizard-step ${isCurrent ? 'wizard-step--current' : ''} ${
                    isPassed ? 'wizard-step--passed' : ''
                  }`}
                  onClick={() => setStep(s.id)}
                >
                  <div className="wizard-step__circle">
                    {isPassed ? <CheckCircle2 size={16} /> : <Icon size={16} />}
                  </div>
                  <div className="wizard-step__text">
                    <small>Bước {s.id}</small>
                    <strong>{s.label}</strong>
                  </div>
                </button>
              )
            })}
          </div>

          <form key={data?.id ?? 'new'} className="panel-form" onSubmit={onSubmit}>
            {/* Step 1: Owner Personal Info */}
            <div style={{ display: step === 1 ? 'block' : 'none' }}>
              <h2>1. Thông tin người đại diện</h2>
              <p className="form-help">Thông tin cá nhân đứng tên đăng ký và chịu trách nhiệm pháp lý.</p>
              <div className="form-grid">
                <label>
                  <span>Họ và tên chủ sân</span>
                  <input
                    name="owner_name"
                    defaultValue={data?.owner_name ?? ''}
                    placeholder="Nguyễn Văn A"
                    required={step === 1}
                  />
                </label>
                <label>
                  <span>Số CCCD / CMND</span>
                  <input
                    name="owner_id_number"
                    defaultValue={data?.owner_id_number ?? ''}
                    placeholder="12 chữ số trên thẻ CCCD"
                    required={step === 1}
                  />
                </label>
              </div>
              <div className="wizard-actions">
                <div />
                <button
                  type="button"
                  className="button button--brand"
                  onClick={() => setStep(2)}
                >
                  Tiếp tục: Cơ sở <ArrowRight size={16} />
                </button>
              </div>
            </div>

            {/* Step 2: Business Info */}
            <div style={{ display: step === 2 ? 'block' : 'none' }}>
              <h2>2. Cơ sở kinh doanh thể thao</h2>
              <p className="form-help">Thông tin sẽ hiển thị công khai trên website cho khách hàng liên hệ.</p>
              <label>
                <span>Tên cơ sở kinh doanh / Câu lạc bộ</span>
                <input
                  name="business_name"
                  defaultValue={data?.business_name ?? ''}
                  placeholder="CLB Thể Thao Tây Vương"
                  required={step === 2}
                />
              </label>
              <div className="form-grid">
                <label>
                  <span>Địa chỉ kinh doanh</span>
                  <input
                    name="business_address"
                    defaultValue={data?.business_address ?? ''}
                    placeholder="Số 123 Đường ABC, Quận XYZ, TP..."
                    required={step === 2}
                  />
                </label>
                <label>
                  <span>Số điện thoại hotline đặt sân</span>
                  <input
                    name="business_phone"
                    defaultValue={data?.business_phone ?? ''}
                    placeholder="0988776655"
                    required={step === 2}
                  />
                </label>
              </div>
              <label>
                <span>Mô tả giới thiệu cơ sở</span>
                <textarea
                  name="description"
                  rows={3}
                  defaultValue={data?.description ?? ''}
                  placeholder="Giới thiệu về tiện ích, bãi đỗ xe, căn tin, chất lượng sân..."
                />
              </label>
              <div className="wizard-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setStep(1)}
                >
                  <ArrowLeft size={16} /> Quay lại
                </button>
                <button
                  type="button"
                  className="button button--brand"
                  onClick={() => setStep(3)}
                >
                  Tiếp tục: Giấy phép <ArrowRight size={16} />
                </button>
              </div>
            </div>

            {/* Step 3: Licenses & Land Rights */}
            <div style={{ display: step === 3 ? 'block' : 'none' }}>
              <h2>3. Giấy phép kinh doanh & Hồ sơ sân</h2>
              <p className="form-help">Hồ sơ pháp lý được bảo mật riêng tư, chỉ dùng cho admin đối soát duyệt sân.</p>
              <label>
                <span>Mã số giấy phép kinh doanh / Đăng ký hộ kinh doanh</span>
                <input
                  name="business_license"
                  defaultValue={data?.business_license ?? ''}
                  placeholder="GPKD-123456 hoặc MST..."
                  required={step === 3}
                />
              </label>
              <label>
                <span>
                  Ảnh chụp hoặc PDF Giấy phép kinh doanh
                  {data?.documents?.some((d) => d.type === 'business_license')
                    ? ' (đã có file tải lên trước đó, chọn file mới nếu muốn thay)'
                    : ''}
                </span>
                <input
                  name="documents[business_license]"
                  type="file"
                  accept="image/*,.pdf"
                  required={!data?.documents?.some((d) => d.type === 'business_license')}
                />
              </label>
              <label>
                <span>Giấy tờ quyền sử dụng sân / Hợp đồng thuê mặt bằng (không bắt buộc)</span>
                <input name="documents[land_right]" type="file" accept="image/*,.pdf" />
              </label>
              <div className="wizard-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setStep(2)}
                >
                  <ArrowLeft size={16} /> Quay lại
                </button>
                <button
                  type="button"
                  className="button button--brand"
                  onClick={() => setStep(4)}
                >
                  Tiếp tục: Ảnh CCCD <ArrowRight size={16} />
                </button>
              </div>
            </div>

            {/* Step 4: ID Card & Submission */}
            <div style={{ display: step === 4 ? 'block' : 'none' }}>
              <h2>4. Xác minh Căn cước công dân</h2>
              <p className="form-help">Ảnh chụp bản gốc CCCD rõ nét, không bị mờ lóa hoặc cắt góc.</p>
              <div className="form-grid">
                <label>
                  <span>
                    Mặt trước CCCD
                    {data?.documents?.some((d) => d.type === 'id_front') ? ' (đã có file)' : ''}
                  </span>
                  <input
                    name="documents[id_front]"
                    type="file"
                    accept="image/*,.pdf"
                    required={!data?.documents?.some((d) => d.type === 'id_front')}
                  />
                </label>
                <label>
                  <span>
                    Mặt sau CCCD
                    {data?.documents?.some((d) => d.type === 'id_back') ? ' (đã có file)' : ''}
                  </span>
                  <input
                    name="documents[id_back]"
                    type="file"
                    accept="image/*,.pdf"
                    required={!data?.documents?.some((d) => d.type === 'id_back')}
                  />
                </label>
              </div>

              {submit.isError && (
                <div className="notice notice--error">{errorMessage(submit.error)}</div>
              )}

              <div className="wizard-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setStep(3)}
                >
                  <ArrowLeft size={16} /> Quay lại
                </button>
                <button
                  type="submit"
                  disabled={submit.isPending}
                  className="button button--brand button--lg"
                >
                  <Send size={16} /> {submit.isPending ? 'Đang nộp hồ sơ...' : 'Xác nhận & Nộp hồ sơ xét duyệt'}
                </button>
              </div>
            </div>
          </form>
        </div>
      )}
    </>
  )
}
