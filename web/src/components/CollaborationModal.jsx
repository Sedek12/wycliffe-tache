import { useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useToast } from '../context/ToastContext'
import { Field, Modal } from './ui'
import MemberPicker from './MemberPicker'

export default function CollaborationModal({ task, onClose, onSent }) {
  const toast = useToast()
  const [departments, setDepartments] = useState([])
  const [members, setMembers] = useState([])
  const [toDept, setToDept] = useState('')
  const [picked, setPicked] = useState([])
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/departments').then(({ data }) => {
      setDepartments(data.data.filter((d) => d.id !== task.department_id))
    })
  }, [task.department_id])

  useEffect(() => {
    setPicked([])
    if (!toDept) return setMembers([])
    api.get(`/departments/${toDept}/members`).then(({ data }) => setMembers(data.data)).catch(() => setMembers([]))
  }, [toDept])

  // On exclut les personnes déjà assignées à la tâche.
  const assignedIds = useMemo(() => new Set((task.assignees || []).map((a) => a.id)), [task])
  const selectable = useMemo(() => members.filter((m) => !assignedIds.has(m.id)), [members, assignedIds])

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    if (picked.length === 0) {
      setError('Choisissez au moins une personne.')
      return
    }
    setBusy(true)
    try {
      await api.post('/collaboration-requests', {
        task_id: task.id,
        to_department_id: toDept,
        target_user_ids: picked,
        message: message || null,
      })
      toast.success(
        picked.length > 1
          ? `${picked.length} demandes envoyées au chef du département.`
          : 'Demande envoyée au chef du département.',
      )
      onSent?.()
      onClose()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      title="Demander des collaborateurs d’un autre département"
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary" form="collab-form" disabled={busy}>
            {busy ? 'Envoi…' : `Envoyer la demande${picked.length > 1 ? 's' : ''}`}
          </button>
        </>
      }
    >
      <form id="collab-form" onSubmit={submit}>
        <div className="form-section">
          <div className="form-section__title">Tâche concernée</div>
          <p style={{ margin: 0, fontWeight: 600 }}>{task.title}</p>
          <p className="faint" style={{ margin: '2px 0 0' }}>{task.department?.name}</p>
        </div>

        <div className="form-section">
          <div className="form-section__title">Personnes demandées</div>
          <Field label={<>Département sollicité <span className="req">*</span></>}>
            <select className="select" value={toDept} onChange={(e) => setToDept(e.target.value)} required>
              <option value="">— choisir —</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </Field>

          <Field label="Personnes (1 à 3)" hint="Cochez une, deux ou trois personnes de ce département.">
            <MemberPicker
              members={selectable}
              value={picked}
              onChange={setPicked}
              roleOf={(m) => m.pivot?.role || ''}
              emptyText={toDept ? 'Aucun membre disponible.' : 'Choisissez d’abord un département.'}
              max={3}
            />
          </Field>

          <Field label="Message (facultatif)">
            <textarea
              className="textarea"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Contexte, durée du renfort attendu…"
            />
          </Field>
        </div>

        <p className="faint">
          Chaque personne rejoint la tâche seulement après validation par le chef de son département.
        </p>
        {error && <div className="field-error">{error}</div>}
      </form>
    </Modal>
  )
}
