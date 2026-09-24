import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useMeta } from '../context/MetaContext'
import { fmtDate, STATUS_BADGE } from '../lib/format'
import { Avatar, EmptyState, ProgressBar, Spinner } from '../components/ui'
import PageHeader from '../components/PageHeader'
import FilterBar from '../components/FilterBar'
import JqDataTable from '../components/DataTable'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { IconPlus } from '../components/Icons'
import { useToast } from '../context/ToastContext'
import TaskForm from '../components/TaskForm'
import ConfirmDialog from '../components/ConfirmDialog'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }
const KANBAN_ORDER = ['brouillon', 'assignee', 'en_cours', 'livree', 'a_refaire', 'validee']

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
const initials = (name = '') =>
  name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || 'U'

export default function Tasks() {
  const { user, isChef, isAdmin, isDirecteur, isChefOf, isProjectManagerOf, ledDepartments } = useAuth()
  const { meta, labelOf } = useMeta()
  const navigate = useNavigate()
  const toast = useToast()

  const [tasks, setTasks] = useState(null)
  const [departments, setDepartments] = useState([])
  const [view, setView] = useState('list')
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState(null)   // tâche en cours de modification
  const [pending, setPending] = useState(null)   // { kind:'delete'|'cancel', task }
  const [busy, setBusy] = useState(false)

  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')
  const [dept, setDept] = useState('')
  const [scope, setScope] = useState('')

  const load = useCallback(() => {
    setTasks(null)
    api.get('/tasks', { params: { per_page: 300 } }).then(({ data }) => setTasks(data.data)).catch(() => setTasks([]))
  }, [])

  useEffect(() => {
    load()
    api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => {})
  }, [load])

  const rows = useMemo(() => {
    if (!tasks) return []
    return tasks.filter((t) => {
      if (status && t.status !== status) return false
      if (dept && String(t.department_id) !== String(dept)) return false
      if (scope === 'mine' && !(t.assignees || []).some((a) => a.id === user?.id)) return false
      if (scope === 'overdue' && !t.is_overdue) return false
      return matchQuery(query, t.title, t.department?.name, (t.assignees || []).map((a) => a.name))
    })
  }, [tasks, status, dept, scope, query, user])

  // Aligné sur TaskPolicy côté API : admin et directeur ont un accès total (before()),
  // sinon il faut être chef d'au moins un département.
  const canCreate = isAdmin || isDirecteur || (isChef && ledDepartments.length > 0)

  // Droits par ligne (cohérents avec TaskPolicy::isChefLevel côté API).
  const chefLevel = (t) =>
    isAdmin
    || isDirecteur
    || isChefOf(t.department_id)
    || (t.project_id && isProjectManagerOf(t.project_id))
  const supAbility = (t, key) =>
    t.supervisor_id === user?.id && (t.delegated_abilities || []).includes(key)
  const canEditTask = (t) =>
    t.status !== 'validee' && (chefLevel(t) || supAbility(t, 'manage_team'))
  const canDeleteTask = (t) => chefLevel(t)
  const canCancelTask = (t) =>
    (chefLevel(t) || supAbility(t, 'change_status')) &&
    (t.allowed_next || []).some((s) => s.value === 'annulee')

  const columns = [
    {
      title: 'Tâche',
      data: 'title',
      render: (v, _t, r) =>
        `<span class="cell-strong">${esc(v)}</span>` +
        (r.is_team ? ' <span class="badge badge-grey">Équipe</span>' : '') +
        (r.parent ? ` <span class="badge badge-blue" title="Sous-tâche de ${esc(r.parent.title)}">↳ ${esc(r.parent.title)}</span>` : ''),
    },
    { title: 'Département', data: null, render: (_d, _t, r) => `<span class="faint">${esc(r.department?.name || '—')}</span>` },
    {
      title: 'Assignés',
      data: null,
      orderable: false,
      render: (_d, _t, r) =>
        (r.assignees || []).slice(0, 4).map((a) => `<span class="dt-avatar" title="${esc(a.name)}">${esc(initials(a.name))}</span>`).join('') || '—',
    },
    {
      title: 'Échéance',
      data: 'due_at',
      render: (v, _t, r) =>
        r.is_overdue ? `<span class="badge badge-red">${fmtDate(v)}</span>` : `<span style="white-space:nowrap">${fmtDate(v)}</span>`,
    },
    {
      title: 'Avancement',
      data: 'progress',
      render: (v) => {
        const p = v || 0
        return `<div class="dt-progress"><div class="progress"><span style="width:${p}%"></span></div><span class="faint">${p}%</span></div>`
      },
    },
    {
      title: 'Statut',
      data: 'status',
      render: (v, _t, r) => `<span class="badge ${STATUS_BADGE[v] || 'badge-grey'}">${esc(r.status_label || v)}</span>`,
    },
    {
      title: '',
      data: null,
      orderable: false,
      searchable: false,
      render: (_d, _t, r) =>
        rowActions([
          { act: 'view' },
          canEditTask(r) && { act: 'edit' },
          canCancelTask(r) && { act: 'cancel', icon: 'x', title: 'Annuler la tâche' },
          canDeleteTask(r) && { act: 'delete' },
        ]),
    },
  ]

  const onAction = (act, row) => {
    if (act === 'view') navigate(`/taches/${row.id}`)
    if (act === 'edit') setEditing(row)
    if (act === 'cancel') setPending({ kind: 'cancel', task: row })
    if (act === 'delete') setPending({ kind: 'delete', task: row })
  }

  const runConfirm = async (reason) => {
    if (!pending) return
    setBusy(true)
    try {
      if (pending.kind === 'delete') {
        await api.delete(`/tasks/${pending.task.id}`, { data: { reason } })
        toast.success('Tâche supprimée.')
      } else {
        await api.post(`/tasks/${pending.task.id}/status`, { status: 'annulee', note: reason })
        toast.success('Tâche annulée.')
      }
      setPending(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader
        title="Tâches"
        subtitle="Suivi des activités par département : avancement, livrables, échéances."
        actions={
          <>
            <div className="seg">
              <button className={view === 'list' ? 'is-on' : ''} onClick={() => setView('list')}>Liste</button>
              <button className={view === 'kanban' ? 'is-on' : ''} onClick={() => setView('kanban')}>Kanban</button>
            </div>
            {canCreate && (
              <button className="btn btn-primary" onClick={() => setShowForm(true)}>
                <IconPlus width={16} height={16} /> Nouvelle tâche
              </button>
            )}
          </>
        }
      />

      <FilterBar
        query={query}
        onQuery={setQuery}
        placeholder="Rechercher une tâche, un assigné…"
        filters={[
          {
            key: 'status',
            label: 'Statut',
            value: status,
            onChange: setStatus,
            allLabel: 'Tous les statuts',
            options: (meta?.task_statuses || []).map((s) => ({ value: s.value, label: s.label })),
          },
          {
            key: 'dept',
            label: 'Département',
            value: dept,
            onChange: setDept,
            allLabel: 'Tous les départements',
            options: departments.map((d) => ({ value: String(d.id), label: d.name })),
          },
          {
            key: 'scope',
            label: 'Périmètre',
            value: scope,
            onChange: setScope,
            allLabel: 'Toutes',
            options: [
              { value: 'mine', label: 'Mes tâches' },
              { value: 'overdue', label: 'En retard' },
            ],
          },
        ]}
        count={rows.length}
        total={tasks?.length ?? 0}
        resultNoun="tâche"
      />

      {tasks === null ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <EmptyState title="Aucune tâche" hint="Ajustez les filtres ou créez une tâche." />
      ) : view === 'list' ? (
        <JqDataTable columns={columns} rows={rows} onAction={onAction} options={{ order: [], ...HIDE_DT_SEARCH }} />
      ) : (
        <div className="kanban">
          {KANBAN_ORDER.map((s) => {
            const col = rows.filter((t) => t.status === s)
            return (
              <div className="kanban-col" key={s}>
                <h3>
                  {labelOf('task_statuses', s)} <span>{col.length}</span>
                </h3>
                {col.map((t) => (
                  <Link to={`/taches/${t.id}`} key={t.id} className="kanban-card" style={{ display: 'block' }}>
                    <div className="t">{t.title}</div>
                    <div className="faint">{t.department?.name}</div>
                    <div style={{ margin: '6px 0' }}>
                      <ProgressBar value={t.progress || 0} />
                    </div>
                    <div className="spread">
                      <span className={`faint ${t.is_overdue ? 'badge badge-red' : ''}`}>{fmtDate(t.due_at)}</span>
                      <div style={{ display: 'flex' }}>
                        {(t.assignees || []).slice(0, 3).map((a) => (
                          <span key={a.id} style={{ marginRight: -8 }}>
                            <Avatar user={a} size={22} />
                          </span>
                        ))}
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            )
          })}
        </div>
      )}

      {showForm && <TaskForm onClose={() => setShowForm(false)} onSaved={load} />}

      {editing && (
        <TaskForm
          task={editing}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); load() }}
        />
      )}

      {pending?.kind === 'delete' && (
        <ConfirmDialog
          title="Supprimer la tâche"
          message={
            <>
              La tâche « <strong>{pending.task.title}</strong> » et tout son historique (avancement,
              livrables, évaluation) seront définitivement supprimés. Cette action est irréversible.
            </>
          }
          confirmLabel="Supprimer définitivement"
          reason={{ label: 'Motif de la suppression', placeholder: 'Expliquez pourquoi…', required: true }}
          busy={busy}
          onConfirm={runConfirm}
          onClose={() => setPending(null)}
        />
      )}

      {pending?.kind === 'cancel' && (
        <ConfirmDialog
          title="Annuler la tâche"
          message={
            <>
              La tâche « <strong>{pending.task.title}</strong> » passera au statut « Annulée ». Elle
              restera consultable mais ne sera plus suivie.
            </>
          }
          confirmLabel="Annuler la tâche"
          reason={{ label: 'Motif de l’annulation', placeholder: 'Expliquez pourquoi…', required: true }}
          busy={busy}
          onConfirm={runConfirm}
          onClose={() => setPending(null)}
        />
      )}
    </>
  )
}
