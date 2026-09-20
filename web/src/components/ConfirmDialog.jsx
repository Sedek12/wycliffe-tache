import { useState } from 'react'
import { Field, Modal } from './ui'

/**
 * Fenêtre de vérification avant une action sensible (supprimer / annuler…).
 *
 * props :
 *  - title, message            : intitulé + explication
 *  - confirmLabel              : texte du bouton de confirmation
 *  - danger                    : bouton rouge (défaut true)
 *  - requireText               : si fourni, il faut le retaper à l'identique pour activer
 *  - reason                    : { label, placeholder, required } → affiche un motif renvoyé à onConfirm
 *  - busy                      : désactive les boutons pendant l'appel
 *  - onConfirm(reason)         : action confirmée
 *  - onClose()
 */
export default function ConfirmDialog({
  title,
  message,
  confirmLabel = 'Confirmer',
  danger = true,
  requireText,
  reason,
  busy = false,
  onConfirm,
  onClose,
}) {
  const [typed, setTyped] = useState('')
  const [why, setWhy] = useState('')

  const textOk = !requireText || typed.trim() === requireText.trim()
  const reasonOk = !reason?.required || why.trim().length > 0
  const canConfirm = textOk && reasonOk && !busy

  return (
    <Modal
      title={title}
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose} disabled={busy}>
            Retour
          </button>
          <button
            className={danger ? 'btn btn-danger' : 'btn btn-primary'}
            disabled={!canConfirm}
            onClick={() => onConfirm(why.trim() || null)}
          >
            {busy ? '…' : confirmLabel}
          </button>
        </>
      }
    >
      {message && <p style={{ marginTop: 0, lineHeight: 1.6 }}>{message}</p>}

      {reason && (
        <Field label={reason.label || 'Motif'}>
          <textarea
            className="textarea"
            value={why}
            placeholder={reason.placeholder || ''}
            onChange={(e) => setWhy(e.target.value)}
          />
        </Field>
      )}

      {requireText && (
        <Field
          label={
            <>
              Pour confirmer, saisissez <strong>{requireText}</strong>
            </>
          }
        >
          <input className="input" value={typed} onChange={(e) => setTyped(e.target.value)} autoFocus />
        </Field>
      )}
    </Modal>
  )
}
