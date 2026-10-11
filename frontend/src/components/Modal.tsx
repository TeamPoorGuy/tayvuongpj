import { motion, AnimatePresence } from 'framer-motion'
import React, { useEffect, useRef } from 'react'

interface ModalProps {
  isOpen: boolean
  onClose: () => void
  children: React.ReactNode
  wide?: boolean
  asForm?: boolean
  onSubmit?: React.FormEventHandler<HTMLFormElement>
  title?: string
  ariaLabel?: string
}

export function Modal({
  isOpen,
  onClose,
  children,
  wide = false,
  asForm = false,
  onSubmit,
  title,
  ariaLabel,
}: ModalProps) {
  const cardClass = `modal__card ${wide ? 'modal__card--wide' : ''}`
  const formRef = useRef<HTMLFormElement>(null)
  const divRef = useRef<HTMLDivElement>(null)
  const previousFocus = useRef<HTMLElement | null>(null)
  // onClose thường là closure inline; giữ trong ref để effect không chạy lại (và giật focus) mỗi lần cha re-render.
  const onCloseRef = useRef(onClose)
  onCloseRef.current = onClose

  // Close on Escape key press
  useEffect(() => {
    if (!isOpen) return
    previousFocus.current = document.activeElement as HTMLElement | null
    const focusable = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    const getModal = () => formRef.current ?? divRef.current
    const focusFirst = () => getModal()?.querySelector<HTMLElement>(focusable)?.focus()
    requestAnimationFrame(focusFirst)
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onCloseRef.current()
        return
      }
      if (e.key === 'Tab' && getModal()) {
        const elements = [...getModal()!.querySelectorAll<HTMLElement>(focusable)]
        if (!elements.length) return
        const first = elements[0]
        const last = elements[elements.length - 1]
        if (!getModal()!.contains(document.activeElement)) {
          e.preventDefault()
          first.focus()
        } else if (e.shiftKey && document.activeElement === first) {
          e.preventDefault()
          last.focus()
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault()
          first.focus()
        }
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => {
      window.removeEventListener('keydown', handleKeyDown)
      previousFocus.current?.focus()
    }
  }, [isOpen])

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          className="modal"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2, ease: [0.16, 1, 0.3, 1] }}
          onClick={(e) => {
            if (e.target === e.currentTarget) onClose()
          }}
          role="dialog"
          aria-modal="true"
          aria-label={title ?? ariaLabel ?? 'Hộp thoại'}
        >
          {asForm ? (
            <motion.form
              ref={formRef}
              className={cardClass}
              onSubmit={onSubmit}
              initial={{ opacity: 0, scale: 0.95, y: 16 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.96, y: 10 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
            >
              {title && <h2 className="modal__title">{title}</h2>}
              {children}
            </motion.form>
          ) : (
            <motion.div
              ref={divRef}
              className={cardClass}
              initial={{ opacity: 0, scale: 0.95, y: 16 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.96, y: 10 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
            >
              {title && <h2 className="modal__title">{title}</h2>}
              {children}
            </motion.div>
          )}
        </motion.div>
      )}
    </AnimatePresence>
  )
}
