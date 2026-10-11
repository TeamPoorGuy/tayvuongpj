import { useEffect, useState } from 'react'

/** Giá trị trễ `delay` ms: ô tìm kiếm không gọi API ở mỗi phím gõ. */
export function useDebouncedValue<T>(value: T, delay = 320): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])
  return debounced
}
