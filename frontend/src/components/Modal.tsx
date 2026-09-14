import { motion, AnimatePresence } from 'framer-motion'
import React from 'react'

interface ModalProps {
  isOpen: boolean
  onClose: () => void
  children: React.ReactNode
  wide?: boolean
  asForm?: boolean
  onSubmit?: React.FormEventHandler<HTMLFormElement>
}

export function Modal({ isOpen, onClose, children, wide = false, asForm = false, onSubmit }: ModalProps) {
  const cardClass = `modal__card ${wide ? 'modal__card--wide' : ''}`

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
        >
          {asForm ? (
            <motion.form
              className={cardClass}
              onSubmit={onSubmit}
              initial={{ opacity: 0, scale: 0.95, y: 16 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.96, y: 10 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
            >
              {children}
            </motion.form>
          ) : (
            <motion.div
              className={cardClass}
              initial={{ opacity: 0, scale: 0.95, y: 16 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.96, y: 10 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
            >
              {children}
            </motion.div>
          )}
        </motion.div>
      )}
    </AnimatePresence>
  )
}
