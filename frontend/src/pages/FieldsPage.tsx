import { useQuery } from '@tanstack/react-query'
import { Search, SlidersHorizontal } from 'lucide-react'
import { useSearchParams } from 'react-router'
import { api } from '../api/client'
import { FieldCard } from '../components/FieldCard'
import type { Category, FieldType, Paginated, SportsField } from '../types/api'

export function FieldsPage() {
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const fields = useQuery({ queryKey: ['fields', query], queryFn: async () => (await api.get<Paginated<SportsField>>(`/fields?${query}`)).data })
  const categories = useQuery({ queryKey: ['categories'], queryFn: async () => (await api.get<{ data: Category[] }>('/categories')).data.data })
  const types = useQuery({ queryKey: ['field-types'], queryFn: async () => (await api.get<{ data: FieldType[] }>('/field-types')).data.data })

  const update = (name: string, value: string) => {
    const next = new URLSearchParams(params)
    if (value) next.set(name, value)
    else next.delete(name)
    next.delete('page')
    setParams(next)
  }

  return (
    <div className="shell page-section">
      <div className="page-heading"><span className="kicker">Khám phá SportHub</span><h1>Tìm sân cho trận đấu tiếp theo.</h1><p>Lọc theo môn chơi, loại sân và ngân sách của bạn.</p></div>
      <div className="filter-bar">
        <label className="search-control"><Search size={18} /><input value={params.get('keyword') ?? ''} onChange={(event) => update('keyword', event.target.value)} placeholder="Tên sân hoặc địa chỉ" /></label>
        <select value={params.get('category') ?? ''} onChange={(event) => update('category', event.target.value)}><option value="">Tất cả môn</option>{categories.data?.map((item) => <option value={item.slug} key={item.id}>{item.icon} {item.name}</option>)}</select>
        <select value={params.get('type_id') ?? ''} onChange={(event) => update('type_id', event.target.value)}><option value="">Mọi loại sân</option>{types.data?.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select>
        <input type="number" value={params.get('max_price') ?? ''} onChange={(event) => update('max_price', event.target.value)} placeholder="Giá tối đa" />
        <button className="button button--dark" onClick={() => setParams({})}><SlidersHorizontal size={15} /> Đặt lại</button>
      </div>
      <div className="result-line"><strong>{fields.data?.meta.total ?? 0} sân phù hợp</strong><span>Dữ liệu trực tiếp từ Laravel API</span></div>
      {fields.isPending && <div className="notice">Đang tìm sân phù hợp...</div>}
      {fields.isError && <div className="notice notice--error">Không thể tải danh sách sân.</div>}
      <div className="field-grid">{fields.data?.data.map((field) => <FieldCard field={field} key={field.id} />)}</div>
      {fields.data && fields.data.meta.last_page > 1 && <div className="pager">
        {Array.from({ length: fields.data.meta.last_page }).map((_, index) => <button className={fields.data?.meta.current_page === index + 1 ? 'active' : ''} onClick={() => update('page', String(index + 1))} key={index}>{index + 1}</button>)}
      </div>}
    </div>
  )
}
