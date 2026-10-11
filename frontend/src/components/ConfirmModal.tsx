import { AlertTriangle } from 'lucide-react'
import { Modal } from './Modal'

interface ConfirmModalProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  danger?: boolean
  busy?: boolean
}

export function ConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = 'Xác nhận',
  cancelText = 'Hủy bỏ',
  danger = true,
  busy = false,
}: ConfirmModalProps) {
  return (
    <Modal isOpen={isOpen} onClose={onClose} ariaLabel={title}>
      <div className="confirm-modal-body">
        <div className={`confirm-modal-icon ${danger ? 'confirm-modal-icon--danger' : ''}`}>
          <AlertTriangle size={24} />
        </div>
        <div>
          <h2>{title}</h2>
          <p>{message}</p>
        </div>
      </div>
      <div className="modal__actions" style={{ marginTop: 20 }}>
        <button
          type="button"
          disabled={busy}
          className="button button--ghost"
          onClick={onClose}
        >
          {cancelText}
        </button>
        <button
          type="button"
          disabled={busy}
          className={`button ${danger ? 'button--danger' : 'button--brand'}`}
          onClick={onConfirm}
        >
          {busy ? 'Đang xử lý...' : confirmText}
        </button>
      </div>
    </Modal>
  )
}
