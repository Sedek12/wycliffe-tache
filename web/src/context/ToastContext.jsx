import { createContext, useCallback, useContext, useState } from 'react'

const ToastContext = createContext(null)
let seq = 0

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const remove = useCallback((id) => {
    setToasts((t) => t.filter((x) => x.id !== id))
  }, [])

  const push = useCallback(
    (message, type = 'info') => {
      const id = ++seq
      setToasts((t) => [...t, { id, message, type }])
      setTimeout(() => remove(id), 4500)
    },
    [remove],
  )

  const toast = {
    success: (m) => push(m, 'success'),
    error: (m) => push(m, 'error'),
    info: (m) => push(m, 'info'),
  }

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div
        style={{
          position: 'fixed',
          right: 16,
          bottom: 16,
          display: 'flex',
          flexDirection: 'column',
          gap: 8,
          zIndex: 200,
        }}
      >
        {toasts.map((t) => (
          <div
            key={t.id}
            onClick={() => remove(t.id)}
            className="card card-pad"
            style={{
              cursor: 'pointer',
              minWidth: 260,
              maxWidth: 360,
              borderLeft: `4px solid ${
                t.type === 'success'
                  ? 'var(--green)'
                  : t.type === 'error'
                    ? 'var(--red)'
                    : 'var(--wy-blue-600)'
              }`,
              padding: '12px 14px',
              fontSize: '0.88rem',
            }}
          >
            {t.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast doit être utilisé dans <ToastProvider>')
  return ctx
}
