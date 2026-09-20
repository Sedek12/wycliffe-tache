import { useEffect } from 'react'
import { STATUS_BADGE, MENTION_BADGE } from '../lib/format'
import { IconX } from './Icons'

export function Spinner({ label = 'Chargement…' }) {
  return <div className="center-page">{label}</div>
}

export function EmptyState({ title = 'Rien à afficher', hint, action }) {
  return (
    <div className="empty">
      <p style={{ fontWeight: 600, color: 'var(--text-soft)' }}>{title}</p>
      {hint && <p className="faint">{hint}</p>}
      {action}
    </div>
  )
}

export function Avatar({ user, size = 32 }) {
  if (!user) return null
  const style = { width: size, height: size, fontSize: size * 0.36 }
  if (user.avatar_url) {
    return <img className="avatar" src={user.avatar_url} alt={user.name} style={style} />
  }
  return (
    <span className="avatar" style={style} title={user.name}>
      {user.initials || user.name?.slice(0, 2).toUpperCase()}
    </span>
  )
}

export function StatusBadge({ status, label }) {
  return <span className={`badge ${STATUS_BADGE[status] || 'badge-grey'}`}>{label || status}</span>
}

export function MentionBadge({ mention, label }) {
  return <span className={`badge ${MENTION_BADGE[mention] || 'badge-grey'}`}>{label || mention}</span>
}

export function ProgressBar({ value = 0 }) {
  return (
    <div className="progress" title={`${value}%`}>
      <span style={{ width: `${Math.min(100, Math.max(0, value))}%` }} />
    </div>
  )
}

export function ProgressRing({ value = 0, size = 120, caption = 'avancement' }) {
  const v = Math.min(100, Math.max(0, value))
  const r = size / 2 - 9
  const c = 2 * Math.PI * r
  return (
    <div className="ring" style={{ '--sz': `${size}px` }}>
      <svg viewBox={`0 0 ${size} ${size}`}>
        <circle className="ring__track" cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth="9" />
        <circle
          className="ring__val"
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          strokeWidth="9"
          strokeDasharray={c}
          strokeDashoffset={c - (v / 100) * c}
        />
      </svg>
      <div className="ring__label">
        <span className="ring__num">{v}%</span>
        <span className="ring__cap">{caption}</span>
      </div>
    </div>
  )
}

export function Field({ label, error, hint, children }) {
  return (
    <label className="field">
      {label && <span style={{ display: 'block', fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-soft)', marginBottom: 5 }}>{label}</span>}
      {children}
      {hint && <div className="hint">{hint}</div>}
      {error && <div className="field-error">{error}</div>}
    </label>
  )
}

export function Modal({ title, onClose, children, footer, wide }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose?.()
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div className="modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
      <div className="modal" style={wide ? { maxWidth: 760 } : undefined}>
        <div className="modal-head">
          <h2 style={{ margin: 0, fontSize: '1.05rem' }}>{title}</h2>
          <button className="btn btn-ghost btn-sm" onClick={onClose} aria-label="Fermer">
            <IconX width={16} height={16} />
          </button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  )
}
