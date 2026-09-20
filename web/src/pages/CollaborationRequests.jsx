import { useCallback, useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Avatar, EmptyState, Field, Modal, Spinner } from '../components/ui'
import PageHeader from '../components/PageHeader'
import FilterBar from '../components/FilterBar'
import JqDataTable from '../components/DataTable'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { fmtDate } from '../lib/format'
import { IconCheck } from '../components/Icons'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }
const STATUS = {
  en_attente: ['badge-amber', 'En attente'],
  acceptee: ['badge-green', 'Acceptée'],
  refusee: ['badge-red', 'Refusée'],
}
const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export default function CollaborationRequests() {
  const { user, isChefOf } = useAuth()
  const toast = useToast()
  const [items, setItems] = useState(null)
  const [openGroup, setOpenGroup] = useState(null)
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')
  const [box, setBox] = useState('')

  const load = useCallback(() => {
    api
      .get('/collaboration-requests', { params: { per_page: 300 } })
      .then(({ data }) => setItems(data.data))
      .catch(() => setItems([]))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const canRespond = useCallback((r) => r.status === 'en_attente' && isChefOf(r.to_department_id), [isChefOf])
  const toHandle = useMemo(() => (items || []).filter(canRespond).length, [items, canRespond])

  // Regroupe les demandes par tâche.
  const groups = useMemo(() => {
    if (!items) return []
    const filtered = items.filter((r) => {
      if (status && r.status !== status) return false
      if (box === 'incoming' && !isChefOf(r.to_department_id)) return false
      if (box === 'outgoing' && r.requested_by !== user?.id) return false
      if (box === 'todo' && !canRespond(r)) return false
      return matchQuery(query, r.target_user?.name, r.task?.title, r.from_department?.name, r.requester?.name)
    })

    const map = new Map()
    for (const r of filtered) {
      const key = r.task_id
      if (!map.has(key)) {
        map.set(key, {
          id: key,
          task_id: r.task_id,
          task: r.task,
          from_department: r.from_department,
          to_department: r.to_department,
          requester: r.requester,
          message: r.message,
          created_at: r.created_at,
          requests: [],
        })
      }
      const g = map.get(key)
      g.requests.push(r)
      if (r.created_at > g.created_at) g.created_at = r.created_at
    }
    return [...map.values()]
  }, [items, status, box, query, user, isChefOf, canRespond])

  const groupStatus = (g) => {
    if (g.requests.some((r) => canRespond(r))) return ['badge-amber', 'à traiter']
    const st = new Set(g.requests.map((r) => r.status))
    if (st.size === 1) return STATUS[[...st][0]] || ['badge-grey', [...st][0]]
    return ['badge-grey', 'traité']
  }

  const respond = async (reqId, decision, note) => {
    try {
      await api.post(`/collaboration-requests/${reqId}/respond`, { decision, response_note: note || null })
      toast.success(decision === 'accept' ? 'Demande acceptée.' : 'Demande refusée.')
      await load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const cancel = async (reqId) => {
    if (!confirm('Annuler cette demande ?')) return
    try {
      await api.delete(`/collaboration-requests/${reqId}`)
      toast.success('Demande annulée.')
      await load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const columns = [
    {
      title: 'Département demandeur',
      data: null,
      render: (_d, _t, g) => `<span class="cell-strong">${esc(g.from_department?.name)}</span>`,
    },
    { title: 'Tâche', data: null, render: (_d, _t, g) => esc(g.task?.title || '—') },
    {
      title: 'Personnes',
      data: null,
      className: 'dt-center',
      render: (_d, _t, g) => `<span class="badge badge-blue">${g.requests.length}</span>`,
    },
    { title: 'Demandé par', data: null, render: (_d, _t, g) => `<span class="faint">${esc(g.requester?.name)}</span>` },
    { title: 'Date', data: 'created_at', render: (v) => fmtDate(v) },
    {
      title: 'Statut',
      data: null,
      render: (_d, _t, g) => {
        const [cls, label] = groupStatus(g)
        return `<span class="badge ${cls}">${label}</span>`
      },
    },
    { title: '', data: null, orderable: false, searchable: false, render: () => rowActions([{ act: 'view', title: 'Ouvrir la demande' }]) },
  ]

  const onAction = (act, g) => {
    if (act === 'view') setOpenGroup(g.task_id)
  }

  if (items === null) return <Spinner />

  const current = openGroup != null ? groups.find((g) => g.task_id === openGroup) : null

  return (
    <>
      <PageHeader
        title="Demandes de collaboration"
        subtitle="Prêt d’un collaborateur entre départements — le chef sollicité accepte ou refuse."
        actions={
          toHandle > 0 && (
            <button className="btn btn-blue" onClick={() => setBox('todo')}>
              {toHandle} à traiter
            </button>
          )
        }
      />

      <FilterBar
        query={query}
        onQuery={setQuery}
        placeholder="Rechercher une tâche, un département, une personne…"
        filters={[
          {
            key: 'box',
            label: 'Boîte',
            value: box,
            onChange: setBox,
            allLabel: 'Toutes',
            options: [
              { value: 'todo', label: 'À traiter' },
              { value: 'incoming', label: 'Reçues (mon département)' },
              { value: 'outgoing', label: 'Envoyées par moi' },
            ],
          },
          {
            key: 'status',
            label: 'Statut',
            value: status,
            onChange: setStatus,
            allLabel: 'Tous les statuts',
            options: [
              { value: 'en_attente', label: 'En attente' },
              { value: 'acceptee', label: 'Acceptée' },
              { value: 'refusee', label: 'Refusée' },
            ],
          },
        ]}
        count={groups.length}
        total={groups.length}
        resultNoun="demande"
      />

      {groups.length === 0 ? (
        <EmptyState title="Aucune demande" hint="Les demandes inter-départements apparaîtront ici." />
      ) : (
        <JqDataTable columns={columns} rows={groups} onAction={onAction} options={{ order: [[4, 'desc']], ...HIDE_DT_SEARCH }} />
      )}

      {current && (
        <GroupModal
          group={current}
          canRespond={canRespond}
          isRequester={(r) => r.requested_by === user?.id}
          onRespond={respond}
          onCancel={cancel}
          onClose={() => setOpenGroup(null)}
        />
      )}
    </>
  )
}

function GroupModal({ group, canRespond, isRequester, onRespond, onCancel, onClose }) {
  const [task, setTask] = useState(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get(`/tasks/${group.task_id}`).then(({ data }) => setTask(data.data)).catch(() => setTask(false))
  }, [group.task_id])

  const pending = group.requests.filter((r) => canRespond(r))

  const act = async (fn) => {
    setBusy(true)
    await fn()
    setBusy(false)
  }

  return (
    <Modal
      title={group.task?.title || 'Demande de collaboration'}
      onClose={onClose}
      wide
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Fermer</button>
          {pending.length > 1 && (
            <>
              <button
                className="btn btn-danger"
                disabled={busy}
                onClick={() => act(() => Promise.all(pending.map((r) => onRespond(r.id, 'reject', note))))}
              >
                Tout refuser
              </button>
              <button
                className="btn btn-primary"
                disabled={busy}
                onClick={() => act(() => Promise.all(pending.map((r) => onRespond(r.id, 'accept', note))))}
              >
                Tout accepter
              </button>
            </>
          )}
        </>
      }
    >
      <div className="form-section">
        <div className="form-section__title">Demande</div>
        <dl className="kv">
          <dt>Département demandeur</dt><dd>{group.from_department?.name}</dd>
          <dt>Département sollicité</dt><dd>{group.to_department?.name}</dd>
          <dt>Demandé par</dt><dd>{group.requester?.name}</dd>
        </dl>
        {group.message && <p style={{ marginTop: 10 }}>« {group.message} »</p>}
      </div>

      <div className="form-section">
        <div className="form-section__title">Membres actuels de la tâche</div>
        {task === null ? (
          <Spinner label=" " />
        ) : task === false ? (
          <p className="faint">Indisponible.</p>
        ) : (task.assignees || []).length === 0 ? (
          <p className="faint">Aucun membre pour l’instant.</p>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {task.assignees.map((a) => (
              <div key={a.id} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Avatar user={a} size={28} />
                <span style={{ fontWeight: 600 }}>{a.name}</span>
                {a.is_external && <span className="badge badge-grey">externe</span>}
                {a.id === task.supervisor_id && <span className="badge badge-blue">responsable</span>}
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="form-section">
        <div className="form-section__title">Personnes demandées ({group.requests.length})</div>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          {group.requests.map((r) => {
            const [cls, label] = STATUS[r.status] || ['badge-grey', r.status]
            return (
              <div key={r.id} className="member-row" style={{ alignItems: 'center' }}>
                <Avatar user={{ name: r.target_user?.name }} size={30} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600 }}>{r.target_user?.name}</div>
                  <span className={`badge ${cls}`}>{label}</span>
                  {r.response_note && <div className="faint">Réponse : {r.response_note}</div>}
                </div>
                <div style={{ display: 'flex', gap: 6 }}>
                  {canRespond(r) && (
                    <>
                      <button className="btn btn-primary btn-sm" disabled={busy} onClick={() => act(() => onRespond(r.id, 'accept', note))}>
                        <IconCheck width={14} height={14} /> Accepter
                      </button>
                      <button className="btn btn-danger btn-sm" disabled={busy} onClick={() => act(() => onRespond(r.id, 'reject', note))}>
                        Refuser
                      </button>
                    </>
                  )}
                  {r.status === 'en_attente' && isRequester(r) && (
                    <button className="btn btn-ghost btn-sm" disabled={busy} onClick={() => act(() => onCancel(r.id))}>
                      Annuler
                    </button>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      </div>

      {pending.length > 0 && (
        <Field label="Note (facultatif — jointe à votre réponse)">
          <textarea className="textarea" value={note} onChange={(e) => setNote(e.target.value)} />
        </Field>
      )}
    </Modal>
  )
}
