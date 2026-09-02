import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, CircleUserRound, Clock3, MapPin, Star } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, initializeCsrf } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { StatusBadge } from '../../components/StatusBadge'
import { useAuth } from '../../context/auth-context'
import type { ApiEnvelope, Booking, Paginated, User } from '../../types/api'

const currency = new Intl.NumberFormat('vi-VN')

export function CustomerBookingsPage() {
  const client = useQueryClient()
  const [reviewing, setReviewing] = useState<Booking | null>(null)
  const bookings = useQuery({ queryKey: ['customer', 'bookings'], queryFn: async () => (await api.get<Paginated<Booking>>('/customer/bookings')).data })
  const cancel = useMutation({ mutationFn: async (booking: Booking) => { await initializeCsrf(); return api.patch(`/customer/bookings/${booking.id}/cancel`) }, onSuccess: () => client.invalidateQueries({ queryKey: ['customer', 'bookings'] }) })
  const review = useMutation({ mutationFn: async (payload: { booking_id: number; rating: number; comment: string }) => { await initializeCsrf(); return api.post('/customer/reviews', payload) }, onSuccess: () => { setReviewing(null); void client.invalidateQueries({ queryKey: ['customer', 'bookings'] }) } })

  const submitReview = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); if (!reviewing) return
    const data = new FormData(event.currentTarget)
    review.mutate({ booking_id: reviewing.id, rating: Number(data.get('rating')), comment: String(data.get('comment')) })
  }

  return <><PageTitle icon={<CalendarDays />} kicker="Lịch chơi" title="Các lượt đặt của tôi" copy="Theo dõi xác nhận, hủy lịch và chia sẻ đánh giá sau trận." />
    {cancel.isError && <div className="notice notice--error">{errorMessage(cancel.error)}</div>}
    <div className="booking-list">{bookings.data?.data.map((booking) => <article className="booking-item" key={booking.id}><div className="booking-item__image">{booking.field?.primary_image && <img src={booking.field.primary_image} alt="" />}</div><div className="booking-item__body"><div className="booking-item__top"><div><small>#BK-{String(booking.id).padStart(5, '0')}</small><h3>{booking.field?.name}</h3></div><StatusBadge status={booking.status} /></div><div className="booking-meta"><span><CalendarDays />{new Date(booking.booking_date).toLocaleDateString('vi-VN')}</span><span><Clock3 />{booking.time_slot?.start_time} – {booking.time_slot?.end_time}</span><span><MapPin />{booking.field?.address}</span></div><div className="booking-item__footer"><strong>{currency.format(booking.total_price)}đ</strong><div>{booking.can_be_cancelled && <button className="button button--danger" onClick={() => cancel.mutate(booking)}>Hủy lịch</button>}{booking.status === 'completed' && !booking.review && <button className="button button--brand" onClick={() => setReviewing(booking)}><Star size={15} /> Đánh giá</button>}</div></div></div></article>)}</div>
    {!bookings.isPending && !bookings.data?.data.length && <Empty text="Bạn chưa có lịch đặt sân nào." />}
    {reviewing && <div className="modal"><form className="modal__card" onSubmit={submitReview}><h2>Đánh giá {reviewing.field?.name}</h2><label><span>Số sao</span><select name="rating" defaultValue="5">{[5,4,3,2,1].map((n) => <option value={n} key={n}>{n} sao</option>)}</select></label><label><span>Nội dung</span><textarea name="comment" required /></label>{review.isError && <div className="notice notice--error">{errorMessage(review.error)}</div>}<div className="modal__actions"><button type="button" className="button button--ghost" onClick={() => setReviewing(null)}>Đóng</button><button className="button button--brand">Gửi đánh giá</button></div></form></div>}
  </>
}

export function CustomerProfilePage() {
  const { user } = useAuth()
  const client = useQueryClient()
  const [message, setMessage] = useState('')
  const profile = useQuery({ queryKey: ['customer', 'profile'], queryFn: async () => (await api.get<ApiEnvelope<User>>('/customer/profile')).data.data })
  const update = useMutation({ mutationFn: async (payload: FormData) => { await initializeCsrf(); payload.append('_method', 'PUT'); return api.post('/customer/profile', payload) }, onSuccess: () => { setMessage('Đã lưu hồ sơ.'); void client.invalidateQueries({ queryKey: ['auth', 'me'] }) } })
  const password = useMutation({ mutationFn: async (payload: Record<string, string>) => { await initializeCsrf(); return api.put('/customer/profile/password', payload) }, onSuccess: () => setMessage('Đã đổi mật khẩu.') })

  return <><PageTitle icon={<CircleUserRound />} kicker="Tài khoản" title="Hồ sơ cá nhân" copy="Cập nhật thông tin liên hệ và bảo mật tài khoản." />{message && <div className="notice notice--success">{message}</div>}<div className="settings-grid"><form key={profile.data?.id} className="panel-form" onSubmit={(event) => { event.preventDefault(); update.mutate(new FormData(event.currentTarget)) }}><h2>Thông tin cá nhân</h2><div className="form-grid"><label><span>Họ tên</span><input name="name" defaultValue={profile.data?.name} /></label><label><span>Email</span><input name="email" defaultValue={profile.data?.email} /></label><label><span>Số điện thoại</span><input name="phone" defaultValue={profile.data?.phone ?? ''} /></label><label><span>Địa chỉ</span><input name="address" defaultValue={profile.data?.address ?? ''} /></label></div><label><span>Ảnh đại diện</span><input name="avatar" type="file" accept="image/*" /></label>{update.isError && <div className="notice notice--error">{errorMessage(update.error)}</div>}<button className="button button--brand">Lưu hồ sơ</button></form><form className="panel-form" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); password.mutate({ current_password: String(data.get('current_password')), password: String(data.get('password')), password_confirmation: String(data.get('password_confirmation')) }) }}><h2>Đổi mật khẩu</h2><label><span>Mật khẩu hiện tại</span><input name="current_password" type="password" required /></label><label><span>Mật khẩu mới</span><input name="password" type="password" required /></label><label><span>Xác nhận mật khẩu</span><input name="password_confirmation" type="password" required /></label>{password.isError && <div className="notice notice--error">{errorMessage(password.error)}</div>}<button className="button button--dark">Cập nhật mật khẩu</button><p className="form-help">Đang đăng nhập với {user?.email}</p></form></div></>
}

export function PageTitle({ icon, kicker, title, copy, action }: { icon?: React.ReactNode; kicker: string; title: string; copy: string; action?: React.ReactNode }) { return <div className="workspace-title"><div className="workspace-title__icon">{icon}</div><div><span>{kicker}</span><h1>{title}</h1><p>{copy}</p></div>{action && <div className="workspace-title__action">{action}</div>}</div> }
export function Empty({ text }: { text: string }) { return <div className="empty-panel"><CalendarDays /><h2>Chưa có dữ liệu</h2><p>{text}</p></div> }
