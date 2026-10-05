import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Building2,
  CalendarDays,
  ChartNoAxesCombined,
  CircleUserRound,
  Clock,
  Image as ImageIcon,
  MessageSquareText,
  Plus,
  QrCode,
  ShieldCheck,
  Trash2,
  Upload,
} from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { AnimatedCounter } from '../../components/AnimatedCounter'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import type {
  ApiEnvelope,
  Booking,
  FieldType,
  OwnerDashboard,
  OwnerProfile,
  Paginated,
  Review,
  SportsField,
  TimeSlot,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'

const currency = new Intl.NumberFormat('vi-VN')

export function OwnerDashboardPage() {
  const dashboard = useQuery({
    queryKey: ['owner', 'dashboard'],
    queryFn: async () => (await api.get<ApiEnvelope<OwnerDashboard>>('/field-owner/dashboard')).data.data,
  })
  const data = dashboard.data
  return (
    <>
      <PageTitle
        icon={<ChartNoAxesCombined />}
        kicker="Tổng quan"
        title="Nhịp vận hành hôm nay"
        copy="Theo dõi sân, lượt đặt và doanh thu trong một màn hình."
      />
      <div className="stats-grid">
        <Stat label="Tổng số sân" value={data?.total_fields ?? 0} />
        <Stat label="Tổng lượt đặt" value={data?.total_bookings ?? 0} />
        <Stat label="Doanh thu" value={`${currency.format(data?.total_revenue ?? 0)}đ`} />
        <Stat label="Đơn tháng này" value={data?.current_month_bookings ?? 0} />
      </div>
      <section className="data-panel">
        <div className="data-panel__head">
          <div>
            <span>Hoạt động gần đây</span>
            <h2>Yêu cầu đặt sân mới nhất</h2>
          </div>
        </div>
        <BookingTable bookings={data?.recent_bookings ?? []} />
      </section>
    </>
  )
}

export function OwnerFieldsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<SportsField | 'new' | null>(null)
  const [slotField, setSlotField] = useState<SportsField | null>(null)
  const [galleryField, setGalleryField] = useState<SportsField | null>(null)
  const [deletingField, setDeletingField] = useState<SportsField | null>(null)

  const fields = useQuery({
    queryKey: ['owner', 'fields', page],
    queryFn: async () => (await api.get<Paginated<SportsField>>(`/field-owner/fields?page=${page}`)).data,
  })
  const types = useQuery({
    queryKey: ['field-types'],
    queryFn: async () => (await api.get<{ data: FieldType[] }>('/field-types')).data.data,
  })

  // Synchronize modal state with latest query data
  const currentSlotField = fields.data?.data.find((f) => f.id === slotField?.id) ?? slotField
  const currentGalleryField = fields.data?.data.find((f) => f.id === galleryField?.id) ?? galleryField

  const save = useMutation({
    mutationFn: async ({ field, data }: { field: SportsField | 'new'; data: FormData }) =>
      field === 'new'
        ? api.post('/field-owner/fields', data)
        : api.post(`/field-owner/fields/${field.id}`, withMethodOverride(data, 'PUT')),
    onSuccess: (_, vars) => {
      toast.success(vars.field === 'new' ? 'Đã tạo sân và gửi kiểm duyệt.' : 'Đã cập nhật thông tin sân.')
      setEditing(null)
      void client.invalidateQueries({ queryKey: ['owner', 'fields'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu sân')
    },
  })

  const toggle = useMutation({
    mutationFn: async (field: SportsField) => api.patch(`/field-owner/fields/${field.id}/toggle`),
    onSuccess: (_, field) => {
      toast.success(`Đã ${field.is_active ? 'tạm ngưng' : 'mở hoạt động'} sân ${field.name}.`)
      void client.invalidateQueries({ queryKey: ['owner', 'fields'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi chuyển trạng thái')
    },
  })

  const deleteField = useMutation({
    mutationFn: async (field: SportsField) => api.delete(`/field-owner/fields/${field.id}`),
    onSuccess: (_, field) => {
      toast.success(`Đã xoá sân "${field.name}".`)
      setDeletingField(null)
      void client.invalidateQueries({ queryKey: ['owner', 'fields'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xoá sân')
    },
  })

  return (
    <>
      <PageTitle
        icon={<Building2 />}
        kicker="Nguồn cung"
        title="Sân của tôi"
        copy="Tạo, cập nhật và điều khiển trạng thái nhận lịch."
        action={
          <button className="button button--brand" onClick={() => setEditing('new')}>
            <Plus size={16} /> Thêm sân
          </button>
        }
      />
      <section className="data-panel">
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Sân</th>
                <th>Loại</th>
                <th>Giá/giờ</th>
                <th>Kiểm duyệt</th>
                <th>Hoạt động</th>
                <th>Quản lý</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {fields.data?.data.map((field) => (
                <tr key={field.id}>
                  <td>
                    <strong>{field.name}</strong>
                    <small>{field.address}</small>
                  </td>
                  <td>{field.field_type?.name}</td>
                  <td>{currency.format(field.price_per_hour)}đ</td>
                  <td>
                    <StatusBadge status={field.status} />
                  </td>
                  <td>
                    <button
                      className={`status ${field.is_active ? 'status--approved' : 'status--inactive'}`}
                      onClick={() => toggle.mutate(field)}
                      title="Nhấn để đổi trạng thái mở / đóng"
                    >
                      {field.is_active ? 'Đang mở' : 'Đã đóng'}
                    </button>
                  </td>
                  <td>
                    <div className="row-actions">
                      <button
                        className="button button--ghost button--small"
                        onClick={() => setSlotField(field)}
                        title="Quản lý các khung giờ nhận lịch"
                      >
                        <Clock size={14} /> Khung giờ ({field.time_slots?.length ?? 0})
                      </button>
                      <button
                        className="button button--ghost button--small"
                        onClick={() => setGalleryField(field)}
                        title="Quản lý bộ sưu tập ảnh"
                      >
                        <ImageIcon size={14} /> Ảnh ({field.images?.length ?? 0}/8)
                      </button>
                    </div>
                  </td>
                  <td>
                    <div className="row-actions">
                      <button className="button button--ghost" onClick={() => setEditing(field)}>
                        Sửa
                      </button>
                      <button
                        className="button button--danger-ghost button--icon"
                        onClick={() => setDeletingField(field)}
                        title="Xoá sân"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Pagination
          page={page}
          lastPage={fields.data?.meta.last_page ?? 1}
          total={fields.data?.meta.total}
          onPageChange={setPage}
        />
      </section>

      {!fields.isPending && !fields.data?.data.length && (
        <Empty text="Hãy tạo sân đầu tiên để bắt đầu nhận lịch." />
      )}

      <FieldModal
        field={editing}
        types={types.data ?? []}
        busy={save.isPending}
        error={save.isError ? errorMessage(save.error) : ''}
        onClose={() => setEditing(null)}
        onSubmit={(event) => {
          event.preventDefault()
          if (!editing) return
          save.mutate({ field: editing, data: new FormData(event.currentTarget) })
        }}
      />

      {currentSlotField && (
        <SlotManagerModal
          field={currentSlotField}
          onClose={() => setSlotField(null)}
          onRefresh={() => client.invalidateQueries({ queryKey: ['owner', 'fields'] })}
        />
      )}

      {currentGalleryField && (
        <ImageGalleryModal
          field={currentGalleryField}
          onClose={() => setGalleryField(null)}
          onRefresh={() => client.invalidateQueries({ queryKey: ['owner', 'fields'] })}
        />
      )}

      <ConfirmModal
        isOpen={!!deletingField}
        title="Xác nhận xoá sân"
        message={`Bạn có chắc muốn xoá sân "${deletingField?.name}"? Hệ thống sẽ xoá mềm sân này nếu không còn đơn đặt sắp tới.`}
        confirmText="Xoá sân"
        cancelText="Giữ lại"
        danger
        busy={deleteField.isPending}
        onClose={() => setDeletingField(null)}
        onConfirm={() => {
          if (deletingField) deleteField.mutate(deletingField)
        }}
      />
    </>
  )
}

function FieldModal({
  field,
  types,
  busy,
  error,
  onClose,
  onSubmit,
}: {
  field: SportsField | 'new' | null
  types: FieldType[]
  busy: boolean
  error: string
  onClose: () => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  const [prevField, setPrevField] = useState<SportsField | 'new' | null>(field)
  const [activeField, setActiveField] = useState<SportsField | 'new' | null>(field)
  if (field !== prevField) {
    setPrevField(field)
    if (field !== null) {
      setActiveField(field)
    }
  }
  const resolved = field ?? activeField
  const current = resolved === 'new' ? null : resolved
  return (
    <Modal isOpen={!!field} onClose={onClose} wide asForm onSubmit={onSubmit}>
      <h2>{current ? 'Chỉnh sửa sân' : 'Thêm sân mới'}</h2>
      <div className="form-grid">
        <label>
          <span>Tên sân</span>
          <input name="name" defaultValue={current?.name} required />
        </label>
        <label>
          <span>Loại sân</span>
          <select name="field_type_id" defaultValue={current?.field_type?.id} required>
            <option value="">Chọn loại sân</option>
            {types.map((type) => (
              <option value={type.id} key={type.id}>
                {type.category?.name} · {type.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>Địa chỉ</span>
          <input name="address" defaultValue={current?.address} required />
        </label>
        <label>
          <span>Giá cơ bản mỗi giờ (VNĐ)</span>
          <input name="price_per_hour" type="number" defaultValue={current?.price_per_hour} required min="1000" step="5000" />
        </label>
      </div>
      <label>
        <span>Mô tả sân</span>
        <textarea name="description" defaultValue={current?.description ?? ''} rows={3} />
      </label>
      {!current && (
        <label>
          <span>Hình ảnh ban đầu (tối đa 8 ảnh)</span>
          <input name="images[]" type="file" accept="image/*" multiple />
        </label>
      )}
      {error && <div className="notice notice--error">{error}</div>}
      <div className="modal__actions">
        <button className="button button--ghost" type="button" onClick={onClose}>
          Đóng
        </button>
        <button disabled={busy} className="button button--brand">
          {busy ? 'Đang lưu...' : 'Lưu sân'}
        </button>
      </div>
    </Modal>
  )
}

function SlotManagerModal({
  field,
  onClose,
  onRefresh,
}: {
  field: SportsField
  onClose: () => void
  onRefresh: () => void
}) {
  const toast = useToast()
  const [startTime, setStartTime] = useState('06:00')
  const [endTime, setEndTime] = useState('07:30')
  const [slotPrice, setSlotPrice] = useState('')
  const [deletingSlot, setDeletingSlot] = useState<TimeSlot | null>(null)

  const addSlot = useMutation({
    mutationFn: async (payload: { start_time: string; end_time: string; price_per_hour?: number | null }) =>
      api.post(`/field-owner/fields/${field.id}/slots`, payload),
    onSuccess: () => {
      toast.success('Đã thêm khung giờ thành công.')
      setSlotPrice('')
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể thêm khung giờ')
    },
  })

  const toggleSlot = useMutation({
    mutationFn: async (slot: TimeSlot) =>
      api.put(`/field-owner/fields/${field.id}/slots/${slot.id}`, {
        start_time: slot.start_time,
        end_time: slot.end_time,
        price_per_hour: slot.price_per_hour,
        is_active: !slot.is_active,
      }),
    onSuccess: () => {
      toast.success('Đã cập nhật trạng thái khung giờ.')
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi cập nhật khung giờ')
    },
  })

  const removeSlot = useMutation({
    mutationFn: async (slot: TimeSlot) =>
      api.delete(`/field-owner/fields/${field.id}/slots/${slot.id}`),
    onSuccess: (res) => {
      toast.success(res.data?.message ?? 'Đã xoá khung giờ.')
      setDeletingSlot(null)
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xoá khung giờ')
    },
  })

  const handleAddSubmit = (e: FormEvent) => {
    e.preventDefault()
    if (!startTime || !endTime) return
    addSlot.mutate({
      start_time: startTime,
      end_time: endTime,
      price_per_hour: slotPrice ? Number(slotPrice) : null,
    })
  }

  const slots = field.time_slots ?? []

  return (
    <>
      <Modal isOpen onClose={onClose} wide title={`Quản lý khung giờ · ${field.name}`}>
        <div className="slots-manager">
          <div className="slots-manager__head">
            <p className="form-help">
              Thiết lập các khung giờ nhận đặt sân và giá giờ cao điểm (nếu có). Giá mặc định của sân là{' '}
              <strong>{currency.format(field.price_per_hour)}đ/giờ</strong>.
            </p>
          </div>

          <form className="slot-add-form" onSubmit={handleAddSubmit}>
            <h4>Thêm khung giờ mới</h4>
            <div className="form-grid form-grid--3">
              <label>
                <span>Giờ bắt đầu</span>
                <input
                  type="time"
                  value={startTime}
                  onChange={(e) => setStartTime(e.target.value)}
                  required
                />
              </label>
              <label>
                <span>Giờ kết thúc</span>
                <input
                  type="time"
                  value={endTime}
                  onChange={(e) => setEndTime(e.target.value)}
                  required
                />
              </label>
              <label>
                <span>Giá riêng/giờ (để trống nếu theo sân)</span>
                <input
                  type="number"
                  placeholder={`${currency.format(field.price_per_hour)}đ`}
                  value={slotPrice}
                  onChange={(e) => setSlotPrice(e.target.value)}
                  min="1000"
                  step="5000"
                />
              </label>
            </div>
            <button
              type="submit"
              disabled={addSlot.isPending}
              className="button button--brand button--small"
            >
              <Plus size={15} /> {addSlot.isPending ? 'Đang thêm...' : 'Thêm khung giờ'}
            </button>
          </form>

          <div className="slots-list">
            <h4>Danh sách khung giờ ({slots.length})</h4>
            {slots.length === 0 ? (
              <p className="empty-inline">Chưa có khung giờ nào. Hãy thêm khung giờ ở trên.</p>
            ) : (
              <div className="slots-grid">
                {slots.map((slot) => (
                  <div key={slot.id} className={`slot-item ${!slot.is_active ? 'slot-item--inactive' : ''}`}>
                    <div className="slot-item__time">
                      <strong>
                        {slot.start_time} – {slot.end_time}
                      </strong>
                      <span className="slot-item__price">
                        {slot.price_per_hour != null
                          ? `${currency.format(slot.price_per_hour)}đ/h (riêng)`
                          : `${currency.format(field.price_per_hour)}đ/h`}
                      </span>
                    </div>
                    <div className="slot-item__actions">
                      <button
                        type="button"
                        className={`status ${slot.is_active ? 'status--approved' : 'status--inactive'}`}
                        onClick={() => toggleSlot.mutate(slot)}
                        title="Bấm để bật/tắt nhận khách khung giờ này"
                      >
                        {slot.is_active ? 'Hoạt động' : 'Tạm dừng'}
                      </button>
                      <button
                        type="button"
                        className="button button--ghost button--icon button--small"
                        onClick={() => setDeletingSlot(slot)}
                        title="Xoá khung giờ"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="modal__actions" style={{ marginTop: 24 }}>
          <button type="button" className="button button--brand" onClick={onClose}>
            Xong
          </button>
        </div>
      </Modal>

      <ConfirmModal
        isOpen={!!deletingSlot}
        title="Xác nhận xoá khung giờ"
        message={`Bạn có chắc muốn xoá khung giờ ${deletingSlot?.start_time} – ${deletingSlot?.end_time}? Nếu khung giờ đã từng có lịch sử đặt, hệ thống sẽ tự động chuyển sang ngưng hoạt động.`}
        confirmText="Xoá khung giờ"
        cancelText="Giữ lại"
        danger
        busy={removeSlot.isPending}
        onClose={() => setDeletingSlot(null)}
        onConfirm={() => {
          if (deletingSlot) removeSlot.mutate(deletingSlot)
        }}
      />
    </>
  )
}

function ImageGalleryModal({
  field,
  onClose,
  onRefresh,
}: {
  field: SportsField
  onClose: () => void
  onRefresh: () => void
}) {
  const toast = useToast()
  const [uploading, setUploading] = useState(false)
  const [deletingImage, setDeletingImage] = useState<{ id: number; url: string } | null>(null)

  const uploadImages = useMutation({
    mutationFn: async (formData: FormData) =>
      api.post(`/field-owner/fields/${field.id}/images`, formData),
    onSuccess: () => {
      toast.success('Đã tải ảnh lên thành công.')
      setUploading(false)
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi tải ảnh')
      setUploading(false)
    },
  })

  const setPrimary = useMutation({
    mutationFn: async (imageId: number) =>
      api.patch(`/field-owner/fields/${field.id}/images/${imageId}/primary`),
    onSuccess: () => {
      toast.success('Đã đặt làm ảnh đại diện sân.')
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể đổi ảnh đại diện')
    },
  })

  const deleteImage = useMutation({
    mutationFn: async (imageId: number) =>
      api.delete(`/field-owner/fields/${field.id}/images/${imageId}`),
    onSuccess: () => {
      toast.success('Đã xoá ảnh.')
      setDeletingImage(null)
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xoá ảnh')
    },
  })

  const handleUploadSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const form = e.currentTarget
    const formData = new FormData(form)
    const files = formData.getAll('images[]')
    if (!files.length || (files[0] as File).size === 0) {
      toast.error('Vui lòng chọn ít nhất 1 hình ảnh.')
      return
    }
    setUploading(true)
    uploadImages.mutate(formData)
    form.reset()
  }

  const images = field.images ?? []

  return (
    <>
      <Modal isOpen onClose={onClose} wide title={`Bộ sưu tập ảnh · ${field.name}`}>
        <div className="gallery-manager">
          <p className="form-help">
            Mỗi sân có thể đăng tối đa 8 hình ảnh. Chọn một ảnh làm ảnh đại diện hiển thị ở danh sách tìm kiếm.
          </p>

          {images.length < 8 && (
            <form className="gallery-upload-form" onSubmit={handleUploadSubmit}>
              <h4>Tải thêm hình ảnh ({images.length}/8)</h4>
              <div className="gallery-upload-row">
                <input
                  type="file"
                  name="images[]"
                  accept="image/*"
                  multiple
                  required
                  disabled={uploading}
                />
                <button
                  type="submit"
                  disabled={uploading || uploadImages.isPending}
                  className="button button--brand button--small"
                >
                  <Upload size={14} /> {uploading ? 'Đang tải lên...' : 'Tải lên'}
                </button>
              </div>
            </form>
          )}

          <div className="gallery-grid">
            {images.map((img) => (
              <div key={img.id} className="gallery-card">
                <img src={img.url} alt="" />
                {img.is_primary && <span className="gallery-badge">Ảnh đại diện</span>}
                <div className="gallery-card__actions">
                  {!img.is_primary && (
                    <button
                      type="button"
                      className="button button--ghost button--small"
                      onClick={() => setPrimary.mutate(img.id)}
                      disabled={setPrimary.isPending}
                    >
                      Đặt đại diện
                    </button>
                  )}
                  <button
                    type="button"
                    className="button button--danger-ghost button--small button--icon"
                    onClick={() => setDeletingImage(img)}
                    disabled={deleteImage.isPending}
                    title="Xoá ảnh này"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            ))}
          </div>

          {images.length === 0 && (
            <p className="empty-inline">Sân này chưa có hình ảnh nào. Hãy tải ảnh lên ở trên.</p>
          )}
        </div>

        <div className="modal__actions" style={{ marginTop: 24 }}>
          <button type="button" className="button button--brand" onClick={onClose}>
            Xong
          </button>
        </div>
      </Modal>

      <ConfirmModal
        isOpen={!!deletingImage}
        title="Xác nhận xoá ảnh"
        message="Bạn có chắc chắn muốn xoá ảnh này khỏi sân thể thao?"
        confirmText="Xoá ảnh"
        cancelText="Giữ lại"
        danger
        busy={deleteImage.isPending}
        onClose={() => setDeletingImage(null)}
        onConfirm={() => {
          if (deletingImage) deleteImage.mutate(deletingImage.id)
        }}
      />
    </>
  )
}

export function OwnerBookingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [rejectingBooking, setRejectingBooking] = useState<Booking | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [showCheckInModal, setShowCheckInModal] = useState(false)
  const [checkInCode, setCheckInCode] = useState('')

  const bookings = useQuery({
    queryKey: ['owner', 'bookings', status, page],
    queryFn: async () =>
      (
        await api.get<Paginated<Booking>>(
          `/field-owner/bookings?page=${page}${status ? `&status=${status}` : ''}`,
        )
      ).data,
  })

  const update = useMutation({
    mutationFn: async ({
      booking,
      status: next,
      reason,
    }: {
      booking: Booking
      status: string
      reason?: string
    }) =>
      api.patch(`/field-owner/bookings/${booking.id}/status`, {
        status: next,
        ...(reason ? { reason } : {}),
      }),
    onSuccess: (_, vars) => {
      const label =
        vars.status === 'confirmed'
          ? 'xác nhận'
          : vars.status === 'completed'
            ? 'hoàn thành'
            : 'từ chối'
      toast.success(`Đã ${label} đơn đặt sân #BK-${vars.booking.id}.`)
      setRejectingBooking(null)
      setRejectReason('')
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể cập nhật đơn')
    },
  })

  const checkIn = useMutation({
    mutationFn: async (code: string) =>
      (await api.post<{ message: string; data: Booking }>('/field-owner/check-in', { code })).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Check-in thành công! Đã cộng +10 điểm uy tín cho khách hàng.')
      setShowCheckInModal(false)
      setCheckInCode('')
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Check-in không thành công')
    },
  })

  const handleFilterChange = (newStatus: string) => {
    setStatus(newStatus)
    setPage(1)
  }

  return (
    <>
      <PageTitle
        icon={<CalendarDays />}
        kicker="Vận hành"
        title="Đơn đặt sân & Check-in QR"
        copy="Xác nhận yêu cầu, quét mã QR check-in tích điểm uy tín và hoàn tất lượt chơi."
        action={
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <Link to="/owner/schedule" className="button button--ghost">
              <CalendarDays size={16} /> Xem lịch trực quan
            </Link>
            <button
              type="button"
              className="button button--brand"
              onClick={() => setShowCheckInModal(true)}
            >
              <QrCode size={16} /> Quét / Check-in QR
            </button>
          </div>
        }
      />
      <div className="toolbar">
        <select value={status} onChange={(event) => handleFilterChange(event.target.value)}>
          <option value="">Mọi trạng thái</option>
          <option value="pending">Chờ xử lý</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="completed">Hoàn thành</option>
          <option value="cancelled">Đã hủy</option>
          <option value="rejected">Từ chối</option>
        </select>
      </div>
      <section className="data-panel">
        <QueryState
          isPending={bookings.isPending}
          isError={bookings.isError}
          isEmpty={!bookings.data?.data.length}
          error={errorMessage(bookings.error)}
          empty={<Empty text="Chưa có đơn đặt sân phù hợp." />}
        >
          <BookingTable
            bookings={bookings.data?.data ?? []}
            actions={(booking) => (
              <>
                {booking.status === 'pending' && (
                  <>
                    <button
                      className="button button--brand button--small"
                      onClick={() => update.mutate({ booking, status: 'confirmed' })}
                      disabled={update.isPending}
                    >
                      Xác nhận
                    </button>
                    <button
                      className="button button--danger button--small"
                      onClick={() => setRejectingBooking(booking)}
                      disabled={update.isPending}
                    >
                      Từ chối
                    </button>
                  </>
                )}
                {booking.status === 'confirmed' && (
                  <>
                    {!booking.checked_in_at && (
                      <button
                        className="button button--ghost button--small"
                        onClick={() => checkIn.mutate(booking.check_in_code ?? '')}
                        disabled={checkIn.isPending}
                        title="Xác nhận khách đã tới sân (tích +10 điểm uy tín)"
                      >
                        <QrCode size={13} /> Check-in
                      </button>
                    )}
                    <button
                      className="button button--dark button--small"
                      onClick={() => update.mutate({ booking, status: 'completed' })}
                      disabled={update.isPending}
                    >
                      Hoàn thành
                    </button>
                  </>
                )}
              </>
            )}
          />
        </QueryState>
        <Pagination
          page={page}
          lastPage={bookings.data?.meta.last_page ?? 1}
          total={bookings.data?.meta.total}
          onPageChange={setPage}
        />
      </section>

      {/* Reject Modal */}
      <Modal
        isOpen={!!rejectingBooking}
        onClose={() => setRejectingBooking(null)}
        title="Từ chối đơn đặt sân"
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (rejectingBooking) {
            update.mutate({
              booking: rejectingBooking,
              status: 'rejected',
              reason: rejectReason || 'Chủ sân bận khung giờ này.',
            })
          }
        }}
      >
        <p>
          Bạn có chắc chắn muốn từ chối đơn <strong>#BK-{rejectingBooking?.id}</strong> của khách hàng{' '}
          <strong>{rejectingBooking?.customer?.name}</strong>?
        </p>
        <label style={{ marginTop: 12 }}>
          <span>Lý do từ chối (thông báo tới khách)</span>
          <textarea
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder="Ví dụ: Sân đang bảo trì mặt cỏ / trùng lịch nội bộ..."
            required
            rows={3}
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => setRejectingBooking(null)}
          >
            Đóng
          </button>
          <button type="submit" disabled={update.isPending} className="button button--danger">
            {update.isPending ? 'Đang xử lý...' : 'Xác nhận từ chối'}
          </button>
        </div>
      </Modal>

      {/* Owner QR Check-in Modal */}
      <Modal
        isOpen={showCheckInModal}
        onClose={() => setShowCheckInModal(false)}
        title="Xác thực Check-in Khách Hàng"
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (checkInCode.trim()) {
            checkIn.mutate(checkInCode.trim())
          }
        }}
      >
        <p>
          Nhập mã Check-in được hiển thị trên thẻ vào sân của khách hàng (hoặc mã quét từ QR).
          Hệ thống sẽ chuyển trạng thái đơn sang đã check-in và tự động <strong>cộng +10 điểm uy tín</strong> cho khách.
        </p>
        <label style={{ marginTop: 14 }}>
          <span>Mã Check-in (VD: CK-A1B2C3D4)</span>
          <input
            type="text"
            value={checkInCode}
            onChange={(e) => setCheckInCode(e.target.value.toUpperCase())}
            placeholder="CK-XXXXXXXX"
            style={{ fontFamily: 'monospace', fontSize: '1.1rem', letterSpacing: '0.08em' }}
            required
            autoFocus
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => setShowCheckInModal(false)}
          >
            Hủy
          </button>
          <button type="submit" disabled={checkIn.isPending} className="button button--brand">
            {checkIn.isPending ? 'Đang xác thực...' : 'Xác nhận Check-in'}
          </button>
        </div>
      </Modal>
    </>
  )
}

export function OwnerReviewsPage() {
  const [page, setPage] = useState(1)
  const reviews = useQuery({
    queryKey: ['owner', 'reviews', page],
    queryFn: async () =>
      (await api.get<Paginated<Review>>(`/field-owner/reviews?page=${page}`)).data,
  })

  return (
    <>
      <PageTitle
        icon={<MessageSquareText />}
        kicker="Phản hồi"
        title="Khách hàng nói gì?"
        copy="Lắng nghe trải nghiệm thật để cải thiện chất lượng sân."
      />
      <QueryState
        isPending={reviews.isPending}
        isError={reviews.isError}
        isEmpty={!reviews.data?.data.length}
        error={errorMessage(reviews.error)}
        empty={<Empty text="Chưa có đánh giá nào từ khách hàng." />}
      >
        <div className="review-grid">
          {reviews.data?.data.map((review) => (
          <article className="review-card" key={review.id}>
            <div>
              <div className="avatar">{review.user?.name.slice(0, 1)}</div>
              <span>
                <strong>{review.user?.name}</strong>
                <small>{new Date(review.created_at).toLocaleDateString('vi-VN')}</small>
              </span>
              <b>{'★'.repeat(review.rating)}</b>
            </div>
            <p>“{review.comment}”</p>
            <small>
              Đánh giá cho <strong>{review.field?.name}</strong>
            </small>
          </article>
          ))}
        </div>
      </QueryState>
      <Pagination
        page={page}
        lastPage={reviews.data?.meta.last_page ?? 1}
        total={reviews.data?.meta.total}
        onPageChange={setPage}
      />
    </>
  )
}

export function OwnerProfilePage() {
  const client = useQueryClient()
  const toast = useToast()
  const profile = useQuery({
    queryKey: ['owner', 'profile'],
    queryFn: async () => (await api.get<ApiEnvelope<OwnerProfile>>('/field-owner/profile')).data.data,
  })
  const update = useMutation({
    mutationFn: async (payload: Record<string, string>) => api.put('/field-owner/profile', payload),
    onSuccess: () => {
      toast.success('Đã lưu hồ sơ cơ sở thành công.')
      void client.invalidateQueries({ queryKey: ['owner', 'profile'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu hồ sơ')
    },
  })

  return (
    <>
      <PageTitle
        icon={<CircleUserRound />}
        kicker="Đối tác"
        title="Hồ sơ cơ sở"
        copy="Thông tin dùng trong quá trình kiểm duyệt và liên hệ với khách hàng."
      />
      <form
        key={profile.data?.id}
        className="panel-form panel-form--wide"
        onSubmit={(event) => {
          event.preventDefault()
          update.mutate(
            Object.fromEntries(new FormData(event.currentTarget).entries()) as Record<string, string>,
          )
        }}
      >
        <div className="profile-status">
          <span>Trạng thái kiểm duyệt</span>
          <StatusBadge status={profile.data?.verification_status ?? 'pending'} />
        </div>
        <div className="form-grid">
          <label>
            <span>Tên cơ sở</span>
            <input name="business_name" defaultValue={profile.data?.business_name} required />
          </label>
          <label>
            <span>SĐT kinh doanh</span>
            <input name="business_phone" defaultValue={profile.data?.business_phone} required />
          </label>
          <label>
            <span>Địa chỉ kinh doanh</span>
            <input name="business_address" defaultValue={profile.data?.business_address} required />
          </label>
          <label>
            <span>Giấy phép</span>
            <input
              name="business_license"
              defaultValue={profile.data?.business_license ?? ''}
              disabled
              title="Muốn đổi giấy phép, hãy liên hệ admin"
            />
          </label>
        </div>
        <label>
          <span>Giới thiệu cơ sở</span>
          <textarea name="description" defaultValue={profile.data?.description ?? ''} rows={4} />
        </label>
        {update.isError && <div className="notice notice--error">{errorMessage(update.error)}</div>}
        <button disabled={update.isPending} className="button button--brand">
          {update.isPending ? 'Đang lưu...' : 'Lưu hồ sơ'}
        </button>
      </form>
    </>
  )
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <article className="stat">
      <span>{label}</span>
      <strong>
        <AnimatedCounter value={value} />
      </strong>
    </article>
  )
}

function BookingTable({
  bookings,
  actions,
}: {
  bookings: Booking[]
  actions?: (booking: Booking) => React.ReactNode
}) {
  if (bookings.length === 0) {
    return <p className="empty-inline" style={{ padding: 24, textAlign: 'center' }}>Chưa có yêu cầu đặt sân nào.</p>
  }

  return (
    <>
      {/* Desktop Table View */}
      <div className="table-wrap hide-on-mobile">
        <table>
          <thead>
            <tr>
              <th>Đơn & sân</th>
              <th>Khách hàng</th>
              <th>Lịch chơi</th>
              <th>Giá trị</th>
              <th>Trạng thái</th>
              {actions && <th />}
            </tr>
          </thead>
          <tbody>
            {bookings.map((booking) => (
              <tr key={booking.id}>
                <td>
                  <small>#BK-{booking.id}</small>
                  <strong>{booking.field?.name}</strong>
                </td>
                <td>{booking.customer?.name ?? '—'}</td>
                <td>
                  {new Date(booking.booking_date).toLocaleDateString('vi-VN')}
                  <small>
                    {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
                  </small>
                </td>
                <td>{currency.format(booking.total_price)}đ</td>
                <td>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'flex-start' }}>
                    <StatusBadge status={booking.status} />
                    {booking.checked_in_at ? (
                      <span className="badge badge--success" style={{ fontSize: '0.72rem', padding: '2px 6px' }} title={`Check-in: ${new Date(booking.checked_in_at).toLocaleTimeString('vi-VN')}`}>
                        ✓ Đã Check-in
                      </span>
                    ) : booking.status === 'confirmed' ? (
                      <span className="badge badge--soft" style={{ fontSize: '0.72rem', padding: '2px 6px' }}>
                        Chưa check-in
                      </span>
                    ) : null}
                  </div>
                </td>
                {actions && (
                  <td>
                    <div className="row-actions">{actions(booking)}</div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Mobile Card List View */}
      <div className="mobile-booking-cards hide-on-desktop">
        {bookings.map((booking) => (
          <article key={booking.id} className="mobile-booking-card">
            <div className="mobile-booking-card__head">
              <div>
                <small className="mobile-booking-card__id">#BK-{String(booking.id).padStart(5, '0')}</small>
                <h4>{booking.field?.name}</h4>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }}>
                <StatusBadge status={booking.status} />
                {booking.checked_in_at && (
                  <span className="badge badge--success" style={{ fontSize: '0.7rem', padding: '2px 6px' }}>
                    ✓ Đã Check-in
                  </span>
                )}
              </div>
            </div>

            <div className="mobile-booking-card__grid">
              <div className="mobile-booking-card__field">
                <span>Khách hàng</span>
                <strong>{booking.customer?.name ?? '—'}</strong>
              </div>
              <div className="mobile-booking-card__field">
                <span>Tổng tiền</span>
                <strong className="mobile-booking-card__price">{currency.format(booking.total_price)}đ</strong>
              </div>
            </div>

            <div className="mobile-booking-card__time">
              <CalendarDays size={13} />
              <span>
                {new Date(booking.booking_date).toLocaleDateString('vi-VN')} ({booking.time_slot?.start_time} – {booking.time_slot?.end_time})
              </span>
            </div>

            {actions && (
              <div className="mobile-booking-card__actions">
                {actions(booking)}
              </div>
            )}
          </article>
        ))}
      </div>
    </>
  )
}
