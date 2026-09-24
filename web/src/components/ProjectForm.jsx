import { useEffect, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Field, Modal } from './ui'

export default function ProjectForm({ project, onClose, onSaved }) {
  const { ledDepartments, isAdmin, isDirecteur, managedProjects } = useAuth()
  const toast = useToast()
  const editing = Boolean(project?.id)

  // Un chef voit ses départements. L'admin et le directeur peuvent créer un projet
  // dans n'importe quel département. Un responsable de projet (coordonnateur,
  // facilitateur, moniteur) sans être chef n'a pas de "ledDepartments" propre non plus
  // — on charge alors la liste complète pour qu'il puisse choisir le département.
  const [departments, setDepartments] = useState(ledDepartments)
  useEffect(() => {
    if (isAdmin || isDirecteur || (ledDepartments.length === 0 && managedProjects.length > 0)) {
      api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => {})
    }
  }, [isAdmin, isDirecteur, ledDepartments, managedProjects])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const [form, setForm] = useState({
    department_id: project?.department_id || departments[0]?.id || '',
    title: project?.title || '',
    description: project?.description || '',
    effet: project?.effet || '',
    extrant: project?.extrant || '',
    budget_previsionnel: project?.budget_previsionnel ?? '',
    starts_at: project?.starts_at ? project.starts_at.slice(0, 10) : '',
    due_at: project?.due_at ? project.due_at.slice(0, 10) : '',
  })
  const [parties, setParties] = useState((project?.parties_prenantes || []).join('\n'))

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    setBusy(true)
    const payload = {
      title: form.title,
      description: form.description || null,
      effet: form.effet || null,
      extrant: form.extrant || null,
      budget_previsionnel: form.budget_previsionnel !== '' ? Number(form.budget_previsionnel) : null,
      parties_prenantes: parties.split('\n').map((s) => s.trim()).filter(Boolean),
      starts_at: form.starts_at || null,
      due_at: form.due_at || null,
    }
    if (!editing) payload.department_id = form.department_id

    try {
      const res = editing
        ? await api.put(`/projects/${project.id}`, payload)
        : await api.post('/projects', payload)
      toast.success(editing ? 'Projet mis à jour.' : 'Projet créé.')
      onSaved?.(res.data.data)
      onClose()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      title={editing ? 'Modifier le projet' : 'Nouveau projet'}
      onClose={onClose}
      wide
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary" form="project-form" disabled={busy}>
            {busy ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer le projet'}
          </button>
        </>
      }
    >
      <form id="project-form" onSubmit={submit}>
        <div className="form-section">
          <div className="form-section__title">Cadrage</div>
          <div className="form-grid form-grid--2">
            <Field label={<>Département <span className="req">*</span></>}>
              <select className="select" value={form.department_id} onChange={set('department_id')} disabled={editing} required>
                <option value="">— choisir —</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </Field>
            <Field label={<>Titre <span className="req">*</span></>}>
              <input className="input" value={form.title} onChange={set('title')} required />
            </Field>
          </div>
          <Field label="Description">
            <textarea className="textarea" value={form.description} onChange={set('description')} />
          </Field>
          <div className="form-grid form-grid--2">
            <Field label="Effet" hint="Impact visé (logique PTAB).">
              <textarea className="textarea" value={form.effet} onChange={set('effet')} />
            </Field>
            <Field label="Extrant" hint="Résultat livré (logique PTAB).">
              <textarea className="textarea" value={form.extrant} onChange={set('extrant')} />
            </Field>
          </div>
          <Field label="Parties prenantes" hint="Une par ligne.">
            <textarea className="textarea" value={parties} onChange={(e) => setParties(e.target.value)} />
          </Field>
        </div>

        <div className="form-section">
          <div className="form-section__title">Planning &amp; budget</div>
          <div className="form-grid form-grid--2">
            <Field label="Début">
              <input className="input" type="date" value={form.starts_at} onChange={set('starts_at')} />
            </Field>
            <Field label="Échéance">
              <input className="input" type="date" value={form.due_at} onChange={set('due_at')} />
            </Field>
          </div>
          <Field label="Budget prévisionnel (FCFA)">
            <input className="input" type="number" min="0" step="0.01" value={form.budget_previsionnel} onChange={set('budget_previsionnel')} />
          </Field>
        </div>

        {error && <div className="field-error">{error}</div>}
      </form>
    </Modal>
  )
}
