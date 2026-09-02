import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, Check, Clock3, MapPin, ShieldCheck, Star } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { useState } from 'react'
import { api, initializeCsrf } from '../api/client'
import { errorMessage } from '../api/errors'
import { useAuth } from '../context/auth-context'
import type { ApiEnvelope, SportsField, TimeSlot } from '../types/api'

const currency = new Intl.NumberFormat('vi-VN')

export function FieldDetailPage() {
  const { slug = '' } = useParams()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [slotId, setSlotId] = useState<number | null>(null)
  const [notes, setNotes] = useState('')
  const field = useQuery({ queryKey: ['field', slug], queryFn: async () => (await api.get<ApiEnvelope<SportsField>>(`/fields/${slug}`)).data.data })
  const slots = useQuery({ queryKey: ['slots', field.data?.id, date], enabled: !!field.data, queryFn: async () => (await api.get<ApiEnvelope<{ date: string; slots: TimeSlot[] }>>(`/fields/${field.data?.id}/available-slots?date=${date}`)).data.data.slots })
  const booking = useMutation({
    mutationFn: async () => {
      await initializeCsrf()
      return api.post('/customer/bookings', { sports_field_id: field.data?.id, time_slot_id: slotId, booking_date: date, notes })
    },
    onSuccess: () => { setSlotId(null); void queryClient.invalidateQueries({ queryKey: ['slots'] }) },
  })

  if (field.isPending) return <div className="page-loader">Đang tải thông tin sân...</div>
  if (!field.data) return <div className="empty-page"><h1>Không tìm thấy sân</h1></div>
  const item = field.data
  const images = item.images?.length ? item.images : item.primary_image ? [{ id: 0, url: item.primary_image, is_primary: true }] : []

  return (
    <div className="shell page-section detail-page">
      <div className="detail-head"><div><span className="kicker">{item.field_type?.category?.name} · {item.field_type?.name}</span><h1>{item.name}</h1><p><MapPin size={16} /> {item.address}</p></div><div className="detail-rating"><Star fill="currentColor" /> <strong>{item.average_rating || 'Mới'}</strong><span>{item.reviews_count} đánh giá</span></div></div>
      <div className="gallery">{images.slice(0, 4).map((image, index) => <img className={index === 0 ? 'gallery__main' : ''} src={image.url} alt={`${item.name} ${index + 1}`} key={image.id} />)}</div>
      <div className="detail-grid">
        <div>
          <section className="content-card"><h2>Thông tin sân</h2><p>{item.description || 'Chủ sân chưa cập nhật mô tả chi tiết.'}</p><div className="feature-list"><span><ShieldCheck /> Sân đã kiểm duyệt</span><span><Clock3 /> Khung giờ 90 phút</span><span><Check /> Xác nhận bởi chủ sân</span></div></section>
          <section className="content-card"><h2>Đánh giá gần đây</h2>{item.reviews?.filter((review) => review.is_visible).map((review) => <article className="review" key={review.id}><div><strong>{review.user?.name}</strong><span>{'★'.repeat(review.rating)}</span></div><p>{review.comment}</p></article>)}</section>
        </div>
        <aside className="booking-card">
          <div className="booking-price"><span>Giá thuê mỗi giờ</span><strong>{currency.format(item.price_per_hour)}đ</strong></div>
          <label><span>Ngày chơi</span><input type="date" min={new Date().toISOString().slice(0, 10)} value={date} onChange={(event) => { setDate(event.target.value); setSlotId(null) }} /></label>
          <div><span className="field-label">Khung giờ còn trống</span><div className="slot-grid">{slots.data?.map((slot) => <button disabled={slot.is_booked} className={slotId === slot.id ? 'selected' : ''} onClick={() => setSlotId(slot.id)} key={slot.id}>{slot.start_time} – {slot.end_time}</button>)}</div></div>
          <label><span>Ghi chú</span><textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Yêu cầu thêm cho chủ sân..." /></label>
          {booking.isSuccess && <div className="notice notice--success">Đã gửi yêu cầu đặt sân.</div>}
          {booking.isError && <div className="notice notice--error">{errorMessage(booking.error)}</div>}
          {!user ? <Link className="button button--dark button--block" to="/login">Đăng nhập để đặt sân</Link> : user.role !== 'customer' ? <div className="notice">Chỉ tài khoản khách hàng có thể đặt sân.</div> : <button disabled={!slotId || booking.isPending} onClick={() => booking.mutate()} className="button button--brand button--block"><CalendarDays size={17} /> {booking.isPending ? 'Đang gửi...' : 'Gửi yêu cầu đặt sân'}</button>}
        </aside>
      </div>
    </div>
  )
}
