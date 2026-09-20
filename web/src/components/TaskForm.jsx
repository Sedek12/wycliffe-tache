import { useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Field, Modal } from './ui'
import MemberPicker from './MemberPicker'

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  const off = d.getTimezoneOffset()
  return new Date(d.getTime() - off * 60000).toISOString().slice(0, 16)
}

// Pouvoirs que le chef peut déléguer au responsable de la tâche.
const ABILITIES = [
  { key: 'create_subtasks', label: 'Créer des sous-tâches', hint: 'Ouvrir des sous-tâches rattachées à celle-ci.' },
  { key: 'manage_team', label: 'Gérer l’équipe', hint: 'Ajouter / retirer des membres, modifier les consignes.' },
  { key: 'request_collaboration', label: 'Demander une collaboration', hint: 'Solliciter une personne d’un autre département.' },
  { key: 'change_status', label: 'Faire évoluer le statut', hint: 'Changer le statut (jusqu’à « Livrée ») et revoir les points d’avancement.' },
]

export default function TaskForm({ task, parent, project, projectActivities, onClose, onSaved }) {
  const { user, ledDepartments, isAdmin, isChefOf, isProjectManagerOf } = useAuth()
  const toast = useToast()
  const editing = Boolean(task)
  const isSubtask = Boolean(parent)
  const isProjectActivity = Boolean(project) && !isSubtask

  const [departments, setDepartments] = useState(ledDepartments)
  const [members, setMembers] = useState([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const [form, setForm] = useState({
    department_id: task?.department_id || parent?.department_id || project?.department_id || ledDepartments[0]?.id || '',
    title: task?.title || '',
    description: task?.description || '',
    starts_at: toLocalInput(task?.starts_at) || toLocalInput(Date.now()),
    due_at: toLocalInput(task?.due_at),
    is_team: task?.is_team || false,
    supervisor_id: task?.supervisor_id ? String(task.supervisor_id) : String(user?.id || ''),
  })
  const [assigneeIds, setAssigneeIds] = useState((task?.assignees || []).map((a) => a.id))
  const [instructions, setInstructions] = useState(
    Object.fromEntries((task?.assignees || []).map((a) => [a.id, a.instructions || ''])),
  )
  const [hours, setHours] = useState(
    Object.fromEntries((task?.assignees || []).map((a) => [a.id, a.estimated_hours ?? ''])),
  )
  const [dependsOn, setDependsOn] = useState((task?.depends_on || []).map((d) => d.id))
  const [abilities, setAbilities] = useState(task?.delegated_abilities || [])
  const toggleAbility = (key) =>
    setAbilities((list) => (list.includes(key) ? list.filter((k) => k !== key) : [...list, key]))

  const set = (k) => (e) =>
    setForm((f) => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  useEffect(() => {
    if (isAdmin) api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => {})
  }, [isAdmin])

  useEffect(() => {
    if (!form.department_id) return
    api
      .get(`/departments/${form.department_id}/members`)
      .then(({ data }) => {
        if (!project) return setMembers(data.data)
        // Une activité de projet peut être assignée à des membres du projet
        // qui ne sont pas formellement membres du département.
        api.get(`/projects/${project.id}/members`).then(({ data: pm }) => {
          const merged = [...data.data]
          pm.data.forEach((m) => { if (!merged.some((x) => x.id === m.id)) merged.push(m) })
          setMembers(merged)
        }).catch(() => setMembers(data.data))
      })
      .catch(() => setMembers([]))
  }, [form.department_id, project])

  const memberById = useMemo(() => Object.fromEntries(members.map((m) => [m.id, m])), [members])
  const isTeam = form.is_team || assigneeIds.length > 1
  const roleOf = (m) => m.pivot?.role || ''

  // Le chef (ou l'admin, ou le responsable managérial du projet) peut déléguer des
  // pouvoirs au responsable désigné, dès lors que ce responsable n'est pas lui-même.
  const isChefLevel = isAdmin || isChefOf(form.department_id) || (project && isProjectManagerOf(project.id))
  const supervisorIsSomeoneElse =
    form.supervisor_id && String(form.supervisor_id) !== String(user?.id || '')
  const showAbilities = isChefLevel && supervisorIsSomeoneElse

  const otherActivities = (projectActivities || []).filter((a) => a.id !== task?.id)

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    if (isTeam && !form.supervisor_id) {
      setError('Une tâche d’équipe doit avoir un responsable.')
      return
    }
    setBusy(true)
    const payload = {
      department_id: form.department_id,
      title: form.title,
      description: form.description,
      is_team: isTeam,
      supervisor_id: form.supervisor_id ? Number(form.supervisor_id) : null,
      starts_at: form.starts_at.replace('T', ' '),
      due_at: form.due_at.replace('T', ' '),
      assignees: assigneeIds.map((id) => ({
        user_id: id,
        instructions: instructions[id] || null,
        estimated_hours: hours[id] !== '' && hours[id] != null ? Number(hours[id]) : null,
      })),
    }
    if (isSubtask) payload.parent_id = parent.id
    if (isProjectActivity) payload.project_id = project.id
    if (isChefLevel) {
      payload.delegated_abilities = showAbilities ? abilities : []
    }
    try {
      const res = editing ? await api.put(`/tasks/${task.id}`, payload) : await api.post('/tasks', payload)
      const saved = res.data.data

      if (isProjectActivity) {
        const before = (task?.depends_on || []).map((d) => d.id)
        const toAdd = dependsOn.filter((id) => !before.includes(id))
        const toRemove = before.filter((id) => !dependsOn.includes(id))
        await Promise.all([
          ...toAdd.map((id) => api.post(`/tasks/${saved.id}/dependencies`, { depends_on_task_id: id })),
          ...toRemove.map((id) => api.delete(`/tasks/${saved.id}/dependencies/${id}`)),
        ])
      }

      toast.success(editing ? 'Tâche mise à jour.' : 'Tâche créée.')
      onSaved?.(saved)
      onClose()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      title={isSubtask ? 'Nouvelle sous-tâche' : editing ? 'Modifier la tâche' : 'Nouvelle tâche'}
      onClose={onClose}
      wide
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary" form="task-form" disabled={busy}>
            {busy ? 'Enregistrement…' : editing ? 'Enregistrer' : isSubtask ? 'Créer la sous-tâche' : 'Créer la tâche'}
          </button>
        </>
      }
    >
      <form id="task-form" onSubmit={submit}>
        {isSubtask && (
          <div className="form-section">
            <div className="faint" style={{ fontSize: '0.86rem' }}>
              Sous-tâche de « <strong>{parent.title}</strong> » — département {parent.department?.name || ''}.
            </div>
          </div>
        )}
        {isProjectActivity && (
          <div className="form-section">
            <div className="faint" style={{ fontSize: '0.86rem' }}>
              Activité du projet « <strong>{project.title}</strong> ».
            </div>
          </div>
        )}
        <div className="form-section">
          <div className="form-section__title">Détails</div>
          <div className="form-grid form-grid--2">
            <Field label={<>Département <span className="req">*</span></>}>
              <select className="select" value={form.department_id} onChange={set('department_id')} disabled={editing || isSubtask || isProjectActivity} required>
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
          <Field label="Description" hint="Pour une équipe, précisez ce que chaque personne doit faire.">
            <textarea className="textarea" value={form.description} onChange={set('description')} required />
          </Field>
        </div>

        <div className="form-section">
          <div className="form-section__title">Planning</div>
          <div className="form-grid form-grid--2">
            <Field label={<>Début <span className="req">*</span></>}>
              <input className="input" type="datetime-local" value={form.starts_at} onChange={set('starts_at')} required />
            </Field>
            <Field label={<>Échéance <span className="req">*</span></>}>
              <input className="input" type="datetime-local" value={form.due_at} onChange={set('due_at')} required />
            </Field>
          </div>
        </div>

        <div className="form-section">
          <div className="form-section__title">Assignés</div>
          <MemberPicker
            members={members}
            value={assigneeIds}
            onChange={setAssigneeIds}
            roleOf={roleOf}
            emptyText="Aucun membre dans ce département."
          />

          {assigneeIds.length > 0 && (
            <div className="card" style={{ background: 'var(--surface-2)', padding: 12, boxShadow: 'none', marginTop: 12 }}>
              <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>
                Consignes individuelles {isTeam ? '(équipe)' : ''}
              </strong>
              {assigneeIds.map((id) => (
                <div key={id} style={{ marginTop: 8, display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                  <div style={{ flex: 1 }}>
                    <div className="faint" style={{ marginBottom: 3 }}>{memberById[id]?.name}</div>
                    <input
                      className="input"
                      placeholder="Ce que cette personne doit faire…"
                      value={instructions[id] || ''}
                      onChange={(e) => setInstructions((m) => ({ ...m, [id]: e.target.value }))}
                    />
                  </div>
                  {isProjectActivity && (
                    <div style={{ width: 110 }}>
                      <div className="faint" style={{ marginBottom: 3 }}>Charge (h)</div>
                      <input
                        className="input"
                        type="number"
                        min="0"
                        step="0.5"
                        value={hours[id] ?? ''}
                        onChange={(e) => setHours((m) => ({ ...m, [id]: e.target.value }))}
                      />
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}

          <label style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12, fontSize: '0.9rem' }}>
            <input type="checkbox" checked={isTeam} onChange={set('is_team')} disabled={assigneeIds.length > 1} />
            Tâche d’équipe (livrable commun)
          </label>
        </div>

        {isProjectActivity && otherActivities.length > 0 && (
          <div className="form-section">
            <div className="form-section__title">Dépendances</div>
            <Field label="Dépend de" hint="Cette activité ne pourra démarrer que lorsque les activités cochées seront validées.">
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                {otherActivities.map((a) => (
                  <label key={a.id} className="chip-check">
                    <input
                      type="checkbox"
                      checked={dependsOn.includes(a.id)}
                      onChange={() =>
                        setDependsOn((list) => (list.includes(a.id) ? list.filter((x) => x !== a.id) : [...list, a.id]))
                      }
                    />
                    {a.title}
                  </label>
                ))}
              </div>
            </Field>
          </div>
        )}

        <div className="form-section">
          <div className="form-section__title">Supervision</div>
          <Field
            label={<>Responsable de la tâche {isTeam && <span className="req">*</span>}</>}
            hint="Le chef, ou la personne qu’il délègue pour suivre l’avancement. Obligatoire pour une équipe."
          >
            <select
              className="select"
              value={form.supervisor_id}
              onChange={set('supervisor_id')}
              required={isTeam}
            >
              <option value="">— aucun (le chef) —</option>
              {members.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name}
                  {m.id === user?.id ? ' (moi)' : ''}
                  {m.pivot?.role === 'chef' ? ' — chef' : ''}
                </option>
              ))}
            </select>
          </Field>

          {showAbilities && (
            <div className="card" style={{ background: 'var(--surface-2)', padding: 12, boxShadow: 'none', marginTop: 12 }}>
              <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>
                Pouvoirs délégués à ce responsable
              </strong>
              <p className="faint" style={{ margin: '4px 0 8px', fontSize: '0.8rem' }}>
                Par défaut, le responsable ne fait que suivre l’avancement. Cochez ce que vous lui confiez.
                Rester non délégable : noter/évaluer, valider, supprimer.
              </p>
              {ABILITIES.map((a) => (
                <label key={a.key} style={{ display: 'flex', gap: 8, alignItems: 'flex-start', marginTop: 8, fontSize: '0.88rem' }}>
                  <input
                    type="checkbox"
                    checked={abilities.includes(a.key)}
                    onChange={() => toggleAbility(a.key)}
                    style={{ marginTop: 3 }}
                  />
                  <span>
                    {a.label}
                    <span className="faint" style={{ display: 'block', fontSize: '0.78rem' }}>{a.hint}</span>
                  </span>
                </label>
              ))}
            </div>
          )}
        </div>

        {error && <div className="field-error">{error}</div>}
      </form>
    </Modal>
  )
}
