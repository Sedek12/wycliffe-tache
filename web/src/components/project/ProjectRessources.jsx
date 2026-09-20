import { useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../../lib/api'
import { useMeta } from '../../context/MetaContext'
import { useToast } from '../../context/ToastContext'
import JqDataTable from '../DataTable'
import { rowActions } from '../../lib/rowActions'
import { IconPlus } from '../Icons'

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export default function ProjectRessources({ project, canManage, onChanged }) {
  const { meta } = useMeta()
  const toast = useToast()
  const [allUsers, setAllUsers] = useState([])
  const [add, setAdd] = useState({ user_id: '', role: 'moniteur' })

  useEffect(() => {
    if (canManage) api.get('/users', { params: { per_page: 300, active_only: 1 } }).then(({ data }) => setAllUsers(data.data))
  }, [canManage])

  const memberIds = new Set((project.members || []).map((m) => m.id))
  const roleLabel = (v) => meta?.project_roles?.find((r) => r.value === v)?.label || v

  const upsertMember = async (user_id, role) => {
    try {
      await api.post(`/projects/${project.id}/members`, { user_id, role })
      toast.success('Membre du projet enregistré.')
      setAdd({ user_id: '', role: 'moniteur' })
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const removeMember = async (uid) => {
    if (!confirm('Retirer ce membre du projet ?')) return
    await api.delete(`/projects/${project.id}/members/${uid}`)
    onChanged()
  }

  // Charge de travail : somme des heures estimées par membre, sur les activités du projet.
  const workload = useMemo(() => {
    const totals = {}
    ;(project.activities || []).forEach((a) => {
      ;(a.assignees || []).forEach((u) => {
        if (u.estimated_hours) totals[u.id] = (totals[u.id] || 0) + Number(u.estimated_hours)
      })
    })
    return totals
  }, [project.activities])

  const memberColumns = [
    {
      title: 'Membre',
      data: null,
      render: (_d, _t, r) => `<span class="cell-strong">${esc(r.name)}</span><div class="faint">${esc(r.email)}</div>`,
    },
    {
      title: 'Rôle sur le projet',
      data: null,
      render: (_d, _t, r) => `<span class="badge badge-blue">${esc(roleLabel(r.pivot?.role || r.role))}</span>`,
    },
    {
      title: 'Charge estimée',
      data: null,
      className: 'dt-center',
      render: (_d, _t, r) => (workload[r.id] ? `${workload[r.id]} h` : '—'),
    },
    canManage && {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: () => rowActions([{ act: 'delete', title: 'Retirer du projet' }]),
    },
  ].filter(Boolean)

  return (
    <>
      <JqDataTable
        columns={memberColumns}
        rows={project.members || []}
        onAction={(act, row) => act === 'delete' && removeMember(row.id)}
      />

      {canManage && (
        <div className="card card-pad" style={{ marginTop: 16 }}>
          <h3>Ajouter un membre au projet</h3>
          <div className="inline-form">
            <label className="field" style={{ margin: 0, minWidth: 220 }}>
              <span className="faint">Personne</span>
              <select className="select" value={add.user_id} onChange={(e) => setAdd((a) => ({ ...a, user_id: e.target.value }))}>
                <option value="">— choisir —</option>
                {allUsers.filter((u) => !memberIds.has(u.id)).map((u) => (
                  <option key={u.id} value={u.id}>{u.name}</option>
                ))}
              </select>
            </label>
            <label className="field" style={{ margin: 0, minWidth: 220 }}>
              <span className="faint">Rôle sur le projet</span>
              <select className="select" value={add.role} onChange={(e) => setAdd((a) => ({ ...a, role: e.target.value }))}>
                {meta?.project_roles?.map((r) => (
                  <option key={r.value} value={r.value}>{r.label}</option>
                ))}
              </select>
            </label>
            <button className="btn btn-primary" disabled={!add.user_id} onClick={() => upsertMember(add.user_id, add.role)}>
              <IconPlus width={15} height={15} /> Ajouter
            </button>
          </div>
        </div>
      )}
    </>
  )
}
