import axios from 'axios'
import { UploadError } from './upload'

export function errorMessage(error: unknown): string {
  if (error instanceof UploadError) return error.message
  if (axios.isAxiosError(error)) {
    if (error.response?.status === 413) {
      return 'Dung lượng tệp tải lên vượt giới hạn của máy chủ. Hãy chọn tệp nhỏ hơn hoặc ít tệp hơn.'
    }
    const errors = error.response?.data?.errors as Record<string, string[]> | undefined
    const first = errors && Object.values(errors)[0]?.[0]
    return first ?? error.response?.data?.message ?? 'Không thể xử lý yêu cầu.'
  }
  return 'Đã có lỗi không mong muốn.'
}
