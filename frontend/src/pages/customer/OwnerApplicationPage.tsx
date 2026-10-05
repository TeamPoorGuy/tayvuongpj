import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Building2,
  CheckCircle2,
  CreditCard,
  FileCheck2,
  Send,
  ShieldAlert,
  ShieldCheck,
  Store,
  Upload,
  UserCheck,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { useToast } from '../../components/Toast'
import type { ApiEnvelope, OwnerProfile } from '../../types/api'
import { PageTitle } from './CustomerPages'

const STEPS = [
  { id: 1, label: 'Đại diện', icon: UserCheck, desc: 'Họ tên & số CCCD' },
  { id: 2, label: 'Cơ sở', icon: Store, desc: 'Tên, địa chỉ & hotline' },
  { id: 3, label: 'Ngân hàng & GPKD', icon: CreditCard, desc: 'Tài khoản & giấy phép' },
  { id: 4, label: 'Xác thực & Gửi', icon: ShieldCheck, desc: 'CCCD & điều khoản' },
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

  const data = application.data
  const status = data?.verification_status

  // Controlled form state
  const [formData, setFormData] = useState({
    owner_name: '',
    owner_id_number: '',
    business_name: '',
    business_address: '',
    business_phone: '',
    description: '',
    bank_name: '',
    bank_account_number: '',
    bank_account_holder: '',
    business_license: '',
    accept_terms: false,
  })

  // File uploads state
  const [files, setFiles] = useState<{
    business_license?: File | null
    land_right?: File | null
    id_front?: File | null
    id_back?: File | null
  }>({})

  // Validation error state
  const [errors, setErrors] = useState<Record<string, string>>({})

  // Prefill data when fetched
  useEffect(() => {
    if (data) {
      setFormData((prev) => ({
        owner_name: data.owner_name ?? prev.owner_name,
        owner_id_number: data.owner_id_number ?? prev.owner_id_number,
        business_name: data.business_name ?? prev.business_name,
        business_address: data.business_address ?? prev.business_address,
        business_phone: data.business_phone ?? prev.business_phone,
        description: data.description ?? prev.description,
        bank_name: data.bank_name ?? prev.bank_name,
        bank_account_number: data.bank_account_number ?? prev.bank_account_number,
        bank_account_holder: data.bank_account_holder ?? prev.bank_account_holder,
        business_license: data.business_license ?? prev.business_license,
        accept_terms: prev.accept_terms,
      }))
    }
  }, [data])

  const submit = useMutation({
    mutationFn: async (payload: FormData) => api.post('/customer/owner-application', payload),
    onSuccess: () => {
      success('Hồ sơ đăng ký chủ sân đã được gửi đến ban quản trị!', 'Gửi hồ sơ thành công')
      void client.invalidateQueries({ queryKey: ['owner-application'] })
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err: any) => {
      const serverErrors = err?.response?.data?.errors as Record<string, string[]> | undefined
      if (serverErrors) {
        const mappedErrors: Record<string, string> = {}
        for (const [k, v] of Object.entries(serverErrors)) {
          mappedErrors[k] = v[0] || 'Thông tin không hợp lệ'
        }
        setErrors(mappedErrors)

        // Automatically switch back to the first step containing an error
        if (mappedErrors.owner_name || mappedErrors.owner_id_number) {
          setStep(1)
        } else if (
          mappedErrors.business_name ||
          mappedErrors.business_address ||
          mappedErrors.business_phone ||
          mappedErrors.description
        ) {
          setStep(2)
        } else if (
          mappedErrors.bank_name ||
          mappedErrors.bank_account_number ||
          mappedErrors.bank_account_holder ||
          mappedErrors.business_license ||
          mappedErrors['documents.business_license']
        ) {
          setStep(3)
        } else if (
          mappedErrors['documents.id_front'] ||
          mappedErrors['documents.id_back'] ||
          mappedErrors.accept_terms
        ) {
          setStep(4)
        }
      }
      error(errorMessage(err), 'Không thể gửi hồ sơ')
    },
  })

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target
    const checked = (e.target as HTMLInputElement).checked
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }))
    if (errors[name]) {
      setErrors((prev) => {
        const next = { ...prev }
        delete next[name]
        return next
      })
    }
  }

  const handleFileChange = (type: 'business_license' | 'land_right' | 'id_front' | 'id_back', file: File | null) => {
    setFiles((prev) => ({ ...prev, [type]: file }))
    const errorKey = `documents.${type}`
    if (errors[errorKey]) {
      setErrors((prev) => {
        const next = { ...prev }
        delete next[errorKey]
        return next
      })
    }
  }

  // Validate a specific step
  const validateStep = (s: number): boolean => {
    const newErrors: Record<string, string> = {}

    if (s === 1) {
      if (!formData.owner_name.trim()) {
        newErrors.owner_name = 'Vui lòng nhập họ và tên người đại diện.'
      }
      if (!formData.owner_id_number.trim()) {
        newErrors.owner_id_number = 'Vui lòng nhập số CCCD / CMND.'
      } else if (!/^[0-9]{12}$/.test(formData.owner_id_number.trim())) {
        newErrors.owner_id_number = 'Số CCCD phải gồm đúng 12 chữ số.'
      }
    } else if (s === 2) {
      if (!formData.business_name.trim()) {
        newErrors.business_name = 'Vui lòng nhập tên cơ sở kinh doanh / CLB.'
      }
      if (!formData.business_address.trim()) {
        newErrors.business_address = 'Vui lòng nhập địa chỉ kinh doanh của sân.'
      }
      if (!formData.business_phone.trim()) {
        newErrors.business_phone = 'Vui lòng nhập số điện thoại hotline.'
      } else if (!/^[0-9]{10,11}$/.test(formData.business_phone.trim())) {
        newErrors.business_phone = 'Số điện thoại hotline phải gồm 10 hoặc 11 chữ số.'
      }
    } else if (s === 3) {
      if (!formData.bank_name.trim()) {
        newErrors.bank_name = 'Vui lòng nhập tên ngân hàng nhận thanh toán.'
      }
      if (!formData.bank_account_number.trim()) {
        newErrors.bank_account_number = 'Vui lòng nhập số tài khoản ngân hàng.'
      } else if (!/^[0-9]{6,20}$/.test(formData.bank_account_number.trim())) {
        newErrors.bank_account_number = 'Số tài khoản ngân hàng phải gồm từ 6 đến 20 chữ số.'
      }
      if (!formData.bank_account_holder.trim()) {
        newErrors.bank_account_holder = 'Vui lòng nhập tên chủ tài khoản ngân hàng.'
      }
      if (!formData.business_license.trim()) {
        newErrors.business_license = 'Vui lòng nhập mã số giấy phép kinh doanh / MST.'
      }
      const hasLicenseDoc = data?.documents?.some((d) => d.type === 'business_license')
      if (!hasLicenseDoc && !files.business_license) {
        newErrors['documents.business_license'] = 'Vui lòng tải lên ảnh hoặc PDF Giấy phép kinh doanh.'
      }
    } else if (s === 4) {
      const hasIdFront = data?.documents?.some((d) => d.type === 'id_front')
      if (!hasIdFront && !files.id_front) {
        newErrors['documents.id_front'] = 'Vui lòng tải lên ảnh CCCD mặt trước.'
      }
      const hasIdBack = data?.documents?.some((d) => d.type === 'id_back')
      if (!hasIdBack && !files.id_back) {
        newErrors['documents.id_back'] = 'Vui lòng tải lên ảnh CCCD mặt sau.'
      }
      if (!formData.accept_terms) {
        newErrors.accept_terms = 'Bạn cần đồng ý với điều khoản dành cho chủ sân để gửi hồ sơ.'
      }
    }

    setErrors((prev) => ({ ...prev, ...newErrors }))
    return Object.keys(newErrors).length === 0
  }

  const handleNext = () => {
    if (validateStep(step)) {
      setStep((prev) => Math.min(prev + 1, 4))
    } else {
      error(`Vui lòng kiểm tra lại thông tin bị thiếu hoặc chưa đúng ở Bước ${step}.`, 'Chưa hoàn thành')
    }
  }

  const handleStepClick = (targetStep: number) => {
    if (targetStep < step) {
      setStep(targetStep)
      return
    }
    for (let i = 1; i < targetStep; i++) {
      if (!validateStep(i)) {
        setStep(i)
        error(`Vui lòng hoàn thiện Bước ${i} trước khi chuyển sang bước tiếp theo.`, 'Thông tin chưa đầy đủ')
        return
      }
    }
    setStep(targetStep)
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()

    // Validate ALL 4 steps sequentially
    for (let s = 1; s <= 4; s++) {
      if (!validateStep(s)) {
        setStep(s)
        error(`Bước ${s} có thông tin chưa hợp lệ. Vui lòng kiểm tra lại.`, 'Chưa hoàn thành hồ sơ')
        return
      }
    }

    // Build FormData
    const fd = new FormData()
    fd.append('owner_name', formData.owner_name.trim())
    fd.append('owner_id_number', formData.owner_id_number.trim())
    fd.append('business_name', formData.business_name.trim())
    fd.append('business_address', formData.business_address.trim())
    fd.append('business_phone', formData.business_phone.trim())
    if (formData.description.trim()) {
      fd.append('description', formData.description.trim())
    }
    fd.append('bank_name', formData.bank_name.trim())
    fd.append('bank_account_number', formData.bank_account_number.trim())
    fd.append('bank_account_holder', formData.bank_account_holder.trim().toUpperCase())
    fd.append('business_license', formData.business_license.trim())
    fd.append('accept_terms', '1')

    if (files.business_license) {
      fd.append('documents[business_license]', files.business_license)
    }
    if (files.land_right) {
      fd.append('documents[land_right]', files.land_right)
    }
    if (files.id_front) {
      fd.append('documents[id_front]', files.id_front)
    }
    if (files.id_back) {
      fd.append('documents[id_back]', files.id_back)
    }

    submit.mutate(fd)
  }

  // Name comparison helper
  const normalizeName = (s: string) =>
    s
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim()

  const isNameMismatch =
    formData.bank_account_holder.trim().length > 0 &&
    formData.owner_name.trim().length > 0 &&
    normalizeName(formData.bank_account_holder) !== normalizeName(formData.owner_name)

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
                  onClick={() => handleStepClick(s.id)}
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

          <form className="panel-form" noValidate onSubmit={onSubmit}>
            {/* Step 1: Owner Personal Info */}
            {step === 1 && (
              <div>
                <h2>1. Thông tin người đại diện</h2>
                <p className="form-help">Thông tin cá nhân đứng tên đăng ký và chịu trách nhiệm pháp lý.</p>
                <div className="form-grid">
                  <label>
                    <span>Họ và tên chủ sân</span>
                    <input
                      name="owner_name"
                      value={formData.owner_name}
                      onChange={handleChange}
                      placeholder="Nguyễn Văn A"
                      className={errors.owner_name ? 'input--invalid' : ''}
                    />
                    {errors.owner_name && <span className="input-error">{errors.owner_name}</span>}
                  </label>
                  <label>
                    <span>Số CCCD / CMND (12 chữ số)</span>
                    <input
                      name="owner_id_number"
                      value={formData.owner_id_number}
                      onChange={handleChange}
                      placeholder="12 chữ số trên thẻ CCCD"
                      maxLength={12}
                      inputMode="numeric"
                      className={errors.owner_id_number ? 'input--invalid' : ''}
                    />
                    {errors.owner_id_number && <span className="input-error">{errors.owner_id_number}</span>}
                  </label>
                </div>
                <div className="wizard-actions">
                  <div />
                  <button
                    type="button"
                    className="button button--brand"
                    onClick={handleNext}
                  >
                    Tiếp tục: Cơ sở <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* Step 2: Business Info */}
            {step === 2 && (
              <div>
                <h2>2. Cơ sở kinh doanh thể thao</h2>
                <p className="form-help">Thông tin sẽ hiển thị công khai trên website cho khách hàng liên hệ.</p>
                <label>
                  <span>Tên cơ sở kinh doanh / Câu lạc bộ</span>
                  <input
                    name="business_name"
                    value={formData.business_name}
                    onChange={handleChange}
                    placeholder="CLB Thể Thao Tây Vương"
                    className={errors.business_name ? 'input--invalid' : ''}
                  />
                  {errors.business_name && <span className="input-error">{errors.business_name}</span>}
                </label>
                <div className="form-grid">
                  <label>
                    <span>Địa chỉ kinh doanh</span>
                    <input
                      name="business_address"
                      value={formData.business_address}
                      onChange={handleChange}
                      placeholder="Số 123 Đường ABC, Quận XYZ, TP..."
                      className={errors.business_address ? 'input--invalid' : ''}
                    />
                    {errors.business_address && <span className="input-error">{errors.business_address}</span>}
                  </label>
                  <label>
                    <span>Số điện thoại hotline đặt sân</span>
                    <input
                      name="business_phone"
                      value={formData.business_phone}
                      onChange={handleChange}
                      placeholder="0988776655"
                      maxLength={11}
                      inputMode="tel"
                      className={errors.business_phone ? 'input--invalid' : ''}
                    />
                    {errors.business_phone && <span className="input-error">{errors.business_phone}</span>}
                  </label>
                </div>
                <label>
                  <span>Mô tả giới thiệu cơ sở (không bắt buộc)</span>
                  <textarea
                    name="description"
                    rows={3}
                    value={formData.description}
                    onChange={handleChange}
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
                    onClick={handleNext}
                  >
                    Tiếp tục: Tài khoản & GPKD <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* Step 3: Bank Account & Business License */}
            {step === 3 && (
              <div>
                <h2>3. Tài khoản ngân hàng & Pháp lý cơ sở</h2>
                <p className="form-help">Thông tin nhận tiền doanh thu đối soát và giấy phép kinh doanh để admin kiểm định.</p>

                {/* Bank Account Details */}
                <div style={{ marginBottom: 20 }}>
                  <h4 style={{ margin: '0 0 10px', fontSize: 13, color: 'var(--forest)' }}>
                    Tài khoản nhận doanh thu (bắt buộc)
                  </h4>
                  <div className="form-grid">
                    <label>
                      <span>Ngân hàng thụ hưởng</span>
                      <input
                        name="bank_name"
                        value={formData.bank_name}
                        onChange={handleChange}
                        placeholder="Vietcombank, MB Bank, Techcombank..."
                        className={errors.bank_name ? 'input--invalid' : ''}
                      />
                      {errors.bank_name && <span className="input-error">{errors.bank_name}</span>}
                    </label>
                    <label>
                      <span>Số tài khoản</span>
                      <input
                        name="bank_account_number"
                        value={formData.bank_account_number}
                        onChange={handleChange}
                        placeholder="Số tài khoản ngân hàng"
                        inputMode="numeric"
                        className={errors.bank_account_number ? 'input--invalid' : ''}
                      />
                      {errors.bank_account_number && (
                        <span className="input-error">{errors.bank_account_number}</span>
                      )}
                    </label>
                  </div>
                  <label>
                    <span>Tên chủ tài khoản (viết hoa không dấu hoặc có dấu)</span>
                    <input
                      name="bank_account_holder"
                      value={formData.bank_account_holder}
                      onChange={handleChange}
                      placeholder="NGUYEN VAN A"
                      className={errors.bank_account_holder ? 'input--invalid' : ''}
                    />
                    {errors.bank_account_holder && (
                      <span className="input-error">{errors.bank_account_holder}</span>
                    )}
                  </label>

                  {/* Name comparison warning */}
                  {isNameMismatch && (
                    <div className="notice notice--warn" style={{ marginTop: 8 }}>
                      <AlertTriangle size={15} style={{ flexShrink: 0 }} />
                      <span>
                        Lưu ý: Tên chủ tài khoản (<strong>{formData.bank_account_holder}</strong>) khác với họ tên
                        người đại diện (<strong>{formData.owner_name}</strong>). Ban quản trị có thể yêu cầu giải
                        trình nếu tài khoản thụ hưởng không chính chủ.
                      </span>
                    </div>
                  )}
                </div>

                {/* Business License */}
                <div style={{ marginTop: 16 }}>
                  <h4 style={{ margin: '0 0 10px', fontSize: 13, color: 'var(--forest)' }}>
                    Hồ sơ pháp lý kinh doanh
                  </h4>
                  <label>
                    <span>Mã số giấy phép kinh doanh / Đăng ký hộ kinh doanh</span>
                    <input
                      name="business_license"
                      value={formData.business_license}
                      onChange={handleChange}
                      placeholder="GPKD-123456 hoặc MST..."
                      className={errors.business_license ? 'input--invalid' : ''}
                    />
                    {errors.business_license && <span className="input-error">{errors.business_license}</span>}
                  </label>
                  <label>
                    <span>
                      Ảnh chụp hoặc PDF Giấy phép kinh doanh
                      {data?.documents?.some((d) => d.type === 'business_license')
                        ? ' (đã có tệp tải lên trước đó, chọn tệp mới nếu muốn thay)'
                        : ''}
                    </span>
                    <input
                      name="documents[business_license]"
                      type="file"
                      accept="image/*,.pdf"
                      onChange={(e) =>
                        handleFileChange('business_license', e.target.files?.[0] ?? null)
                      }
                      className={errors['documents.business_license'] ? 'input--invalid' : ''}
                    />
                    {errors['documents.business_license'] && (
                      <span className="input-error">{errors['documents.business_license']}</span>
                    )}
                  </label>
                  <label>
                    <span>Giấy tờ quyền sử dụng sân / Hợp đồng thuê mặt bằng (không bắt buộc)</span>
                    <input
                      name="documents[land_right]"
                      type="file"
                      accept="image/*,.pdf"
                      onChange={(e) => handleFileChange('land_right', e.target.files?.[0] ?? null)}
                    />
                  </label>
                </div>

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
                    onClick={handleNext}
                  >
                    Tiếp tục: Xác thực & Điều khoản <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* Step 4: ID Card Verification, Terms & Final Review */}
            {step === 4 && (
              <div>
                <h2>4. Xác minh Căn cước công dân & Cam kết</h2>
                <p className="form-help">Tải ảnh chụp CCCD bản gốc rõ nét và xác nhận điều khoản chủ sân.</p>

                {/* ID Cards Upload */}
                <div className="form-grid">
                  <label>
                    <span>
                      Mặt trước CCCD
                      {data?.documents?.some((d) => d.type === 'id_front') ? ' (đã có tệp trước đó)' : ''}
                    </span>
                    <input
                      name="documents[id_front]"
                      type="file"
                      accept="image/*,.pdf"
                      onChange={(e) => handleFileChange('id_front', e.target.files?.[0] ?? null)}
                      className={errors['documents.id_front'] ? 'input--invalid' : ''}
                    />
                    {errors['documents.id_front'] && (
                      <span className="input-error">{errors['documents.id_front']}</span>
                    )}
                  </label>
                  <label>
                    <span>
                      Mặt sau CCCD
                      {data?.documents?.some((d) => d.type === 'id_back') ? ' (đã có tệp trước đó)' : ''}
                    </span>
                    <input
                      name="documents[id_back]"
                      type="file"
                      accept="image/*,.pdf"
                      onChange={(e) => handleFileChange('id_back', e.target.files?.[0] ?? null)}
                      className={errors['documents.id_back'] ? 'input--invalid' : ''}
                    />
                    {errors['documents.id_back'] && (
                      <span className="input-error">{errors['documents.id_back']}</span>
                    )}
                  </label>
                </div>

                {/* Quick Summary Review */}
                <div className="wizard-summary">
                  <h4>Tóm tắt thông tin hồ sơ</h4>
                  <div className="wizard-summary-grid">
                    <div className="wizard-summary-item">
                      <span>Đại diện:</span>
                      <strong>{formData.owner_name || '—'} (CCCD: {formData.owner_id_number || '—'})</strong>
                    </div>
                    <div className="wizard-summary-item">
                      <span>Cơ sở kinh doanh:</span>
                      <strong>{formData.business_name || '—'} ({formData.business_phone || '—'})</strong>
                    </div>
                    <div className="wizard-summary-item">
                      <span>Địa chỉ cơ sở:</span>
                      <strong>{formData.business_address || '—'}</strong>
                    </div>
                    <div className="wizard-summary-item">
                      <span>Ngân hàng thụ hưởng:</span>
                      <strong>
                        {formData.bank_name || '—'} · {formData.bank_account_number || '—'} ({formData.bank_account_holder || '—'})
                      </strong>
                    </div>
                  </div>
                </div>

                {/* Terms Acceptance */}
                <div style={{ margin: '16px 0 10px' }}>
                  <label className="check-line" style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                    <input
                      type="checkbox"
                      name="accept_terms"
                      checked={formData.accept_terms}
                      onChange={handleChange}
                      style={{ marginTop: 3 }}
                    />
                    <span style={{ fontSize: 11, lineHeight: 1.5, color: 'var(--soft)' }}>
                      Tôi cam kết toàn bộ thông tin kê khai là chính xác, đồng ý với Quy chế hoạt động, Chính
                      sách huỷ/hoàn và xử lý tranh chấp của SportHub, và đồng ý để SportHub xử lý dữ liệu cá nhân
                      theo Nghị định 13/2023/NĐ-CP.
                    </span>
                  </label>
                  {errors.accept_terms && (
                    <span className="input-error" style={{ marginLeft: 26 }}>
                      {errors.accept_terms}
                    </span>
                  )}
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
                    <Send size={16} /> {submit.isPending ? 'Đang gửi hồ sơ...' : 'Xác nhận & Nộp hồ sơ xét duyệt'}
                  </button>
                </div>
              </div>
            )}
          </form>
        </div>
      )}
    </>
  )
}
