/**
 * Chuẩn bị tệp trước khi gửi lên server.
 *
 * - Chặn sớm sai định dạng/quá dung lượng với thông báo tiếng Việt rõ ràng.
 * - Ảnh lớn được thu nhỏ + nén lại (JPEG): ảnh chụp điện thoại thường 3–8MB, vượt giới hạn
 *   upload mặc định của PHP (2MB/tệp, 8MB/request) trên nhiều máy nên server trả lỗi chung chung.
 */
export class UploadError extends Error {}

const MAX_BYTES = 5 * 1024 * 1024 // khớp rule max:5120 phía backend
const COMPRESS_ABOVE = 1_000_000
const MAX_EDGE = 1920
const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf']

const extensionOf = (name: string) => name.split('.').pop()?.toLowerCase() ?? ''

async function shrinkImage(file: File): Promise<File> {
  let bitmap: ImageBitmap
  try {
    bitmap = await createImageBitmap(file)
  } catch {
    throw new UploadError(`Không đọc được ảnh "${file.name}". Hãy dùng ảnh JPG, PNG hoặc WEBP.`)
  }

  const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  const context = canvas.getContext('2d')
  if (!context) return file
  context.fillStyle = '#fff' // PNG trong suốt → nền trắng thay vì đen khi đổi sang JPEG
  context.fillRect(0, 0, canvas.width, canvas.height)
  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  bitmap.close()

  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85))
  if (!blob || blob.size >= file.size) return file
  return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })
}

export async function prepareFile(file: File): Promise<File> {
  if (!EXTENSIONS.includes(extensionOf(file.name))) {
    throw new UploadError(`"${file.name}" không đúng định dạng. Chỉ nhận ảnh JPG, PNG, WEBP hoặc PDF.`)
  }
  const prepared = file.type.startsWith('image/') && file.size > COMPRESS_ABOVE ? await shrinkImage(file) : file
  if (prepared.size > MAX_BYTES) {
    throw new UploadError(`"${file.name}" nặng ${(prepared.size / 1048576).toFixed(1)}MB, vượt giới hạn 5MB.`)
  }
  return prepared
}

export async function prepareFormData(data: FormData): Promise<FormData> {
  const next = new FormData()
  for (const [key, value] of data.entries()) {
    if (value instanceof File && value.size > 0) {
      const file = await prepareFile(value)
      next.append(key, file, file.name)
    } else {
      next.append(key, value)
    }
  }
  return next
}
