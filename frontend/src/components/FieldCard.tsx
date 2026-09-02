import { ArrowUpRight, MapPin, Star } from 'lucide-react'
import { Link } from 'react-router'
import type { SportsField } from '../types/api'

const currency = new Intl.NumberFormat('vi-VN')

export function FieldCard({ field }: { field: SportsField }) {
  return (
    <article className="field-card">
      <Link to={`/fields/${field.slug}`} className="field-card__image">
        {field.primary_image ? <img src={field.primary_image} alt={field.name} /> : <span>SportHub</span>}
        <span className="pill pill--dark">{field.field_type?.category?.name ?? 'Thể thao'}</span>
      </Link>
      <div className="field-card__body">
        <div className="rating"><Star size={14} fill="currentColor" /> {field.average_rating || 'Mới'} <span>({field.reviews_count})</span></div>
        <h3>{field.name}</h3>
        <p><MapPin size={15} /> {field.address}</p>
        <div className="field-card__footer">
          <div><small>Từ</small><strong>{currency.format(field.price_per_hour)}đ <span>/giờ</span></strong></div>
          <Link className="round-link" to={`/fields/${field.slug}`} aria-label={`Xem ${field.name}`}><ArrowUpRight size={18} /></Link>
        </div>
      </div>
    </article>
  )
}
