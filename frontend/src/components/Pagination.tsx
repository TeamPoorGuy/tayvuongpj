import { ChevronLeft, ChevronRight } from 'lucide-react'

interface PaginationProps {
  page: number
  lastPage: number
  total?: number
  onPageChange: (page: number) => void
  disabled?: boolean
}

export function Pagination({
  page,
  lastPage,
  total,
  onPageChange,
  disabled = false,
}: PaginationProps) {
  if (lastPage <= 1) {
    if (total !== undefined && total > 0) {
      return (
        <div className="pagination">
          <span className="pagination__info">Tổng cộng: {total} mục</span>
        </div>
      )
    }
    return null
  }

  // Generate page numbers with ellipsis
  const getPageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 1 // number of pages around current page

    for (let i = 1; i <= lastPage; i++) {
      if (
        i === 1 ||
        i === lastPage ||
        (i >= page - delta && i <= page + delta)
      ) {
        pages.push(i)
      } else if (pages[pages.length - 1] !== '...') {
        pages.push('...')
      }
    }
    return pages
  }

  const pages = getPageNumbers()

  return (
    <nav className="pagination" aria-label="Phân trang">
      <div className="pagination__info">
        Trang <strong>{page}</strong> / <strong>{lastPage}</strong>
        {total !== undefined && ` (${total} mục)`}
      </div>
      <div className="pagination__controls">
        <button
          type="button"
          className="button button--ghost pagination__button"
          disabled={page <= 1 || disabled}
          onClick={() => onPageChange(page - 1)}
          aria-label="Trang trước"
        >
          <ChevronLeft size={16} />
          <span>Trước</span>
        </button>

        <div className="pagination__pages">
          {pages.map((p, idx) =>
            typeof p === 'number' ? (
              <button
                key={p}
                type="button"
                className={`pagination__page ${p === page ? 'pagination__page--active' : ''}`}
                disabled={disabled}
                onClick={() => onPageChange(p)}
                aria-current={p === page ? 'page' : undefined}
              >
                {p}
              </button>
            ) : (
              <span key={`ellipsis-${idx}`} className="pagination__ellipsis">
                …
              </span>
            ),
          )}
        </div>

        <button
          type="button"
          className="button button--ghost pagination__button"
          disabled={page >= lastPage || disabled}
          onClick={() => onPageChange(page + 1)}
          aria-label="Trang sau"
        >
          <span>Sau</span>
          <ChevronRight size={16} />
        </button>
      </div>
    </nav>
  )
}
