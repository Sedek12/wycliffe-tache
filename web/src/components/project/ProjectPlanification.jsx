import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../../lib/api'
import { useToast } from '../../context/ToastContext'
import { StatusBadge, ProgressBar } from '../ui'
import { fmtDate } from '../../lib/format'
import TaskForm from '../TaskForm'
import Gantt from './Gantt'
import { IconPlus } from '../Icons'

export default function ProjectPlanification({ project, canManage, onChanged }) {
  const toast = useToast()
  const navigate = useNavigate()
  const [creatingActivity, setCreatingActivity] = useState(false)
  const [milestoneForm, setMilestoneForm] = useState({ title: '', date: '' })

  const addMilestone = async (e) => {
    e.preventDefault()
    try {
      await api.post(`/projects/${project.id}/milestones`, milestoneForm)
      toast.success('Jalon ajouté.')
      setMilestoneForm({ title: '', date: '' })
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const toggleMilestone = async (m) => {
    await api.put(`/milestones/${m.id}`, { is_reached: !m.is_reached })
    onChanged()
  }

  const removeMilestone = async (m) => {
    if (!confirm(`Supprimer le jalon « ${m.title} » ?`)) return
    await api.delete(`/milestones/${m.id}`)
    onChanged()
  }

  return (
    <>
      <div className="card card-pad">
        <div className="spread">
          <h3 style={{ margin: 0 }}>Diagramme de Gantt</h3>
        </div>
        <div style={{ marginTop: 12 }}>
          <Gantt activities={project.activities || []} milestones={project.milestones || []} />
        </div>
      </div>

      <div className="card card-pad" style={{ marginTop: 16 }}>
        <div className="spread">
          <h3 style={{ margin: 0 }}>Activités ({project.activities?.length || 0})</h3>
          {canManage && (
            <button className="btn btn-primary btn-sm" onClick={() => setCreatingActivity(true)}>
              <IconPlus width={15} height={15} /> Nouvelle activité
            </button>
          )}
        </div>

        <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 8 }}>
          {(project.activities || []).length === 0 && <p className="faint">Aucune activité pour l’instant.</p>}
          {(project.activities || []).map((a) => (
            <div key={a.id} className="card" style={{ padding: 12, boxShadow: 'none', background: 'var(--surface-2)' }}>
              <div className="spread">
                <button className="link-btn cell-strong" onClick={() => navigate(`/taches/${a.id}`)} style={{ textAlign: 'left' }}>
                  {a.title}
                </button>
                <StatusBadge status={a.status} label={a.status_label} />
              </div>
              <div className="faint" style={{ marginTop: 4 }}>
                {fmtDate(a.starts_at)} → {fmtDate(a.due_at)}
                {a.sub_tasks?.length ? ` · ${a.sub_tasks.length} sous-activité(s)` : ''}
              </div>
              <div style={{ marginTop: 8, maxWidth: 240 }}>
                <ProgressBar value={a.progress} />
              </div>
              {(a.depends_on || []).length > 0 && (
                <div className="faint" style={{ marginTop: 6, fontSize: '0.8rem' }}>
                  Dépend de : {a.depends_on.map((d) => d.title).join(', ')}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      <div className="card card-pad" style={{ marginTop: 16 }}>
        <h3 style={{ marginTop: 0 }}>Jalons ({project.milestones?.length || 0})</h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          {(project.milestones || []).length === 0 && <p className="faint">Aucun jalon.</p>}
          {(project.milestones || []).map((m) => (
            <div key={m.id} className="spread" style={{ padding: '6px 0', borderBottom: '1px solid var(--border)' }}>
              <div>
                <span style={{ fontWeight: 600 }}>{m.title}</span>
                <span className="faint" style={{ marginLeft: 8 }}>{fmtDate(m.date)}</span>
              </div>
              {canManage ? (
                <div style={{ display: 'flex', gap: 6 }}>
                  <button className={`btn btn-sm ${m.is_reached ? 'btn-blue' : 'btn-ghost'}`} onClick={() => toggleMilestone(m)}>
                    {m.is_reached ? 'Atteint ✓' : 'Marquer atteint'}
                  </button>
                  <button className="btn btn-ghost btn-sm" onClick={() => removeMilestone(m)}>Supprimer</button>
                </div>
              ) : (
                m.is_reached && <span className="badge badge-green">atteint</span>
              )}
            </div>
          ))}
        </div>

        {canManage && (
          <form onSubmit={addMilestone} className="inline-form" style={{ marginTop: 12 }}>
            <label className="field" style={{ margin: 0, minWidth: 220 }}>
              <span className="faint">Titre du jalon</span>
              <input
                className="input"
                value={milestoneForm.title}
                onChange={(e) => setMilestoneForm((f) => ({ ...f, title: e.target.value }))}
                required
              />
            </label>
            <label className="field" style={{ margin: 0 }}>
              <span className="faint">Date</span>
              <input
                className="input"
                type="date"
                value={milestoneForm.date}
                onChange={(e) => setMilestoneForm((f) => ({ ...f, date: e.target.value }))}
                required
              />
            </label>
            <button className="btn btn-primary" type="submit">
              <IconPlus width={15} height={15} /> Ajouter
            </button>
          </form>
        )}
      </div>

      {creatingActivity && (
        <TaskForm
          project={project}
          projectActivities={project.activities || []}
          onClose={() => setCreatingActivity(false)}
          onSaved={onChanged}
        />
      )}
    </>
  )
}
