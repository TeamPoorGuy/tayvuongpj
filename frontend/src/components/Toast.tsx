import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react'
import { motion, AnimatePresence } from 'framer-motion'
import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'

export type ToastType = 'success' | 'error' | 'info'

interface ToastMessage {
  id: string
  type: ToastType
  title?: string
  message: string
}

interface ToastContextValue {
  toast: (message: string, type?: ToastType, title?: string) => void
  success: (message: string, title?: string) => void
  error: (message: string, title?: string) => void
  info: (message: string, title?: string) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastMessage[]>([])

  const remove = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  const add = useCallback((message: string, type: ToastType = 'info', title?: string) => {
    const id = Math.random().toString(36).slice(2, 9)
    setToasts((prev) => [...prev.slice(-3), { id, type, title, message }])
    setTimeout(() => remove(id), 3800)
  }, [remove])

  const contextValue: ToastContextValue = {
    toast: add,
    success: (msg, title) => add(msg, 'success', title),
    error: (msg, title) => add(msg, 'error', title),
    info: (msg, title) => add(msg, 'info', title),
  }

  return (
    <ToastContext.Provider value={contextValue}>
      {children}
      <div className="toast-viewport" aria-live="polite" aria-atomic="true">
        <AnimatePresence>
          {toasts.map((item) => (
            <motion.div
              key={item.id}
              layout
              initial={{ opacity: 0, y: 18, scale: 0.94 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, scale: 0.92, transition: { duration: 0.2 } }}
              className={`toast-item toast-item--${item.type}`}
            >
              <div className="toast-item__icon">
                {item.type === 'success' && <CheckCircle2 size={18} />}
                {item.type === 'error' && <AlertCircle size={18} />}
                {item.type === 'info' && <Info size={18} />}
              </div>
              <div className="toast-item__content">
                {item.title && <strong>{item.title}</strong>}
                <span>{item.message}</span>
              </div>
              <button
                type="button"
                className="toast-item__close"
                onClick={() => remove(item.id)}
                aria-label="Đóng thông báo"
              >
                <X size={14} />
              </button>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext)
  if (!context) {
    // Fallback if rendered outside provider
    return {
      toast: (msg) => console.log(msg),
      success: (msg) => console.log(msg),
      error: (msg) => console.error(msg),
      info: (msg) => console.info(msg),
    }
  }
  return context
}
