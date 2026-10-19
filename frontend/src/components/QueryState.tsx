import type { ReactNode } from 'react'

interface StandaloneQueryStateProps {
  type: 'loading' | 'error' | 'empty'
  message?: ReactNode
  onRetry?: () => void
  action?: ReactNode
  children?: never
  isPending?: never
  isError?: never
  isEmpty?: never
  empty?: never
  error?: ReactNode
}

interface WrappedQueryStateProps {
  isPending: boolean
  isError: boolean
  isEmpty: boolean
  children: ReactNode
  empty: ReactNode
  error?: ReactNode
  type?: never
  message?: never
  onRetry?: never
  action?: never
}

export type QueryStateProps = StandaloneQueryStateProps | WrappedQueryStateProps

export function QueryState(props: QueryStateProps) {
  if (props.type) {
    if (props.type === 'loading') {
      return (
        <div className="query-state query-state--loading" aria-live="polite">
          {props.message ?? 'Đang tải dữ liệu...'}
        </div>
      )
    }

    if (props.type === 'error') {
      return (
        <div className="query-state query-state--error" role="alert">
          <div>{props.message ?? props.error ?? 'Không thể tải dữ liệu. Vui lòng thử lại.'}</div>
          {props.onRetry && (
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={props.onRetry}
              style={{ marginTop: 10 }}
            >
              Thử lại
            </button>
          )}
        </div>
      )
    }

    return (
      <div className="query-state query-state--empty">
        <div>{props.message ?? 'Không có dữ liệu.'}</div>
        {props.action}
      </div>
    )
  }

  const { isPending, isError, isEmpty, children, empty, error } = props

  if (isPending) {
    return <div className="query-state query-state--loading" aria-live="polite">Đang tải dữ liệu...</div>
  }

  if (isError) {
    return <div className="query-state query-state--error" role="alert">{error ?? 'Không thể tải dữ liệu. Vui lòng thử lại.'}</div>
  }

  if (isEmpty) return <>{empty}</>

  return <>{children}</>
}