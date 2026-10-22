import { ImagePlus, X } from 'lucide-react'
import { useEffect, useMemo, useRef } from 'react'
import { prepareFile } from '../api/upload'
import { errorMessage } from '../api/errors'
import type { ComplaintAttachment } from '../types/api'
import { useToast } from './Toast'

export const MAX_EVIDENCE = 3

/** Chọn tối đa 3 ảnh chứng cứ; ảnh lớn được nén trước khi gửi. */
export function EvidencePicker({ files, onChange }: { files: File[]; onChange: (files: File[]) => void }) {
  const toast = useToast()
  const input = useRef<HTMLInputElement>(null)
  const previews = useMemo(() => files.map((file) => URL.createObjectURL(file)), [files])
  useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews])

  const add = async (picked: FileList | null) => {
    if (!picked) return
    const next = [...files]
    for (const file of Array.from(picked)) {
      if (next.length >= MAX_EVIDENCE) {
        toast.error(`Chỉ đính kèm tối đa ${MAX_EVIDENCE} ảnh.`, 'Quá số ảnh cho phép')
        break
      }
      if (!file.type.startsWith('image/')) {
        toast.error(`"${file.name}" không phải ảnh. Chỉ nhận JPG, PNG hoặc WEBP.`, 'Sai định dạng')
        continue
      }
      try {
        next.push(await prepareFile(file))
      } catch (err) {
        toast.error(errorMessage(err), 'Không thêm được ảnh')
      }
    }
    onChange(next)
    if (input.current) input.current.value = ''
  }

  return (
    <div style={{ display: 'grid', gap: 8 }}>
      <span style={{ fontSize: 13, fontWeight: 600 }}>Ảnh chứng cứ (tuỳ chọn, tối đa {MAX_EVIDENCE})</span>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
        {previews.map((url, index) => (
          <div key={url} style={{ position: 'relative', width: 72, height: 72 }}>
            <img src={url} alt={`Ảnh chứng cứ ${index + 1}`} style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--line)' }} />
            <button
              type="button"
              aria-label={`Bỏ ảnh ${index + 1}`}
              onClick={() => onChange(files.filter((_, i) => i !== index))}
              style={{ position: 'absolute', top: -6, right: -6, width: 22, height: 22, borderRadius: 999, border: 0, background: 'var(--ink)', color: '#fff', display: 'grid', placeItems: 'center', cursor: 'pointer' }}
            >
              <X size={12} />
            </button>
          </div>
        ))}
        {files.length < MAX_EVIDENCE && (
          <button type="button" className="button button--ghost" style={{ width: 72, height: 72, padding: 0, display: 'grid', placeItems: 'center' }} onClick={() => input.current?.click()} aria-label="Thêm ảnh chứng cứ">
            <ImagePlus size={20} />
          </button>
        )}
      </div>
      <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => void add(e.target.files)} />
    </div>
  )
}

/** Ảnh chứng cứ đã gửi; bấm để xem ảnh gốc (chỉ hai bên và admin tải được). */
export function EvidenceGallery({ attachments, label }: { attachments?: ComplaintAttachment[]; label?: string }) {
  if (!attachments?.length) return null
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
      {label && <small className="text-muted">{label}</small>}
      {attachments.map((a) => (
        <a key={a.id} href={a.url} target="_blank" rel="noreferrer" title={a.name}>
          <img src={a.url} alt={a.name} loading="lazy" style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--line)' }} />
        </a>
      ))}
    </div>
  )
}
