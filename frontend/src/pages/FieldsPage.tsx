import { useQuery } from '@tanstack/react-query'
import {
  RotateCcw,
  Search,
  Sparkles,
  X,
} from 'lucide-react'
import { useSearchParams } from 'react-router'
import { useState, useEffect, useMemo } from 'react'
import { api } from '../api/client'
import { FieldCard } from '../components/FieldCard'
import { Pagination } from '../components/Pagination'
import { useReveal } from '../hooks/useReveal'
import type { Category, FieldType, Paginated, SportsField } from '../types/api'
import { formatMoney } from '../utils/format'

export function FieldsPage() {

  const [params, setParams] = useSearchParams()
  const query = params.toString()

  const fields = useQuery({
    queryKey: ['fields', query],
    queryFn: async () => (await api.get<Paginated<SportsField>>(`/fields?${query}`)).data,
  })

  const categories = useQuery({
    queryKey: ['categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/categories')).data.data,
  })

  const types = useQuery({
    queryKey: ['field-types'],
    queryFn: async () => (await api.get<{ data: FieldType[] }>('/field-types')).data.data,
  })

  useReveal([fields.data])

  // Debounced search input to prevent firing API and polluting history on every keystroke
  const [keywordInput, setKeywordInput] = useState(params.get('keyword') ?? '')

  useEffect(() => {
    setKeywordInput(params.get('keyword') ?? '')
  }, [params.get('keyword')])

  useEffect(() => {
    const handler = setTimeout(() => {
      const currentParam = params.get('keyword') ?? ''
      if (keywordInput !== currentParam) {
        const next = new URLSearchParams(params)
        if (keywordInput.trim()) next.set('keyword', keywordInput.trim())
        else next.delete('keyword')
        next.delete('page')
        setParams(next, { replace: true })
      }
    }, 320)

    return () => clearTimeout(handler)
  }, [keywordInput, params, setParams])

  const update = (name: string, value: string) => {
    const next = new URLSearchParams(params)
    if (value) next.set(name, value)
    else next.delete(name)
    next.delete('page')
    setParams(next, { replace: true })
  }

  const goToPage = (page: number) => {
    const next = new URLSearchParams(params)
    if (page > 1) next.set('page', String(page))
    else next.delete('page')
    setParams(next)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const activeCategory = params.get('category') ?? ''
  const activeTypeId = params.get('type_id') ?? ''
  const activeMaxPrice = params.get('max_price') ?? ''
  const activeKeyword = params.get('keyword') ?? ''

  const hasFilters = Boolean(activeCategory || activeTypeId || activeMaxPrice || activeKeyword)

  // Filter types by active category if selected
  const availableTypes = useMemo(() => {
    if (!types.data) return []
    if (!activeCategory) return types.data
    return types.data.filter((t) => t.category?.slug === activeCategory)
  }, [types.data, activeCategory])

  return (
    <div className="shell page-section">
      {/* Header */}
      <div className="page-heading reveal">
        <span className="kicker">
          <Sparkles size={14} /> Khám phá SportHub
        </span>
        <h1>Tìm sân cho trận đấu tiếp theo.</h1>
        <p>Lọc theo môn chơi, loại sân và ngân sách phù hợp nhất với bạn.</p>
      </div>

      {/* Category Pills Strip */}
      <div className="category-pill-strip">
        <button
          type="button"
          className={`category-pill ${!activeCategory ? 'category-pill--active' : ''}`}
          onClick={() => update('category', '')}
        >
          <span>Tất cả môn</span>
        </button>
        {categories.data?.map((cat) => (
          <button
            key={cat.id}
            type="button"
            className={`category-pill ${activeCategory === cat.slug ? 'category-pill--active' : ''}`}
            onClick={() => update('category', cat.slug === activeCategory ? '' : cat.slug)}
          >
            <span>{cat.icon ?? '⚽'}</span>
            <strong>{cat.name}</strong>
          </button>
        ))}
      </div>

      {/* Search & Filter Toolbar */}
      <div className="filter-bar">
        <label className="search-control">
          <Search size={18} />
          <input
            value={keywordInput}
            onChange={(event) => setKeywordInput(event.target.value)}
            placeholder="Tên sân, quận hoặc địa chỉ..."
          />
        </label>

        <select
          value={activeTypeId}
          onChange={(event) => update('type_id', event.target.value)}
          aria-label="Loại sân"
        >
          <option value="">Tất cả loại sân</option>
          {availableTypes.map((item) => (
            <option value={item.id} key={item.id}>
              {item.name}
            </option>
          ))}
        </select>

        {/* Quick price range select */}
        <select
          value={activeMaxPrice}
          onChange={(event) => update('max_price', event.target.value)}
          aria-label="Khoảng giá"
        >
          <option value="">Mọi mức giá</option>
          <option value="200000">Dưới 200.000đ/h</option>
          <option value="350000">Dưới 350.000đ/h</option>
          <option value="500000">Dưới 500.000đ/h</option>
          <option value="800000">Dưới 800.000đ/h</option>
        </select>

        {hasFilters && (
          <button
            type="button"
            className="button button--ghost"
            onClick={() => {
              setKeywordInput('')
              setParams({}, { replace: true })
            }}
            title="Đặt lại bộ lọc"
          >
            <RotateCcw size={14} /> Xóa lọc
          </button>
        )}
      </div>

      {/* Active filter chips badge */}
      {hasFilters && (
        <div className="active-filters">
          <span>Đang lọc:</span>
          {activeKeyword && (
            <span className="filter-chip">
              "{activeKeyword}"
              <button
                type="button"
                onClick={() => {
                  setKeywordInput('')
                  update('keyword', '')
                }}
              >
                <X size={12} />
              </button>
            </span>
          )}
          {activeCategory && (
            <span className="filter-chip">
              {categories.data?.find((c) => c.slug === activeCategory)?.name ?? activeCategory}
              <button type="button" onClick={() => update('category', '')}>
                <X size={12} />
              </button>
            </span>
          )}
          {activeTypeId && (
            <span className="filter-chip">
              {types.data?.find((t) => String(t.id) === activeTypeId)?.name ?? 'Loại sân'}
              <button type="button" onClick={() => update('type_id', '')}>
                <X size={12} />
              </button>
            </span>
          )}
          {activeMaxPrice && (
            <span className="filter-chip">
              ≤ {formatMoney(activeMaxPrice)}
              <button type="button" onClick={() => update('max_price', '')}>
                <X size={12} />
              </button>
            </span>
          )}

        </div>
      )}

      {/* Result Counter */}
      <div className="result-line">
        <strong>{fields.data?.meta.total ?? 0} sân thể thao</strong>
        <span>Cập nhật lịch trống thời gian thực</span>
      </div>

      {fields.isError && (
        <div className="notice notice--error">Không thể tải danh sách sân. Vui lòng thử lại.</div>
      )}

      {/* Grid or Skeletons */}
      {fields.isPending ? (
        <div className="field-grid">
          {Array.from({ length: 6 }).map((_, index) => (
            <div className="skeleton-card" key={index}>
              <div className="skeleton-card__img skeleton-shimmer" />
              <div className="skeleton-card__body">
                <div className="skeleton-line skeleton-line--short skeleton-shimmer" />
                <div className="skeleton-line skeleton-line--title skeleton-shimmer" />
                <div className="skeleton-line skeleton-line--title skeleton-shimmer" />
              </div>
            </div>
          ))}
        </div>
      ) : fields.data?.data && fields.data.data.length > 0 ? (
        <div className="field-grid">
          {fields.data.data.map((field, index) => (
            <FieldCard field={field} index={index} key={field.id} />
          ))}
        </div>
      ) : (
        <div className="empty-panel">
          <Search size={36} />
          <h2>Không tìm thấy sân phù hợp</h2>
          <p>Hãy thử thay đổi từ khóa hoặc xóa bớt tiêu chí lọc xem sao.</p>
          <button
            type="button"
            className="button button--brand"
            style={{ marginTop: 14 }}
            onClick={() => {
              setKeywordInput('')
              setParams({}, { replace: true })
            }}
          >
            Xem tất cả sân
          </button>
        </div>
      )}

      {/* Pagination */}
      {fields.data && (
        <Pagination
          page={fields.data.meta.current_page}
          lastPage={fields.data.meta.last_page}
          total={fields.data.meta.total}
          onPageChange={goToPage}
        />
      )}
    </div>
  )
}
