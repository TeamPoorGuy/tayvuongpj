import type { ReactNode } from 'react'

interface QueryStateProps {
  isPending: boolean
  isError: boolean
  isEmpty: boolean
  children: ReactNode
  empty: ReactNode
  error?: ReactNode
}

export function QueryState({ isPending, isError, isEmpty, children, empty, error }: QueryStateProps) {
  if (isPending) {
    return <div className="query-state query-state--loading" aria-live="polite">Đang tải dữ liệu...</div>
  }

  if (isError) {
    return <div className="query-state query-state--error" role="alert">{error ?? 'Không thể tải dữ liệu. Vui lòng thử lại.'}</div>
  }

  if (isEmpty) return <>{empty}</>

  return <>{children}</>
}