import { useState } from 'react'
import api, { errorMessage } from '../../lib/api'
import { useToast } from '../../context/ToastContext'
import { StatusBadge, ProgressBar } from '../ui'
import { fmtDate } from '../../lib/format'
import ProjectForm from '../ProjectForm'

export default function ProjectCadrage({ project, canManage, onChanged }) {
  const toast = useToast()
  const [editing, setEditing] = useState(false)

  const changeStatus = async (status) => {
    try {
      await api.put(`/projects/${project.id}`, { status })
      toast.success('Statut du projet mis à jour.')
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  return (
    <div className="card card-pad">
      <div className="spread" style={{ alignItems: 'flex-start' }}>
        <div>
          <StatusBadge status={project.status} label={project.status_label} />
          <p style={{ marginTop: 10 }}>{project.description || <span className="faint">Aucune description.</span>}</p>
        </div>
        {canManage && (
          <button className="btn btn-ghost btn-sm" onClick={() => setEditing(true)}>Modifier</button>
        )}
      </div>

      <div className="form-grid form-grid--2" style={{ marginTop: 16 }}>
        <div>
          <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Effet</strong>
          <p className="faint" style={{ marginTop: 4 }}>{project.effet || '—'}</p>
        </div>
        <div>
          <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Extrant</strong>
          <p className="faint" style={{ marginTop: 4 }}>{project.extrant || '—'}</p>
        </div>
      </div>

      <div className="form-grid form-grid--2" style={{ marginTop: 8 }}>
        <div>
          <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Période</strong>
          <p className="faint" style={{ marginTop: 4 }}>{fmtDate(project.starts_at)} → {fmtDate(project.due_at)}</p>
        </div>
        <div>
          <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Parties prenantes</strong>
          <p className="faint" style={{ marginTop: 4 }}>
            {(project.parties_prenantes || []).length ? project.parties_prenantes.join(', ') : '—'}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Avancement</strong>
        <div style={{ marginTop: 6, maxWidth: 320 }}>
          <ProgressBar value={project.progress} />
        </div>
      </div>

      {canManage && project.allowed_next?.length > 0 && (
        <div style={{ marginTop: 16 }}>
          <strong style={{ fontSize: '0.82rem', color: 'var(--text-soft)' }}>Changer le statut</strong>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            {project.allowed_next.map((s) => (
              <button key={s.value} className="btn btn-sm btn-blue" onClick={() => changeStatus(s.value)}>
                → {s.label}
              </button>
            ))}
          </div>
        </div>
      )}

      {editing && (
        <ProjectForm
          project={project}
          onClose={() => setEditing(false)}
          onSaved={() => { setEditing(false); onChanged() }}
        />
      )}
    </div>
  )
}
