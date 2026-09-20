import { useEffect, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useToast } from '../context/ToastContext'
import { Field, Modal, Spinner } from './ui'

export default function SettingsModal({ onClose }) {
  const toast = useToast()
  const [settings, setSettings] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get('/settings').then(({ data }) => setSettings(data)).catch(() => setSettings(false))
  }, [])

  const setFiles = (k, v) => setSettings((s) => ({ ...s, files: { ...s.files, [k]: v } }))

  const save = async (e) => {
    e.preventDefault()
    setBusy(true)
    try {
      const payload = {
        files: {
          max_size_mb: Number(settings.files.max_size_mb),
          allowed_extensions: settings.files.allowed_extensions,
          retention_days: Number(settings.files.retention_days),
        },
        reminders: {
          days_before_due: settings.reminders.days_before_due.map(Number),
        },
      }
      const { data } = await api.put('/settings', payload)
      setSettings(data)
      toast.success('Paramètres enregistrés.')
      onClose()
    } catch (err) {
      toast.error(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      title="Paramètres de la plateforme"
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Fermer</button>
          <button className="btn btn-primary" form="settings-form" disabled={busy || !settings}>
            {busy ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </>
      }
    >
      {settings === false ? (
        <p className="muted">Accès refusé.</p>
      ) : !settings ? (
        <Spinner />
      ) : (
        <form id="settings-form" onSubmit={save}>
          <h3 style={{ fontSize: '0.95rem' }}>Livrables</h3>
          <div className="row">
            <Field label="Taille maximale (Mo)">
              <input className="input" type="number" min="1" value={settings.files.max_size_mb} onChange={(e) => setFiles('max_size_mb', e.target.value)} />
            </Field>
            <Field label="Conservation (jours, 0 = illimité)">
              <input className="input" type="number" min="0" value={settings.files.retention_days} onChange={(e) => setFiles('retention_days', e.target.value)} />
            </Field>
          </div>
          <Field label="Extensions autorisées (séparées par des virgules)">
            <input
              className="input"
              value={settings.files.allowed_extensions.join(', ')}
              onChange={(e) => setFiles('allowed_extensions', e.target.value.split(',').map((x) => x.trim().toLowerCase()).filter(Boolean))}
            />
          </Field>

          <div className="sep" />
          <h3 style={{ fontSize: '0.95rem' }}>Rappels d’échéance</h3>
          <Field label="Jours avant l’échéance" hint="Ex. : 3, 1, 0 pour J-3, J-1 et le jour J.">
            <input
              className="input"
              value={settings.reminders.days_before_due.join(', ')}
              onChange={(e) =>
                setSettings((s) => ({
                  ...s,
                  reminders: { days_before_due: e.target.value.split(',').map((x) => x.trim()).filter((x) => x !== '') },
                }))
              }
            />
          </Field>
        </form>
      )}
    </Modal>
  )
}
