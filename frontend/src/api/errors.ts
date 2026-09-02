import axios from 'axios'

export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const errors = error.response?.data?.errors as Record<string, string[]> | undefined
    const first = errors && Object.values(errors)[0]?.[0]
    return first ?? error.response?.data?.message ?? 'Không thể xử lý yêu cầu.'
  }
  return 'Đã có lỗi không mong muốn.'
}
