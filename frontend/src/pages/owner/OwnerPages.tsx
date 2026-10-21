import { digitsOnly, toBankHolder } from '../../utils/input'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertTriangle,
  Building2,
  CalendarDays,
  CalendarX,
  ChartNoAxesCombined,
  CircleUserRound,
  Clock,
  CornerDownRight,
  Image as ImageIcon,
  MessageSquareReply,
  MessageSquareText,
  Plus,
  Repeat,
  UserCheck,
  Trash2,
  Upload,
} from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { AnimatedCounter } from '../../components/AnimatedCounter'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { StatusBadge } from '../../components/StatusBadge'
import { OwnerPaymentActions } from '../../components/OwnerPaymentActions'
import { useToast } from '../../components/Toast'
import type {
  ApiEnvelope,
  Booking,
  FieldClosure,
  FieldType,
  LocationGroup,
  OwnerDashboard,
  OwnerProfile,
  Paginated,
  Review,
  SportsField,
  TimeSlot,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { Avatar } from '../../components/Avatar'
import { VIETNAMESE_BANKS } from '../../utils/banks'
import { getLocalTodayString } from '../../utils/date'
import { formatDate, formatMoney } from '../../utils/format'

export function OwnerDashboardPage() {

  const dashboard = useQuery({
    queryKey: ['owner', 'dashboard'],
    queryFn: async () => (await api.get<ApiEnvelope<OwnerDashboard>>('/field-owner/dashboard')).data.data,
  })

  if (dashboard.isPending) {
    return (
      <>
        <PageTitle
          icon={<ChartNoAxesCombined />}
          kicker="Tổng quan"
          title="Nhịp vận hành hôm nay"
          copy="Theo dõi sân, lượt đặt và doanh thu trong một màn hình."
        />
        <QueryState type="loading" message="Đang tải dữ liệu tổng quan..." />
      </>
    )
  }

  if (dashboard.isError) {
    return (
      <>
        <PageTitle
          icon={<ChartNoAxesCombined />}
          kicker="Tổng quan"
          title="Nhịp vận hành hôm nay"
          copy="Theo dõi sân, lượt đặt và doanh thu trong một màn hình."
        />
        <QueryState
          type="error"
          message={errorMessage(dashboard.error)}
          onRetry={() => void dashboard.refetch()}
        />
      </>
    )
  }

  const data = dashboard.data
  return (
    <>
      <PageTitle
        icon={<ChartNoAxesCombined />}
        kicker="Tổng quan"
        title="Nhịp vận hành hôm nay"
        copy="Theo dõi sân, lượt đặt và doanh thu trong một màn hình."
      />

      {data?.has_overdue_debt && (
        <div className="alert-banner alert-banner--danger" style={{ marginBottom: 20 }}>
          <AlertTriangle size={20} />
          <div>
            <strong>Tài khoản đang có kỳ đối soát nợ phí quá hạn!</strong>
            <p>
              Bạn đang bị tạm dừng duyệt đơn đặt sân mới. Vui lòng thanh toán phí đối soát để tiếp tục nhận và duyệt đơn.{' '}
              <Link to="/owner/statements" style={{ textDecoration: 'underline', fontWeight: 'bold' }}>
                Đến trang đối soát &rarr;
              </Link>
            </p>
          </div>
        </div>
      )}

      <div className="stats-grid">
        <Stat label="Tổng số sân" value={data?.total_fields ?? 0} />
        <Stat
          label="Đơn chờ xác nhận"
          value={data?.pending_bookings ?? 0}
          note={data?.pending_bookings ? 'Xử lý ngay' : `${data?.total_bookings ?? 0} lượt đặt tổng cộng`}
          to={data?.pending_bookings ? '/owner/bookings?status=pending' : undefined}
        />
        <Stat
          label="Doanh thu thực nhận"
          value={formatMoney(data?.current_month_net_revenue ?? 0)}
          note={`GMV: ${formatMoney(data?.current_month_gmv ?? 0)}`}
        />
        <Stat
          label="Phí sàn ước tính"
          value={formatMoney(data?.current_month_commission ?? 0)}
          note="Xem chi tiết đối soát"
          to="/owner/statements"
        />
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
  const [closureField, setClosureField] = useState<SportsField | null>(null)
  const [deletingField, setDeletingField] = useState<SportsField | null>(null)
  const [togglingField, setTogglingField] = useState<SportsField | null>(null)

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
  const currentClosureField = fields.data?.data.find((f) => f.id === closureField?.id) ?? closureField

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
        {fields.isPending && (
          <QueryState type="loading" message="Đang tải danh sách sân của bạn..." />
        )}
        {fields.isError && (
          <QueryState
            type="error"
            message={errorMessage(fields.error)}
            onRetry={() => void fields.refetch()}
          />
        )}
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
                  <td>{formatMoney(field.price_per_hour)}</td>
                  <td>
                    <StatusBadge status={field.status} />
                    {field.rejection_reason && field.status !== 'approved' && (
                      <small title="Lý do từ phía quản trị viên">Lý do: {field.rejection_reason}</small>
                    )}
                  </td>
                  <td>
                    <button
                      type="button"
                      role="switch"
                      aria-checked={field.is_active}
                      className="switch"
                      onClick={() => setTogglingField(field)}
                      disabled={toggle.isPending}
                      title="Bật/tắt nhận lịch đặt sân"
                    >
                      <span className="switch__track"><span className="switch__knob" /></span>
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
                      <button
                        className="button button--ghost button--small"
                        onClick={() => setClosureField(field)}
                        title="Quản lý ngày nghỉ và bảo trì sân"
                      >
                        <CalendarX size={14} /> Lịch nghỉ ({field.closures?.length ?? 0})
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

      {currentClosureField && (
        <FieldClosureModal
          field={currentClosureField}
          onClose={() => setClosureField(null)}
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

      <ConfirmModal
        isOpen={!!togglingField}
        title={togglingField?.is_active ? `Tạm ngưng nhận lịch sân "${togglingField?.name}"` : `Mở nhận lịch sân "${togglingField?.name}"`}
        message={
          togglingField?.is_active
            ? 'Khách hàng sẽ không thể tìm thấy hoặc đặt lịch mới tại sân này khi đang tạm ngưng.'
            : 'Khung giờ trống của sân này sẽ sẵn sàng để khách hàng tìm kiếm và đặt lịch.'
        }
        confirmText={togglingField?.is_active ? 'Tạm ngưng' : 'Mở nhận lịch'}
        cancelText="Giữ nguyên"
        danger={togglingField?.is_active}
        busy={toggle.isPending}
        onClose={() => setTogglingField(null)}
        onConfirm={() => {
          if (togglingField) {
            toggle.mutate(togglingField)
            setTogglingField(null)
          }
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
  const locations = useQuery({
    queryKey: ['locations'],
    queryFn: async () => (await api.get<{ data: LocationGroup[] }>('/fields/locations')).data.data,
    enabled: !!field,
  })
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
          <span>Thành phố (tuỳ chọn)</span>
          <input name="city" list="location-cities" maxLength={100} placeholder="VD: Hà Nội" defaultValue={current?.city ?? ''} />
          <datalist id="location-cities">
            {locations.data?.map((l) => <option value={l.city} key={l.city} />)}
          </datalist>
        </label>
        <label>
          <span>Quận / Huyện (tuỳ chọn)</span>
          <input name="district" list="location-districts" maxLength={100} placeholder="VD: Thanh Xuân" defaultValue={current?.district ?? ''} />
          <datalist id="location-districts">
            {[...new Set(locations.data?.flatMap((l) => l.districts))].map((d) => <option value={d} key={d} />)}
          </datalist>
        </label>
        <label>
          <span>Vĩ độ (tuỳ chọn)</span>
          <input name="latitude" type="number" step="any" min="-90" max="90" placeholder="VD: 21.0285" defaultValue={current?.latitude ?? ''} />
        </label>
        <label>
          <span>Kinh độ (tuỳ chọn)</span>
          <input name="longitude" type="number" step="any" min="-180" max="180" placeholder="VD: 105.8542" defaultValue={current?.longitude ?? ''} />
        </label>
        <label>
          <span>Giá cơ bản mỗi giờ (VNĐ)</span>
          <input name="price_per_hour" type="number" defaultValue={current?.price_per_hour} required min="1000" max="100000000" step="1000" />
        </label>
      </div>
      <label>
        <span>Mô tả sân</span>
        <textarea name="description" defaultValue={current?.description ?? ''} rows={3} />
      </label>
      {!current && (
        <label>
          <span>Hình ảnh ban đầu (tối đa 8 ảnh)</span>
          <input name="images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple />
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

/** 'HH:MM' + số phút, chặn ở 23:59. */
function shiftTime(time: string, minutes: number): string {
  const [h, m] = time.split(':').map(Number)
  const total = Math.min(23 * 60 + 59, h * 60 + m + minutes)
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
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
  // Mặc định nối tiếp khung giờ cuối cùng để thêm liên tục không phải gõ lại giờ.
  const lastEnd = (field.time_slots ?? []).map((s) => s.end_time).sort().at(-1)
  const [startTime, setStartTime] = useState(lastEnd && lastEnd < '22:30' ? lastEnd : '06:00')
  const [endTime, setEndTime] = useState(shiftTime(lastEnd && lastEnd < '22:30' ? lastEnd : '06:00', 90))
  const [slotPrice, setSlotPrice] = useState('')
  const [deletingSlot, setDeletingSlot] = useState<TimeSlot | null>(null)
  const [togglingSlot, setTogglingSlot] = useState<TimeSlot | null>(null)

  const addSlot = useMutation({
    mutationFn: async (payload: { start_time: string; end_time: string; price_per_hour?: number | null }) =>
      api.post(`/field-owner/fields/${field.id}/slots`, payload),
    onSuccess: () => {
      toast.success('Đã thêm khung giờ thành công.')
      setSlotPrice('')
      const [sh, sm] = startTime.split(':').map(Number)
      const [eh, em] = endTime.split(':').map(Number)
      setStartTime(endTime)
      setEndTime(shiftTime(endTime, eh * 60 + em - (sh * 60 + sm)))
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
              <strong>{formatMoney(field.price_per_hour)}/giờ</strong>.
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
                  min="1000"
                  max="100000000"
                  step="1000"
                  placeholder={formatMoney(field.price_per_hour)}
                  value={slotPrice}
                  onChange={(e) => setSlotPrice(e.target.value)}
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
                          ? `${formatMoney(slot.price_per_hour)}/h (riêng)`
                          : `${formatMoney(field.price_per_hour)}/h`}
                      </span>
                    </div>
                    <div className="slot-item__actions">
                      <button
                        type="button"
                        className={`status ${slot.is_active ? 'status--approved' : 'status--inactive'}`}
                        onClick={() => setTogglingSlot(slot)}
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
        isOpen={!!togglingSlot}
        title={togglingSlot?.is_active ? 'Tạm dừng khung giờ' : 'Mở hoạt động khung giờ'}
        message={
          togglingSlot?.is_active
            ? `Bạn có chắc muốn tạm dừng khung giờ ${togglingSlot.start_time} – ${togglingSlot.end_time}? Khách hàng sẽ không thể đặt lịch vào khung giờ này trong những ngày tới.`
            : `Mở hoạt động khung giờ ${togglingSlot?.start_time} – ${togglingSlot?.end_time}? Khung giờ này sẽ sẵn sàng để khách tìm kiếm và đặt lịch.`
        }
        confirmText={togglingSlot?.is_active ? 'Tạm dừng' : 'Mở hoạt động'}
        cancelText="Giữ nguyên"
        danger={togglingSlot?.is_active}
        busy={toggleSlot.isPending}
        onClose={() => setTogglingSlot(null)}
        onConfirm={() => {
          if (togglingSlot) {
            toggleSlot.mutate(togglingSlot)
            setTogglingSlot(null)
          }
        }}
      />

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
                  accept=".jpg,.jpeg,.png,.webp"
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

function FieldClosureModal({
  field,
  onClose,
  onRefresh,
}: {
  field: SportsField
  onClose: () => void
  onRefresh: () => void
}) {
  const toast = useToast()
  const today = getLocalTodayString()
  const [startDate, setStartDate] = useState(today)
  const [endDate, setEndDate] = useState(today)
  const [reason, setReason] = useState('')
  const [deletingClosure, setDeletingClosure] = useState<FieldClosure | null>(null)

  const closuresQuery = useQuery({
    queryKey: ['owner', 'fields', field.id, 'closures'],
    queryFn: async () =>
      (await api.get<ApiEnvelope<FieldClosure[]>>(`/field-owner/fields/${field.id}/closures`)).data.data,
  })

  const addClosure = useMutation({
    mutationFn: async (payload: { start_date: string; end_date: string; reason: string }) =>
      api.post(`/field-owner/fields/${field.id}/closures`, payload),
    onSuccess: () => {
      toast.success('Đã thêm lịch nghỉ / bảo trì đóng sân.')
      setReason('')
      void closuresQuery.refetch()
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể tạo lịch đóng sân')
    },
  })

  const removeClosure = useMutation({
    mutationFn: async (closureId: number) =>
      api.delete(`/field-owner/fields/${field.id}/closures/${closureId}`),
    onSuccess: () => {
      toast.success('Đã xoá lịch nghỉ. Sân đã mở lại hoạt động.')
      setDeletingClosure(null)
      void closuresQuery.refetch()
      onRefresh()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xoá lịch nghỉ')
    },
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    if (!startDate || !endDate || !reason.trim()) return
    addClosure.mutate({
      start_date: startDate,
      end_date: endDate,
      reason: reason.trim(),
    })
  }

  const closures = closuresQuery.data ?? field.closures ?? []

  return (
    <>
      <Modal
        isOpen
        onClose={onClose}
        title={`Lịch nghỉ & bảo trì: ${field.name}`}
        ariaLabel="Quản lý lịch nghỉ sân"
      >
        <div style={{ maxWidth: 640 }}>
          <p className="form-help" style={{ marginBottom: 16 }}>
            Thiết lập các ngày sân tạm dừng hoạt động do bảo dưỡng, sửa chữa hoặc nghỉ lễ.
            Khách hàng sẽ không thể đặt lịch vào các ngày này. Nếu có đơn đang chờ hoặc đã xác nhận, hệ thống sẽ yêu cầu bạn xử lý trước khi đóng sân.
          </p>

          <form onSubmit={handleSubmit} style={{ background: 'var(--surface-sunken, #f8fafc)', padding: 16, borderRadius: 12, border: '1px solid var(--border)', marginBottom: 20 }}>
            <h4 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 700 }}>Thêm khoảng thời gian nghỉ / đóng sân</h4>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
              <label style={{ margin: 0 }}>
                <span style={{ fontSize: 12 }}>Từ ngày</span>
                <input
                  type="date"
                  required
                  min={today}
                  value={startDate}
                  onChange={(e) => {
                    setStartDate(e.target.value)
                    if (e.target.value > endDate) setEndDate(e.target.value)
                  }}
                />
              </label>
              <label style={{ margin: 0 }}>
                <span style={{ fontSize: 12 }}>Đến ngày</span>
                <input
                  type="date"
                  required
                  min={startDate || today}
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                />
              </label>
            </div>
            <label style={{ margin: '0 0 12px' }}>
              <span style={{ fontSize: 12 }}>Lý do nghỉ / đóng sân</span>
              <input
                type="text"
                required
                maxLength={255}
                placeholder="Ví dụ: Thay cỏ nhân tạo định kỳ, Nghỉ lễ Quốc khánh 2/9, Bảo dưỡng hệ thống đèn..."
                value={reason}
                onChange={(e) => setReason(e.target.value)}
              />
            </label>
            <button
              type="submit"
              className="button button--brand button--small"
              disabled={addClosure.isPending || !reason.trim()}
            >
              <Plus size={14} /> {addClosure.isPending ? 'Đang lưu...' : 'Thêm lịch nghỉ'}
            </button>
          </form>

          <h4 style={{ margin: '0 0 10px', fontSize: 14, fontWeight: 700 }}>
            Danh sách lịch nghỉ ({closures.length})
          </h4>

          {closuresQuery.isPending && (
            <div className="query-state query-state--loading">Đang tải lịch nghỉ...</div>
          )}

          {!closuresQuery.isPending && closures.length === 0 && (
            <p className="empty-inline" style={{ padding: '16px 0', textAlign: 'center', color: 'var(--muted)' }}>
              Chưa có lịch nghỉ hoặc bảo dưỡng nào được tạo. Sân đang mở nhận lịch bình thường.
            </p>
          )}

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, maxHeight: 280, overflowY: 'auto' }}>
            {closures.map((c) => {
              const isOngoing = c.start_date <= today && c.end_date >= today
              const isUpcoming = c.start_date > today
              return (
                <div
                  key={c.id}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '10px 14px',
                    borderRadius: 8,
                    border: '1px solid var(--border)',
                    background: isOngoing ? 'rgba(239, 68, 68, 0.05)' : 'var(--surface)',
                  }}
                >
                  <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <strong>
                        {formatDate(c.start_date)} {c.start_date !== c.end_date ? `→ ${formatDate(c.end_date)}` : ''}
                      </strong>
                      {isOngoing && <span className="badge badge--danger" style={{ fontSize: '0.7rem', padding: '1px 6px' }}>Đang đóng sân</span>}
                      {isUpcoming && <span className="badge badge--warning" style={{ fontSize: '0.7rem', padding: '1px 6px' }}>Sắp tới</span>}
                    </div>
                    <div style={{ fontSize: 13, color: 'var(--muted)', marginTop: 2 }}>{c.reason}</div>
                  </div>
                  <button
                    type="button"
                    className="button button--danger-ghost button--icon"
                    title="Xoá lịch nghỉ này"
                    onClick={() => setDeletingClosure(c)}
                    disabled={removeClosure.isPending}
                  >
                    <Trash2 size={15} />
                  </button>
                </div>
              )
            })}
          </div>

          <div className="modal__actions" style={{ marginTop: 20 }}>
            <button type="button" className="button button--ghost" onClick={onClose}>
              Đóng
            </button>
          </div>
        </div>
      </Modal>

      <ConfirmModal
        isOpen={!!deletingClosure}
        onClose={() => setDeletingClosure(null)}
        onConfirm={() => {
          if (deletingClosure) removeClosure.mutate(deletingClosure.id)
        }}
        title="Xoá lịch đóng sân"
        message={`Bạn có chắc muốn xoá lịch nghỉ từ ${deletingClosure ? formatDate(deletingClosure.start_date) : ''} đến ${deletingClosure ? formatDate(deletingClosure.end_date) : ''}? Sân sẽ mở lại khung giờ cho khách đặt bình thường.`}
        confirmText="Mở lại sân"
        cancelText="Giữ lại"
        danger={false}
        busy={removeClosure.isPending}
      />
    </>
  )
}

export function OwnerBookingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [params] = useSearchParams()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const [date, setDate] = useState('')
  const [page, setPage] = useState(1)
  const [rejectingBooking, setRejectingBooking] = useState<Booking | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectScope, setRejectScope] = useState<'single' | 'recurring'>('single')

  const bookings = useQuery({
    queryKey: ['owner', 'bookings', status, date, page],
    queryFn: async () =>
      (
        await api.get<Paginated<Booking>>('/field-owner/bookings', {
          // Giá trị "pay:*" trong ô lọc là bước thanh toán của đơn đang chờ, không phải trạng thái đơn.
          params: {
            page,
            status: status.startsWith('pay:') ? 'pending' : status || undefined,
            payment_status: status.startsWith('pay:') ? status.slice(4) : undefined,
            date: date || undefined,
          },
        })
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
            : vars.status === 'cancelled'
              ? 'huỷ'
              : 'từ chối'
      toast.success(`Đã ${label} đơn đặt sân #BK-${vars.booking.id}.`)
      setRejectingBooking(null)
      setRejectReason('')
      setRejectScope('single')
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể cập nhật đơn')
    },
  })

  const rejectRecurring = useMutation({
    mutationFn: async ({ groupId, reason }: { groupId: string; reason?: string }) =>
      api.patch(`/field-owner/bookings/recurring/${groupId}/reject`, { reason }),
    onSuccess: () => {
      toast.success('Đã từ chối toàn bộ chuỗi đặt sân cố định.')
      setRejectingBooking(null)
      setRejectReason('')
      setRejectScope('single')
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể từ chối chuỗi đơn')
    },
  })

  const [showCodeModal, setShowCodeModal] = useState(false)
  const [checkInInputCode, setCheckInInputCode] = useState('')
  const [noShowingBooking, setNoShowingBooking] = useState<Booking | null>(null)

  const noShow = useMutation({
    mutationFn: async (booking: Booking) =>
      (await api.patch<{ message: string; data: Booking }>(`/field-owner/bookings/${booking.id}/no-show`)).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Đã ghi nhận khách vắng mặt và trừ điểm uy tín.')
      setNoShowingBooking(null)
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể ghi nhận vắng mặt')
    },
  })

  const checkIn = useMutation({
    mutationFn: async (booking: Booking) =>
      (await api.patch<{ message: string; data: Booking }>(`/field-owner/bookings/${booking.id}/check-in`)).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Đã xác nhận khách đến sân (+10 điểm uy tín cho khách).')
      void client.invalidateQueries({ queryKey: ['owner', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['owner', 'dashboard'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể xác nhận khách đến')
    },
  })

  const checkInByCode = useMutation({
    mutationFn: async (code: string) =>
      (await api.post<{ message: string; data: Booking }>('/field-owner/check-in', { code })).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Check-in thành công (+10 điểm uy tín cho khách).')
      setShowCodeModal(false)
      setCheckInInputCode('')
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
        title="Đơn đặt sân"
        copy="Duyệt yêu cầu, đối chiếu tiền chuyển khoản theo nội dung, xác nhận khách đến sân và hoàn tất lượt chơi."
        action={
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button
              type="button"
              className="button button--brand"
              onClick={() => setShowCodeModal(true)}
            >
              <UserCheck size={16} /> Nhập mã check-in
            </button>
            <Link to="/owner/schedule" className="button button--ghost">
              <CalendarDays size={16} /> Xem lịch trực quan
            </Link>
          </div>
        }
      />
      <div className="toolbar">
        <select value={status} onChange={(event) => handleFilterChange(event.target.value)}>
          <option value="">Mọi trạng thái</option>
          <option value="pending">Chờ xử lý (tất cả)</option>
          <option value="pay:awaiting">Chờ khách thanh toán</option>
          <option value="pay:reported">Chờ xác nhận tiền</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="completed">Hoàn thành</option>
          <option value="cancelled">Đã hủy</option>
          <option value="rejected">Từ chối</option>
        </select>
        <input
          type="date"
          value={date}
          aria-label="Lọc theo ngày chơi"
          title="Lọc theo ngày chơi"
          onChange={(event) => {
            setDate(event.target.value)
            setPage(1)
          }}
        />
        {(status || date) && (
          <button
            type="button"
            className="button button--ghost button--small"
            onClick={() => {
              setStatus('')
              setDate('')
              setPage(1)
            }}
          >
            Xóa lọc
          </button>
        )}
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
                  <OwnerPaymentActions booking={booking} onReject={() => setRejectingBooking(booking)} />
                )}
                {booking.status === 'confirmed' && (
                  <>
                    {!booking.checked_in_at && (
                      <button
                        className="button button--ghost button--small"
                        onClick={() => checkIn.mutate(booking)}
                        disabled={checkIn.isPending}
                        title="Xác nhận khách đã tới sân (tích +10 điểm uy tín)"
                      >
                        <UserCheck size={13} /> Khách đã đến
                      </button>
                    )}
                    {(() => {
                      const matchEnded = (() => {
                        if (!booking.booking_date || !booking.time_slot?.end_time) return false
                        const matchEndTime = new Date(`${booking.booking_date}T${booking.time_slot.end_time}`).getTime()
                        return !isNaN(matchEndTime) && matchEndTime <= Date.now()
                      })()

                      const matchStarted = (() => {
                        if (!booking.booking_date || !booking.time_slot?.start_time) return false
                        const matchStartTime = new Date(`${booking.booking_date}T${booking.time_slot.start_time}`).getTime()
                        return !isNaN(matchStartTime) && matchStartTime <= Date.now()
                      })()

                      if (booking.checked_in_at && matchEnded) {
                        return (
                          <button
                            className="button button--dark button--small"
                            onClick={() => update.mutate({ booking, status: 'completed' })}
                            disabled={update.isPending}
                          >
                            Hoàn thành
                          </button>
                        )
                      }

                      if (!booking.checked_in_at && matchStarted) {
                        return (
                          <button
                            className="button button--ghost button--small"
                            style={{ color: '#b45309', borderColor: '#fde68a' }}
                            onClick={() => setNoShowingBooking(booking)}
                            disabled={noShow.isPending}
                            title="Ghi nhận khách không tới sân theo lịch"
                          >
                            Vắng mặt
                          </button>
                        )
                      }

                      return null
                    })()}
                    <button
                      className="button button--danger button--small"
                      onClick={() => setRejectingBooking(booking)}
                      disabled={update.isPending}
                    >
                      Huỷ đơn
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
        onClose={() => {
          setRejectingBooking(null)
          setRejectReason('')
          setRejectScope('single')
        }}
        title={rejectingBooking?.status === 'confirmed' ? 'Huỷ đơn đã xác nhận' : 'Từ chối đơn đặt sân'}
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (!rejectingBooking) return
          if (rejectScope === 'recurring' && rejectingBooking.recurring_group_id) {
            rejectRecurring.mutate({
              groupId: rejectingBooking.recurring_group_id,
              reason: rejectReason || 'Chủ sân không thể tiếp tục nhận chuỗi đặt cố định.',
            })
          } else {
            update.mutate({
              booking: rejectingBooking,
              status: rejectingBooking.status === 'confirmed' ? 'cancelled' : 'rejected',
              reason: rejectReason || 'Chủ sân bận khung giờ này.',
            })
          }
        }}
      >
        <p>
          Bạn có chắc chắn muốn {rejectingBooking?.status === 'confirmed' ? 'huỷ' : 'từ chối'} đơn <strong>#BK-{rejectingBooking?.id}</strong> của khách hàng{' '}
          <strong>{rejectingBooking?.customer?.name}</strong>?
        </p>

        {rejectingBooking?.recurring_group_id && (
          <div style={{ padding: '10px 14px', background: 'var(--surface-sunken)', borderRadius: 8, border: '1px solid var(--border)', margin: '12px 0' }}>
            <span style={{ fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8, color: 'var(--forest)' }}>
              <Repeat size={14} /> Đơn thuộc chuỗi đặt cố định hằng tuần:
            </span>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', margin: 0 }}>
                <input
                  type="radio"
                  name="reject_scope"
                  value="single"
                  checked={rejectScope === 'single'}
                  onChange={() => setRejectScope('single')}
                />
                <span>Chỉ {rejectingBooking.status === 'confirmed' ? 'huỷ' : 'từ chối'} buổi này (#BK-{rejectingBooking.id})</span>
              </label>
              <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', margin: 0 }}>
                <input
                  type="radio"
                  name="reject_scope"
                  value="recurring"
                  checked={rejectScope === 'recurring'}
                  onChange={() => setRejectScope('recurring')}
                />
                <span>{rejectingBooking.status === 'confirmed' ? 'Huỷ' : 'Từ chối'} toàn bộ các buổi trong chuỗi cố định</span>
              </label>
            </div>
          </div>
        )}

        <label style={{ marginTop: 12 }}>
          <span>Lý do {rejectingBooking?.status === 'confirmed' ? 'huỷ' : 'từ chối'} (thông báo tới khách)</span>
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
            onClick={() => {
              setRejectingBooking(null)
              setRejectReason('')
              setRejectScope('single')
            }}
          >
            Đóng
          </button>
          <button
            type="submit"
            disabled={update.isPending || rejectRecurring.isPending}
            className="button button--danger"
          >
            {update.isPending || rejectRecurring.isPending
              ? 'Đang xử lý...'
              : rejectScope === 'recurring'
                ? 'Từ chối toàn bộ chuỗi'
                : rejectingBooking?.status === 'confirmed'
                  ? 'Xác nhận huỷ đơn'
                  : 'Xác nhận từ chối'}
          </button>
        </div>
      </Modal>

      {/* Check In By Code Modal */}
      <Modal
        isOpen={showCodeModal}
        onClose={() => {
          setShowCodeModal(false)
          setCheckInInputCode('')
        }}
        title="Check-in bằng mã đơn"
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (checkInInputCode.trim()) {
            checkInByCode.mutate(checkInInputCode.trim())
          }
        }}
      >
        <p style={{ margin: '0 0 14px', fontSize: 13, color: 'var(--soft)' }}>
          Nhập mã <strong>CK-XXXXXXXX</strong> do khách cung cấp khi tới sân để xác nhận có mặt và tích +10 điểm uy tín cho khách.
        </p>
        <label>
          <span>Mã check-in</span>
          <input
            type="text"
            value={checkInInputCode}
            onChange={(e) => setCheckInInputCode(e.target.value.toUpperCase())}
            placeholder="Ví dụ: CK-A1B2C3D4"
            required
            autoFocus
            style={{ letterSpacing: '0.08em', fontWeight: 600, fontSize: 16 }}
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => {
              setShowCodeModal(false)
              setCheckInInputCode('')
            }}
          >
            Hủy bỏ
          </button>
          <button
            type="submit"
            disabled={checkInByCode.isPending || !checkInInputCode.trim()}
            className="button button--brand"
          >
            {checkInByCode.isPending ? 'Đang xác nhận...' : 'Xác nhận khách đến'}
          </button>
        </div>
      </Modal>

      <ConfirmModal
        isOpen={!!noShowingBooking}
        title="Ghi nhận khách vắng mặt (No-show)"
        message={`Bạn có chắc chắn khách hàng ${noShowingBooking?.customer?.name ?? ''} không đến sân cho đơn #BK-${noShowingBooking?.id}? Hệ thống sẽ trừ 25 điểm uy tín của khách và hoàn tất đơn.`}
        confirmText="Xác nhận vắng mặt"
        cancelText="Đóng"
        danger
        busy={noShow.isPending}
        onClose={() => setNoShowingBooking(null)}
        onConfirm={() => {
          if (noShowingBooking) {
            noShow.mutate(noShowingBooking)
          }
        }}
      />
    </>
  )
}

export function OwnerReviewsPage() {
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [replyingReview, setReplyingReview] = useState<Review | null>(null)

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
        copy="Lắng nghe trải nghiệm thật và phản hồi trực tiếp các ý kiến của khách hàng để xây dựng uy tín cho sân."
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
            <article className="review-card" key={review.id} style={{ display: 'flex', flexDirection: 'column' }}>
              <div>
                <Avatar name={review.user?.name} src={review.user?.avatar} />
                <span>
                  <strong>{review.user?.name}</strong>
                  <small>{formatDate(review.created_at)}</small>
                </span>
                <b>{'★'.repeat(review.rating)}</b>
              </div>
              <p>“{review.comment}”</p>
              <small>
                Đánh giá cho <strong>{review.field?.name}</strong>
              </small>

              {review.owner_reply && (
                <div style={{ marginTop: 12, padding: '10px 12px', background: 'var(--surface-sunken, #f8fafc)', borderRadius: 8, borderLeft: '3px solid var(--pitch, #0b3025)', fontSize: 13 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                    <strong style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--forest, #0b3025)' }}>
                      <CornerDownRight size={13} /> Phản hồi của bạn:
                    </strong>
                    {review.owner_replied_at && (
                      <small style={{ color: 'var(--muted)', fontSize: 11 }}>{formatDate(review.owner_replied_at)}</small>
                    )}
                  </div>
                  <p style={{ margin: 0, fontStyle: 'normal' }}>{review.owner_reply}</p>
                </div>
              )}

              <div style={{ marginTop: 'auto', paddingTop: 10, display: 'flex', justifyContent: 'flex-end' }}>
                <button
                  type="button"
                  className="button button--ghost button--small"
                  onClick={() => setReplyingReview(review)}
                >
                  <MessageSquareReply size={13} /> {review.owner_reply ? 'Sửa phản hồi' : 'Trả lời đánh giá'}
                </button>
              </div>
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

      {replyingReview && (
        <OwnerReviewReplyModal
          review={replyingReview}
          onClose={() => setReplyingReview(null)}
          onSuccess={() => void client.invalidateQueries({ queryKey: ['owner', 'reviews'] })}
        />
      )}
    </>
  )
}

function OwnerReviewReplyModal({
  review,
  onClose,
  onSuccess,
}: {
  review: Review
  onClose: () => void
  onSuccess: () => void
}) {
  const toast = useToast()
  const [replyText, setReplyText] = useState(review.owner_reply ?? '')

  const replyMutation = useMutation({
    mutationFn: async (reply: string) =>
      api.patch(`/field-owner/reviews/${review.id}/reply`, { reply }),
    onSuccess: () => {
      toast.success('Đã gửi phản hồi đánh giá.')
      onSuccess()
      onClose()
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu phản hồi')
    },
  })

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Phản hồi đánh giá của khách hàng"
      asForm
      onSubmit={(e) => {
        e.preventDefault()
        if (!replyText.trim()) return
        replyMutation.mutate(replyText.trim())
      }}
    >
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div style={{ background: 'var(--surface-sunken)', padding: 12, borderRadius: 8, fontSize: 13 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
            <strong>{review.user?.name}</strong>
            <span style={{ color: '#f59e0b' }}>{'★'.repeat(review.rating)}</span>
          </div>
          <p style={{ margin: 0, fontStyle: 'italic', color: 'var(--text)' }}>“{review.comment}”</p>
          <small style={{ color: 'var(--muted)', marginTop: 4, display: 'block' }}>
            Sân: {review.field?.name}
          </small>
        </div>

        <label style={{ margin: 0 }}>
          <span>Nội dung phản hồi từ chủ sân</span>
          <textarea
            required
            rows={4}
            maxLength={1000}
            value={replyText}
            onChange={(e) => setReplyText(e.target.value)}
            placeholder="Cảm ơn quý khách đã ghé sân và để lại góp ý. Chúng tôi sẽ cải thiện..."
          />
          <small style={{ color: 'var(--muted)', textAlign: 'right', display: 'block', marginTop: 4 }}>
            {replyText.length}/1000 ký tự
          </small>
        </label>

        <div className="modal__actions">
          <button type="button" className="button button--ghost" onClick={onClose}>
            Hủy
          </button>
          <button
            type="submit"
            className="button button--brand"
            disabled={replyMutation.isPending || !replyText.trim()}
          >
            {replyMutation.isPending ? 'Đang gửi...' : 'Lưu phản hồi'}
          </button>
        </div>
      </div>
    </Modal>
  )
}

export function OwnerProfilePage() {
  const client = useQueryClient()
  const toast = useToast()
  const profile = useQuery({
    queryKey: ['owner', 'profile'],
    queryFn: async () => (await api.get<ApiEnvelope<OwnerProfile>>('/field-owner/profile')).data.data,
  })
  const [selectedBank, setSelectedBank] = useState<string | null>(null)
  const [selectedBin, setSelectedBin] = useState<string | null>(null)

  const currentBank = selectedBank ?? profile.data?.bank_name ?? ''
  const currentBin = selectedBin ?? profile.data?.bank_bin ?? ''

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

  if (profile.isPending) {
    return (
      <>
        <PageTitle
          icon={<CircleUserRound />}
          kicker="Đối tác"
          title="Hồ sơ cơ sở"
          copy="Thông tin dùng trong quá trình kiểm duyệt và liên hệ với khách hàng."
        />
        <QueryState type="loading" message="Đang tải thông tin hồ sơ cơ sở..." />
      </>
    )
  }

  if (profile.isError) {
    return (
      <>
        <PageTitle
          icon={<CircleUserRound />}
          kicker="Đối tác"
          title="Hồ sơ cơ sở"
          copy="Thông tin dùng trong quá trình kiểm duyệt và liên hệ với khách hàng."
        />
        <QueryState
          type="error"
          message={errorMessage(profile.error)}
          onRetry={() => void profile.refetch()}
        />
      </>
    )
  }

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
            <input name="business_phone" type="tel" inputMode="numeric" maxLength={11} pattern="0[0-9]{9,10}" title="Số điện thoại gồm 10–11 chữ số, bắt đầu bằng 0" onInput={(e) => (e.currentTarget.value = digitsOnly(e.currentTarget.value, 11))} defaultValue={profile.data?.business_phone} required />
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
        <h2 style={{ marginTop: 18 }}>Tài khoản nhận tiền</h2>
        <p className="form-help">
          Khách chuyển khoản cọc hoặc trả hết vào tài khoản này sau khi bạn duyệt đơn. Chưa khai thì chưa duyệt được đơn.
        </p>
        <div className="form-grid">
          <label>
            <span>Ngân hàng thụ hưởng</span>
            <select
              name="bank_name"
              value={currentBank}
              onChange={(e) => {
                const val = e.target.value
                setSelectedBank(val)
                const found = VIETNAMESE_BANKS.find((b) => b.short_name === val)
                setSelectedBin(found?.bin ?? '')
              }}
            >
              <option value="">-- Chọn ngân hàng thụ hưởng --</option>
              {VIETNAMESE_BANKS.map((b) => (
                <option key={b.bin} value={b.short_name}>
                  {b.short_name} – {b.name}
                </option>
              ))}
            </select>
            <input type="hidden" name="bank_bin" value={currentBin} />
          </label>
          <label>
            <span>Số tài khoản</span>
            <input
              name="bank_account_number"
              defaultValue={profile.data?.bank_account_number ?? ''}
              inputMode="numeric"
              required
              maxLength={20}
              pattern="[0-9]{6,20}"
              title="Số tài khoản gồm 6–20 chữ số"
              onInput={(e) => (e.currentTarget.value = digitsOnly(e.currentTarget.value, 20))}
            />
          </label>
          <label>
            <span>Chủ tài khoản</span>
            <input name="bank_account_holder" required maxLength={50} onInput={(e) => (e.currentTarget.value = toBankHolder(e.currentTarget.value))} defaultValue={profile.data?.bank_account_holder ?? ''} placeholder="NGUYEN VAN A" />
          </label>
        </div>
        {update.isError && <div className="notice notice--error">{errorMessage(update.error)}</div>}
        <button disabled={update.isPending} className="button button--brand">
          {update.isPending ? 'Đang lưu...' : 'Lưu hồ sơ'}
        </button>
      </form>
    </>
  )
}

function Stat({ label, value, note, to }: { label: string; value: string | number; note?: string; to?: string }) {
  return (
    <article className="stat">
      <span>{label}</span>
      <strong>
        <AnimatedCounter value={value} />
      </strong>
      {note && (to ? <Link className="stat__link" to={to}>{note} →</Link> : <small>{note}</small>)}
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
                <td>
                  {booking.customer?.name ?? '—'}
                  {booking.customer?.phone && (
                    <small>
                      <a href={`tel:${booking.customer.phone}`}>{booking.customer.phone}</a>
                    </small>
                  )}
                  {booking.notes && <small title={booking.notes}>Ghi chú: {booking.notes}</small>}
                </td>
                <td>
                  {formatDate(booking.booking_date)}
                  <small>
                    {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
                  </small>
                </td>
                <td>{formatMoney(booking.total_price)}</td>
                <td>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'flex-start' }}>
                    <StatusBadge status={booking.status} payment={booking.payment?.status} />
                    {booking.recurring_group_id && (
                      <span className="badge badge--info" style={{ fontSize: '0.72rem', padding: '2px 6px', display: 'inline-flex', alignItems: 'center', gap: 3 }} title="Chuỗi đặt cố định hàng tuần">
                        <Repeat size={10} /> Đặt cố định
                      </span>
                    )}
                    {booking.checked_in_at ? (
                      <span className="badge badge--success" style={{ fontSize: '0.72rem', padding: '2px 6px' }} title={`Check-in: ${new Date(booking.checked_in_at).toLocaleTimeString('vi-VN')}`}>
                        ✓ Đã đến sân
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
                <StatusBadge status={booking.status} payment={booking.payment?.status} />
                {booking.recurring_group_id && (
                  <span className="badge badge--info" style={{ fontSize: '0.7rem', padding: '2px 6px', display: 'inline-flex', alignItems: 'center', gap: 3 }}>
                    <Repeat size={10} /> Đặt cố định
                  </span>
                )}
                {booking.checked_in_at && (
                  <span className="badge badge--success" style={{ fontSize: '0.7rem', padding: '2px 6px' }}>
                    ✓ Đã đến sân
                  </span>
                )}
              </div>
            </div>

            <div className="mobile-booking-card__grid">
              <div className="mobile-booking-card__field">
                <span>Khách hàng</span>
                <strong>{booking.customer?.name ?? '—'}</strong>
                {booking.customer?.phone && <a href={`tel:${booking.customer.phone}`}>{booking.customer.phone}</a>}
              </div>
              <div className="mobile-booking-card__field">
                <span>Tổng tiền</span>
                <strong className="mobile-booking-card__price">{formatMoney(booking.total_price)}</strong>
              </div>
            </div>

            <div className="mobile-booking-card__time">
              <CalendarDays size={13} />
              <span>
                {formatDate(booking.booking_date)} ({booking.time_slot?.start_time} – {booking.time_slot?.end_time})
              </span>
            </div>

            {booking.notes && <p className="form-help">Ghi chú: {booking.notes}</p>}

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
