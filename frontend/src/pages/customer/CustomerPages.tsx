import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, CircleUserRound, Clock3, MapPin, Star } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, withMethodOverride } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import { useAuth } from '../../context/auth-context'
import type { ApiEnvelope, Booking, Paginated, User } from '../../types/api'

const currency = new Intl.NumberFormat('vi-VN')

export function CustomerBookingsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [cancellingBooking, setCancellingBooking] = useState<Booking | null>(null)
  const [reviewing, setReviewing] = useState<Booking | null>(null)
  const [activeReviewing, setActiveReviewing] = useState<Booking | null>(null)
  const currentReview = reviewing ?? activeReviewing

  const openReview = (booking: Booking) => {
    setReviewing(booking)
    setActiveReviewing(booking)
  }

  const bookings = useQuery({
    queryKey: ['customer', 'bookings', page],
    queryFn: async () => (await api.get<Paginated<Booking>>(`/customer/bookings?page=${page}`)).data,
  })

  const cancel = useMutation({
    mutationFn: async (booking: Booking) => api.patch(`/customer/bookings/${booking.id}/cancel`),
    onSuccess: (_, booking) => {
      toast.success(`Đã hủy lịch đặt sân #BK-${booking.id}.`)
      setCancellingBooking(null)
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể hủy lịch')
    },
  })

  const review = useMutation({
    mutationFn: async (payload: { booking_id: number; rating: number; comment: string }) =>
      api.post('/customer/reviews', payload),
    onSuccess: () => {
      toast.success('Cảm ơn bạn đã gửi đánh giá!')
      setReviewing(null)
      void client.invalidateQueries({ queryKey: ['customer', 'bookings'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể gửi đánh giá')
    },
  })

  const submitReview = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!reviewing) return
    const data = new FormData(event.currentTarget)
    review.mutate({
      booking_id: reviewing.id,
      rating: Number(data.get('rating')),
      comment: String(data.get('comment')),
    })
  }

  return (
    <>
      <PageTitle
        icon={<CalendarDays />}
        kicker="Lịch chơi"
        title="Các lượt đặt của tôi"
        copy="Theo dõi xác nhận, hủy lịch và chia sẻ đánh giá sau trận."
      />
      <div className="booking-list">
        {bookings.data?.data.map((booking) => (
          <article
            className="booking-item"
            key={booking.id}
          >
            <div className="booking-item__image">
              {booking.field?.primary_image && <img src={booking.field.primary_image} alt="" />}
            </div>
            <div className="booking-item__body">
              <div className="booking-item__top">
                <div>
                  <small>#BK-{String(booking.id).padStart(5, '0')}</small>
                  <h3>{booking.field?.name}</h3>
                </div>
                <StatusBadge status={booking.status} />
              </div>
              <div className="booking-meta">
                <span>
                  <CalendarDays />
                  {new Date(booking.booking_date).toLocaleDateString('vi-VN')}
                </span>
                <span>
                  <Clock3 />
                  {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
                </span>
                <span>
                  <MapPin />
                  {booking.field?.address}
                </span>
              </div>
              <div className="booking-item__footer">
                <strong>{currency.format(booking.total_price)}đ</strong>
                <div>
                  {booking.can_be_cancelled && (
                    <button
                      className="button button--danger button--small"
                      onClick={() => setCancellingBooking(booking)}
                    >
                      Hủy lịch
                    </button>
                  )}
                  {booking.status === 'completed' && !booking.review && (
                    <button
                      className="button button--brand button--small"
                      onClick={() => openReview(booking)}
                    >
                      <Star size={15} /> Đánh giá
                    </button>
                  )}
                </div>
              </div>
            </div>
          </article>
        ))}
      </div>
      {!bookings.isPending && !bookings.data?.data.length && (
        <Empty text="Bạn chưa có lịch đặt sân nào." />
      )}
      <Pagination
        page={page}
        lastPage={bookings.data?.meta.last_page ?? 1}
        total={bookings.data?.meta.total}
        onPageChange={setPage}
      />

      <Modal isOpen={!!reviewing} onClose={() => setReviewing(null)} asForm onSubmit={submitReview}>
        <h2>Đánh giá {currentReview?.field?.name}</h2>
        <label>
          <span>Số sao</span>
          <select name="rating" defaultValue="5">
            {[5, 4, 3, 2, 1].map((n) => (
              <option value={n} key={n}>
                {n} sao
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>Nội dung</span>
          <textarea name="comment" required rows={3} placeholder="Chia sẻ cảm nhận về chất lượng sân, dịch vụ..." />
        </label>
        <div className="modal__actions">
          <button type="button" className="button button--ghost" onClick={() => setReviewing(null)}>
            Đóng
          </button>
          <button disabled={review.isPending} className="button button--brand">
            {review.isPending ? 'Đang gửi...' : 'Gửi đánh giá'}
          </button>
        </div>
      </Modal>

      <ConfirmModal
        isOpen={!!cancellingBooking}
        title="Xác nhận hủy đặt sân"
        message={`Bạn có chắc muốn hủy lịch đặt sân "${cancellingBooking?.field?.name}" ngày ${cancellingBooking?.booking_date ? new Date(cancellingBooking.booking_date).toLocaleDateString('vi-VN') : ''} (${cancellingBooking?.time_slot?.start_time} - ${cancellingBooking?.time_slot?.end_time})?`}
        confirmText="Hủy lịch đặt"
        cancelText="Giữ lại"
        danger
        busy={cancel.isPending}
        onClose={() => setCancellingBooking(null)}
        onConfirm={() => {
          if (cancellingBooking) cancel.mutate(cancellingBooking)
        }}
      />
    </>
  )
}

export function CustomerProfilePage() {
  const { user } = useAuth()
  const client = useQueryClient()
  const toast = useToast()
  const profile = useQuery({
    queryKey: ['customer', 'profile'],
    queryFn: async () => (await api.get<ApiEnvelope<User>>('/customer/profile')).data.data,
  })
  const update = useMutation({
    mutationFn: async (payload: FormData) =>
      api.post('/customer/profile', withMethodOverride(payload, 'PUT')),
    onSuccess: () => {
      toast.success('Đã lưu hồ sơ. Nếu đổi email, hãy kiểm tra hộp thư mới để xác thực.')
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể lưu hồ sơ')
    },
  })
  const password = useMutation({
    mutationFn: async (payload: Record<string, string>) =>
      api.put('/customer/profile/password', payload),
    onSuccess: () => {
      toast.success(hasPassword ? 'Đã đổi mật khẩu thành công.' : 'Đã tạo mật khẩu đăng nhập.')
      void client.invalidateQueries({ queryKey: ['auth', 'me'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể cập nhật mật khẩu')
    },
  })
  const hasPassword = user?.has_password !== false

  return (
    <>
      <PageTitle
        icon={<CircleUserRound />}
        kicker="Tài khoản"
        title="Hồ sơ cá nhân"
        copy="Cập nhật thông tin liên hệ và bảo mật tài khoản."
      />
      <div className="settings-grid">
        <form
          key={profile.data?.id}
          className="panel-form"
          onSubmit={(event) => {
            event.preventDefault()
            update.mutate(new FormData(event.currentTarget))
          }}
        >
          <h2>Thông tin cá nhân</h2>
          <div className="form-grid">
            <label>
              <span>Họ tên</span>
              <input name="name" defaultValue={profile.data?.name} required />
            </label>
            <label>
              <span>Email</span>
              <input name="email" defaultValue={profile.data?.email} required />
            </label>
            <label>
              <span>Số điện thoại</span>
              <input name="phone" defaultValue={profile.data?.phone ?? ''} />
            </label>
            <label>
              <span>Địa chỉ</span>
              <input name="address" defaultValue={profile.data?.address ?? ''} />
            </label>
          </div>
          <label>
            <span>Ảnh đại diện</span>
            <input name="avatar" type="file" accept="image/*" />
          </label>
          <button disabled={update.isPending} className="button button--brand">
            {update.isPending ? 'Đang lưu...' : 'Lưu hồ sơ'}
          </button>
        </form>

        <form
          className="panel-form"
          onSubmit={(event) => {
            event.preventDefault()
            const data = new FormData(event.currentTarget)
            password.mutate({
              ...(hasPassword ? { current_password: String(data.get('current_password')) } : {}),
              password: String(data.get('password')),
              password_confirmation: String(data.get('password_confirmation')),
            })
          }}
        >
          <h2>{hasPassword ? 'Đổi mật khẩu' : 'Tạo mật khẩu đăng nhập'}</h2>
          {!hasPassword && (
            <p className="form-help">
              Bạn đang đăng nhập bằng Google. Tạo mật khẩu để có thể đăng nhập bằng email và mật khẩu.
            </p>
          )}
          {hasPassword && (
            <label>
              <span>Mật khẩu hiện tại</span>
              <input name="current_password" type="password" required />
            </label>
          )}
          <label>
            <span>Mật khẩu mới</span>
            <input name="password" type="password" required />
          </label>
          <label>
            <span>Xác nhận mật khẩu</span>
            <input name="password_confirmation" type="password" required />
          </label>
          <button disabled={password.isPending} className="button button--dark">
            {password.isPending ? 'Đang xử lý...' : hasPassword ? 'Cập nhật mật khẩu' : 'Tạo mật khẩu'}
          </button>
          <p className="form-help">Đang đăng nhập với {user?.email}</p>
        </form>
      </div>
    </>
  )
}

export function PageTitle({
  icon,
  kicker,
  title,
  copy,
  action,
}: {
  icon?: React.ReactNode
  kicker: string
  title: string
  copy: string
  action?: React.ReactNode
}) {
  return (
    <div className="workspace-title">
      <div className="workspace-title__icon">{icon}</div>
      <div>
        <span>{kicker}</span>
        <h1>{title}</h1>
        <p>{copy}</p>
      </div>
      {action && <div className="workspace-title__action">{action}</div>}
    </div>
  )
}

export function Empty({ text }: { text: string }) {
  return (
    <div className="empty-panel">
      <CalendarDays />
      <h2>Chưa có dữ liệu</h2>
      <p>{text}</p>
    </div>
  )
}
