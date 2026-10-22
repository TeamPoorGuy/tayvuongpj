import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { Modal } from './Modal'
import { useToast } from './Toast'

/** Bên bị khiếu nại gửi một phản hồi duy nhất trước khi admin quyết định. */
export function ComplaintRespondModal({
  complaintId,
  role,
  onClose,
}: {
  complaintId: number | null
  role: 'customer' | 'field-owner'
  onClose: () => void
}) {
  const toast = useToast()
  const [response, setResponse] = useState('')

  const respond = useMutation({
    mutationFn: async () => (await api.post(`/${role}/complaints/${complaintId}/respond`, { response })).data,
    onSuccess: () => {
      toast.success('Đã gửi phản hồi tới Ban quản trị.')
      setResponse('')
      onClose()
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể gửi phản hồi'),
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    respond.mutate()
  }

  return (
    <Modal isOpen={complaintId !== null} onClose={onClose} title="Phản hồi khiếu nại">
      <form onSubmit={submit} className="form-stack" style={{ maxWidth: 480 }}>
        <p className="form-help" style={{ margin: 0 }}>
          Bạn chỉ phản hồi được một lần. Hãy nêu rõ sự việc và chứng cứ để Ban quản trị xem xét công bằng cho cả hai bên.
        </p>
        <label>
          <span>Nội dung phản hồi (5–2000 ký tự)</span>
          <textarea required rows={5} minLength={5} maxLength={2000} value={response} onChange={(e) => setResponse(e.target.value)} />
        </label>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
          <button type="button" className="button button--ghost" onClick={onClose}>
            Đóng
          </button>
          <button type="submit" className="button button--brand" disabled={respond.isPending}>
            {respond.isPending ? 'Đang gửi...' : 'Gửi phản hồi'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
