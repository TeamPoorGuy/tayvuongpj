import { ArrowUpRight, MapPin, Star } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import type { SportsField } from '../types/api'
import { formatMoney } from '../utils/format'

export function FieldCard({ field, index }: { field: SportsField; index?: number }) {
  const style = index !== undefined ? ({ '--i': index } as React.CSSProperties) : undefined
  // Ảnh lỗi (link hỏng/offline) → hiện khung SportHub thay vì icon ảnh vỡ.
  const [imageFailed, setImageFailed] = useState(false)
  return (
    <article className="field-card reveal" style={style}>
      <Link to={`/fields/${field.slug}`} className="field-card__image">
        {field.primary_image && !imageFailed ? <img src={field.primary_image} alt={field.name} onError={() => setImageFailed(true)} /> : <span>SportHub</span>}
        <span className="pill pill--dark">{field.field_type?.category?.name ?? 'Thể thao'}</span>
      </Link>
      <div className="field-card__body">
        <div className="rating"><Star size={14} fill="currentColor" /> {field.average_rating || 'Mới'} <span>({field.reviews_count})</span></div>
        <h3>{field.name}</h3>
        <p><MapPin size={15} /> {field.address}</p>
        <div className="field-card__footer">
          <div><small>Từ</small><strong>{formatMoney(field.price_per_hour)} <span>/giờ</span></strong></div>
          <Link className="round-link" to={`/fields/${field.slug}`} aria-label={`Xem ${field.name}`}><ArrowUpRight size={18} /></Link>
        </div>
      </div>
    </article>
  )
}
