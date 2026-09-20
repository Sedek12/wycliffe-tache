import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { differenceInCalendarDays } from 'date-fns'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { fmtBytes, fmtDate, fmtDateTime, fromNow } from '../lib/format'
import {
  Avatar,
  Field,
  MentionBadge,
  Modal,
  ProgressBar,
  ProgressRing,
  Spinner,
  StatusBadge,
} from '../components/ui'
import PageHeader from '../components/PageHeader'
import {
  IconFile,
  IconUpload,
  IconEdit,
  IconTrash,
  IconPlus,
  IconEye,
  IconDownload,
  IconCircleCheck,
  IconReopen,
  IconCheck,
  IconX,
} from '../components/Icons'
import { useToast } from '../context/ToastContext'
import TaskForm from '../components/TaskForm'
import ConfirmDialog from '../components/ConfirmDialog'
import DeliverableViewer from '../components/DeliverableViewer'
import CollaborationModal from '../components/CollaborationModal'

function Card({ title, action, children }) {
  return (
    <div className="card card-pad" style={{ marginBottom: 16 }}>
      <div className="spread" style={{ marginBottom: 12 }}>
        <h3 style={{ margin: 0 }}>{title}</h3>
        {action}
      </div>
      {children}
    </div>
  )
}

function dueText(task) {
  if (task.status === 'validee') return null
  const d = differenceInCalendarDays(new Date(task.due_at), new Date())
  if (d < 0) return `en retard de ${Math.abs(d)} j`
  if (d === 0) return "aujourd'hui"
  if (d === 1) return 'demain'
  return `dans ${d} jours`
}

const proofFile = (p) => ({
  original_name: p.proof_name,
  mime: p.proof_mime,
  url: p.proof_url,
  preview_url: p.proof_preview_url,
})

export default function TaskDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user, isSupervisor, isAdmin, isChefOf } = useAuth()
  const toast = useToast()

  const [task, setTask] = useState(null)
  const [err, setErr] = useState('')
  const [editing, setEditing] = useState(false)
  const [viewing, setViewing] = useState(null)
  const [collab, setCollab] = useState(false)
  const [subForm, setSubForm] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const load = useCallback(() => {
    api.get(`/tasks/${id}`).then(({ data }) => setTask(data.data)).catch((e) => setErr(errorMessage(e)))
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  if (err) return <p className="muted">{err}</p>
  if (!task) return <Spinner />

  const isTaskSupervisor = task.supervisor_id === user.id
  const abil = task.delegated_abilities || []
  const chefLevel = isSupervisor || isChefOf(task.department_id)
  const supCan = (key) => isTaskSupervisor && abil.includes(key)

  const canDelete = isAdmin || isChefOf(task.department_id)
  const canEditTask = chefLevel || supCan('manage_team')
  const canStatus = chefLevel || supCan('change_status')
  const canCollab = chefLevel || supCan('request_collaboration')
  const canCreateSub = chefLevel || supCan('create_subtasks')
  const isCreatorChef = task.creator?.id === user.id && isChefOf(task.department_id)
  const myPart = (task.assignees || []).find((a) => a.id === user.id)
  const isAssignee = Boolean(myPart)
  const myPartDone = Boolean(myPart?.is_done)
  const canEvaluate = isCreatorChef && ['livree', 'validee'].includes(task.status)
  const closed = task.status === 'validee'
  const due = dueText(task)

  const post = async (url, body, okMsg) => {
    try {
      const { data } = await api.post(url, body)
      setTask(data.data)
      if (okMsg) toast.success(okMsg)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const myPendingCollab = (task.collaboration_requests || []).filter(
    (c) => c.status === 'en_attente' && isChefOf(c.to_department_id),
  )

  const respondCollab = async (reqId, decision) => {
    try {
      await api.post(`/collaboration-requests/${reqId}/respond`, { decision })
      toast.success(decision === 'accept' ? 'Demande acceptée.' : 'Demande refusée.')
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const changeStatus = (status) => post(`/tasks/${task.id}/status`, { status }, 'Statut mis à jour.')
  const completeMyPart = () => post(`/tasks/${task.id}/complete-my-part`, {}, 'Votre part est validée.')
  const reopenPart = (uid) => post(`/tasks/${task.id}/assignees/${uid}/reopen`, {}, 'Part rouverte.')
  const reviewUpdate = (uid) => post(`/tasks/${task.id}/progress/${uid}/review`, {})
  const rejectUpdate = (uid) => {
    const note = window.prompt('Motif de la dévaluation (facultatif) :') ?? ''
    post(`/tasks/${task.id}/progress/${uid}/reject`, { note }, 'Version dévaluée.')
  }
  const requestReopen = () => post(`/tasks/${task.id}/request-reopen`, {}, 'Demande envoyée au responsable.')

  const removeTask = async (reason) => {
    setDeleting(true)
    try {
      await api.delete(`/tasks/${task.id}`, { data: { reason } })
      toast.success('Tâche supprimée.')
      navigate('/taches')
    } catch (e) {
      toast.error(errorMessage(e))
      setDeleting(false)
    }
  }

  const removeDeliverable = async (d) => {
    if (!confirm(`Retirer « ${d.original_name} » ?`)) return
    try {
      await api.delete(`/tasks/${task.id}/deliverables/${d.id}`)
      toast.success('Livrable retiré.')
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  return (
    <>
      <PageHeader
        back={
          <button className="btn btn-ghost btn-sm" onClick={() => navigate('/taches')} style={{ marginBottom: 8 }}>
            ← Tâches
          </button>
        }
        title={task.title}
        actions={
          (canEditTask || canDelete) && (
            <>
              {!closed && canEditTask && (
                <button className="btn btn-ghost btn-sm" onClick={() => setEditing(true)}>
                  <IconEdit width={15} height={15} /> Modifier
                </button>
              )}
              {canDelete && (
                <button className="btn btn-danger btn-sm" onClick={() => setConfirmDelete(true)}>
                  <IconTrash width={15} height={15} /> Supprimer
                </button>
              )}
            </>
          )
        }
      />

      {/* Résumé */}
      <div className="task-hero card" style={{ marginTop: -6 }}>
        <ProgressRing value={task.progress || 0} />
        <div className="task-hero__meta">
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <StatusBadge status={task.status} label={task.status_label} />
            {task.is_overdue && <span className="badge badge-red">En retard</span>}
            {task.is_team && <span className="badge badge-grey">Équipe · {task.assignees?.length || 0} membres</span>}
          </div>
          <div className="meta-grid">
            <div><span className="meta-k">Département</span><span className="meta-v">{task.department?.name || '—'}</span></div>
            <div><span className="meta-k">Début</span><span className="meta-v">{fmtDate(task.starts_at)}</span></div>
            <div>
              <span className="meta-k">Échéance</span>
              <span className="meta-v">{fmtDate(task.due_at)}{due && <small>{due}</small>}</span>
            </div>
            <div><span className="meta-k">Créée par</span><span className="meta-v">{task.creator?.name || '—'}</span></div>
            <div><span className="meta-k">Responsable</span><span className="meta-v">{task.supervisor?.name || task.creator?.name || '—'}</span></div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            {(task.assignees || []).slice(0, 8).map((a) => (
              <span key={a.id} title={a.name} style={{ marginRight: -8 }}>
                <Avatar user={a} size={28} />
              </span>
            ))}
            <span className="faint" style={{ marginLeft: 14 }}>
              {(task.assignees || []).length
                ? `${task.assignees.length} assigné${task.assignees.length > 1 ? 's' : ''}`
                : 'Non assignée'}
            </span>
          </div>
        </div>
      </div>

      {myPendingCollab.length > 0 && (
        <div className="card card-pad" style={{ marginBottom: 16, borderLeft: '4px solid var(--wy-orange)' }}>
          <h3 style={{ marginTop: 0 }}>Demande de collaboration à traiter</h3>
          <p className="faint" style={{ marginTop: -4 }}>
            Un chef souhaite associer {myPendingCollab.length > 1 ? 'ces personnes' : 'cette personne'} de votre département à la tâche.
          </p>
          {myPendingCollab.map((c) => (
            <div key={c.id} className="spread" style={{ padding: '8px 0', borderTop: '1px solid var(--border)' }}>
              <strong>{c.target_user}</strong>
              <div style={{ display: 'flex', gap: 8 }}>
                <button className="btn btn-primary btn-sm" onClick={() => respondCollab(c.id, 'accept')}>
                  <IconCheck width={14} height={14} /> Accepter
                </button>
                <button className="btn btn-danger btn-sm" onClick={() => respondCollab(c.id, 'reject')}>
                  Refuser
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="detail-grid">
        <div>
          {task.parent && (
            <div className="card card-pad" style={{ marginBottom: 16 }}>
              <span className="faint">Sous-tâche de </span>
              <button className="file-row__name" onClick={() => navigate(`/taches/${task.parent.id}`)}>
                {task.parent.title}
              </button>
            </div>
          )}

          <Card title="Description">
            <p style={{ whiteSpace: 'pre-wrap', margin: 0, lineHeight: 1.6 }}>{task.description}</p>
          </Card>

          <Card
            title={`Sous-tâches (${task.sub_tasks?.length || 0})`}
            action={
              canCreateSub && !closed && (
                <button className="btn btn-ghost btn-sm" onClick={() => setSubForm(true)}>
                  <IconPlus width={14} height={14} /> Nouvelle sous-tâche
                </button>
              )
            }
          >
            {(task.sub_tasks || []).length === 0 && (
              <p className="faint" style={{ margin: 0 }}>Aucune sous-tâche.</p>
            )}
            {(task.sub_tasks || []).map((s) => (
              <div
                key={s.id}
                className="member-row"
                style={{ cursor: 'pointer' }}
                onClick={() => navigate(`/taches/${s.id}`)}
              >
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600 }}>{s.title}</div>
                  <div className="faint" style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginTop: 2 }}>
                    <StatusBadge status={s.status} label={s.status_label} />
                    <span>{s.progress || 0}%</span>
                    {s.is_overdue && <span className="badge badge-red">en retard</span>}
                  </div>
                </div>
              </div>
            ))}
          </Card>

          <Card
            title={`Livrables (${task.deliverables?.length || 0})`}
            action={
              isAssignee && !closed && (
                <UploadButton taskId={task.id} onDone={load} disabled={myPartDone} />
              )
            }
          >
            {myPartDone && (
              <p className="faint" style={{ marginTop: 0 }}>
                Votre part est validée — demandez sa réouverture au responsable pour ajouter un fichier.
              </p>
            )}
            {(task.deliverables || []).length === 0 && <p className="faint" style={{ margin: 0 }}>Aucun livrable déposé.</p>}
            {(task.deliverables || []).map((d) => {
              const mine = d.uploader?.id === user.id
              return (
                <div key={d.id} className="file-row">
                  <span className="file-row__ico"><IconFile width={18} height={18} /></span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <button className="file-row__name" onClick={() => setViewing(d)}>{d.original_name}</button>
                    <div className="faint">{fmtBytes(d.size)} · {d.uploader?.name} · {fromNow(d.created_at)}</div>
                    {d.note && <div className="faint">« {d.note} »</div>}
                  </div>
                  <div style={{ display: 'flex', gap: 6 }}>
                    <button className="dt-ico dt-ico--blue" title="Voir" onClick={() => setViewing(d)}>
                      <IconEye width={15} height={15} />
                    </button>
                    <a className="dt-ico dt-ico--blue" title="Télécharger" href={d.url} download>
                      <IconDownload width={15} height={15} />
                    </a>
                    {(mine || canStatus) && !closed && (
                      <button className="dt-ico dt-ico--red" title="Retirer" onClick={() => removeDeliverable(d)}>
                        <IconTrash width={15} height={15} />
                      </button>
                    )}
                  </div>
                </div>
              )
            })}
          </Card>
        </div>

        <div>
          <Card title="Avancement">
            <span className="eval-score" style={{ fontSize: '1.4rem' }}>{task.progress || 0}%</span>
            <div style={{ marginTop: 6 }}><ProgressBar value={task.progress || 0} /></div>

            {isAssignee && !closed && !myPartDone && (
              <ProgressForm taskId={task.id} stages={task.progress_stages || []} onDone={load} />
            )}

            {isAssignee && myPartDone && !closed && (
              <div style={{ marginTop: 12, borderTop: '1px solid var(--border)', paddingTop: 12 }}>
                <p className="faint" style={{ marginTop: 0 }}>
                  Votre part est verrouillée (livraison à 100 %). Pour la reprendre, demandez une réouverture
                  au responsable, ou attendez qu’une version soit dévaluée.
                </p>
                {myPart?.reopen_requested_at ? (
                  <span className="badge badge-amber">Réouverture demandée · {fromNow(myPart.reopen_requested_at)}</span>
                ) : (
                  <button className="btn btn-ghost btn-sm" onClick={requestReopen}>
                    <IconReopen width={14} height={14} /> Demander à reprendre ma part
                  </button>
                )}
              </div>
            )}

            {(task.progress_updates || []).length > 0 && (
              <div className="upd-list" style={{ marginTop: 14 }}>
                {(task.progress_updates || []).map((p, i, arr) => {
                  const version = arr.length - i
                  return (
                    <div key={p.id} className="upd-row">
                      <div className="upd-row__main">
                        <b>v{version}</b>
                        {p.stage_label && <span className="badge badge-blue" style={{ marginLeft: 6 }}>{p.stage_label}</span>}
                        <span className="faint"> · {p.percent}% · {p.user?.name} · {fromNow(p.created_at)}</span>
                        {p.comment && <div className="faint">« {p.comment} »</div>}
                        {!p.proof_url && !p.comment && <div className="faint">—</div>}
                        <div style={{ marginTop: 4, display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                          {p.rejected_at && <span className="badge badge-red" title={`Par ${p.rejected_by}`}>dévaluée</span>}
                          {p.reviewed_at && <span className="badge badge-green" title={`Par ${p.reviewed_by}`}>revue</span>}
                        </div>
                      </div>
                      <div className="upd-row__side">
                        {p.proof_url && (
                          <button className="dt-ico dt-ico--blue" title="Voir le document" onClick={() => setViewing(proofFile(p))}>
                            <IconEye width={14} height={14} />
                          </button>
                        )}
                        {canStatus && (
                          <>
                            <button
                              className={`dt-ico ${p.reviewed_at ? 'dt-ico--green' : 'dt-ico--blue'}`}
                              title={p.reviewed_at ? `Revue par ${p.reviewed_by}` : 'Marquer comme revue'}
                              onClick={() => reviewUpdate(p.id)}
                            >
                              <IconCheck width={14} height={14} />
                            </button>
                            <button
                              className="dt-ico dt-ico--red"
                              title="Dévaluer cette version"
                              onClick={() => rejectUpdate(p.id)}
                            >
                              <IconX width={14} height={14} />
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            )}
          </Card>

          <Card title="Actions">
            {task.is_team && task.all_parts_done && task.status === 'en_cours' && (
              <p className="faint" style={{ marginTop: 0 }}>Toutes les parts sont validées — vous pouvez livrer la tâche.</p>
            )}
            {(() => {
              const buttons = (task.allowed_next || []).filter(
                (s) => canStatus || (isAssignee && task.status === 'en_cours' && s.value === 'livree'),
              )
              if (buttons.length === 0) return <p className="faint" style={{ margin: 0 }}>Aucune action disponible.</p>
              return (
                <div className="status-actions">
                  {buttons.map((s) => (
                    <button key={s.value} className="btn btn-blue btn-sm" onClick={() => changeStatus(s.value)}>
                      Passer à « {s.label} »
                    </button>
                  ))}
                </div>
              )
            })()}
          </Card>

          <Card
            title="Équipe"
            action={
              canCollab && !closed && (
                <button className="btn btn-ghost btn-sm" onClick={() => setCollab(true)}>
                  <IconPlus width={14} height={14} /> Autre département
                </button>
              )
            }
          >
            {(task.assignees || []).length === 0 && <p className="faint" style={{ margin: 0 }}>Non assignée.</p>}
            {(task.assignees || []).map((a) => (
              <div key={a.id} className="member-row">
                <Avatar user={a} size={30} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600, display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                    {a.name}
                    {a.id === task.supervisor_id && <span className="badge badge-blue">responsable</span>}
                    {a.is_external && <span className="badge badge-grey">externe</span>}
                    {a.is_done ? (
                      <span className="badge badge-green">terminé</span>
                    ) : (
                      <span className="badge badge-amber">en cours</span>
                    )}
                  </div>
                  {a.instructions && <div className="faint">{a.instructions}</div>}
                  <div style={{ marginTop: 6, display: 'flex', gap: 6 }}>
                    {a.id === user.id && !a.is_done && !closed && (
                      <button className="btn btn-primary btn-sm" onClick={completeMyPart}>
                        <IconCircleCheck width={14} height={14} /> J’ai terminé ma part
                      </button>
                    )}
                    {canStatus && a.is_done && !closed && (
                      <button className="btn btn-ghost btn-sm" onClick={() => reopenPart(a.id)}>
                        <IconReopen width={14} height={14} /> Rouvrir
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </Card>

          <Card title="Évaluation">
            {task.evaluation ? (
              <div>
                <div className="eval-score">
                  {task.evaluation.score}/20
                  <MentionBadge mention={task.evaluation.mention} label={task.evaluation.mention_label} />
                </div>
                {task.evaluation.appreciation && <p style={{ marginTop: 8 }}>{task.evaluation.appreciation}</p>}
                <div className="faint">Par {task.evaluation.evaluator} · {fromNow(task.evaluation.created_at)}</div>
                {canEvaluate && <EvalForm task={task} onDone={load} compact />}
              </div>
            ) : canEvaluate ? (
              <EvalForm task={task} onDone={load} />
            ) : (
              <p className="faint" style={{ margin: 0 }}>Disponible une fois la tâche livrée (chef créateur uniquement).</p>
            )}
          </Card>
        </div>
      </div>

      {/* Journaux : activité de l'équipe (gauche) / décisions & autorité (droite) */}
      <div className="detail-grid" style={{ gridTemplateColumns: '1fr 1fr', marginTop: 4 }}>
        <Card title="Activité de l’équipe">
          <ActivityFeed
            items={(task.activities || []).filter((a) => a.stream === 'activity')}
            empty="Aucune activité pour l’instant."
          />
        </Card>
        <Card title="Décisions & autorité">
          <ActivityFeed
            items={(task.activities || []).filter((a) => a.stream === 'authority')}
            empty="Aucune décision d’autorité."
          />
        </Card>
      </div>

      {editing && <TaskForm task={task} onClose={() => setEditing(false)} onSaved={() => { setEditing(false); load() }} />}
      {subForm && (
        <TaskForm
          parent={task}
          onClose={() => setSubForm(false)}
          onSaved={() => { setSubForm(false); load() }}
        />
      )}
      {viewing && <DeliverableViewer deliverable={viewing} onClose={() => setViewing(null)} />}
      {collab && <CollaborationModal task={task} onClose={() => setCollab(false)} onSent={load} />}

      {confirmDelete && (
        <ConfirmDialog
          title="Supprimer la tâche"
          message={
            <>
              La tâche « <strong>{task.title}</strong> »{task.sub_tasks?.length ? ', ses sous-tâches' : ''} et
              tout son historique seront définitivement supprimés. Cette action est irréversible.
            </>
          }
          confirmLabel="Supprimer définitivement"
          reason={{ label: 'Motif de la suppression', placeholder: 'Expliquez pourquoi…', required: true }}
          busy={deleting}
          onConfirm={removeTask}
          onClose={() => setConfirmDelete(false)}
        />
      )}
    </>
  )
}

const ACT_TONE = {
  progress_added: 'blue',
  progress_rejected: 'red',
  reopen_requested: 'amber',
  deliverable_added: 'blue',
  deliverable_removed: 'amber',
  status_changed: 'blue',
  part_completed: 'green',
  progress_reviewed: 'green',
  member_added: 'blue',
  task_created: 'grey',
  task_updated: 'grey',
  member_removed: 'red',
  part_reopened: 'amber',
  collab_requested: 'blue',
  collab_accepted: 'green',
  collab_rejected: 'red',
  authority_delegated: 'orange',
  task_cancelled: 'red',
}

function ActivityFeed({ items, empty }) {
  if (!items || items.length === 0) return <p className="faint" style={{ margin: 0 }}>{empty}</p>
  return (
    <div className="feed">
      {items.map((a) => (
        <div key={a.id} className="feed__row">
          <span className={`feed__dot feed__dot--${ACT_TONE[a.type] || 'grey'}`} />
          <div className="feed__body">
            <div className="feed__text">{a.description}</div>
            <div className="feed__meta">
              {a.actor ? `${a.actor} · ` : ''}{fromNow(a.created_at)}
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

const FALLBACK_STAGES = [
  { value: 'demarrage', label: 'Démarrage', band: '0 – 30 %', percent: 30 },
  { value: 'mi_parcours', label: 'Mi-parcours', band: '30 – 70 %', percent: 70 },
  { value: 'finalisation', label: 'Finalisation', band: '70 – 100 %', percent: 95 },
  { value: 'livraison', label: 'Livraison finale', band: '100 %', percent: 100 },
]

function ProgressForm({ taskId, stages, onDone }) {
  const toast = useToast()
  const list = stages && stages.length ? stages : FALLBACK_STAGES
  const [stage, setStage] = useState('')
  const [comment, setComment] = useState('')
  const [file, setFile] = useState(null)
  const [busy, setBusy] = useState(false)

  const isFinal = list.find((s) => s.value === stage)?.value === 'livraison'
  const canSubmit = stage && (file || comment.trim()) && !busy

  const submit = async (e) => {
    e.preventDefault()
    if (!stage) return toast.error('Choisissez une étape.')
    if (!file && !comment.trim()) return toast.error('Joignez un document ou saisissez une explication.')
    setBusy(true)
    try {
      const fd = new FormData()
      fd.append('stage', stage)
      if (comment.trim()) fd.append('comment', comment.trim())
      if (file) fd.append('file', file)
      await api.post(`/tasks/${taskId}/progress`, fd)
      setComment('')
      setFile(null)
      setStage('')
      toast.success('Version enregistrée.')
      onDone()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={submit} style={{ marginTop: 14, borderTop: '1px solid var(--border)', paddingTop: 14 }}>
      <div style={{ fontWeight: 600, fontSize: '0.85rem', marginBottom: 8 }}>Nouvelle version</div>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
        {list.map((s) => (
          <button
            type="button"
            key={s.value}
            className={`stage-chip${stage === s.value ? ' is-on' : ''}`}
            onClick={() => setStage(s.value)}
          >
            <span className="stage-chip__label">{s.label}</span>
            <span className="stage-chip__band">{s.band}</span>
          </button>
        ))}
      </div>

      {isFinal && (
        <p className="faint" style={{ marginTop: 10 }}>
          ⚠️ Après cette livraison, votre part sera <strong>verrouillée</strong>. Vous devrez demander une
          réouverture (ou attendre une dévaluation) pour soumettre une nouvelle version.
        </p>
      )}

      <label className="field" style={{ margin: '12px 0 0' }}>
        <span className="faint">Document <span className="faint">(ou explication ci-dessous — au moins l’un des deux)</span></span>
        <input className="input" type="file" onChange={(e) => setFile(e.target.files[0] || null)} />
      </label>
      <textarea
        className="textarea"
        placeholder="Expliquez ce qui a été fait à cette étape…"
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        style={{ marginTop: 10, minHeight: 70 }}
      />

      <button className="btn btn-primary btn-sm" disabled={!canSubmit} style={{ marginTop: 10 }}>
        {busy ? '…' : isFinal ? 'Livrer à 100 %' : 'Soumettre la version'}
      </button>
    </form>
  )
}

function UploadButton({ taskId, onDone, disabled }) {
  const toast = useToast()
  const [open, setOpen] = useState(false)
  const [file, setFile] = useState(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    if (!file) return
    setBusy(true)
    try {
      const fd = new FormData()
      fd.append('file', file)
      if (note) fd.append('note', note)
      await api.post(`/tasks/${taskId}/deliverables`, fd)
      toast.success('Livrable déposé.')
      setOpen(false)
      setFile(null)
      setNote('')
      onDone()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <button className="btn btn-primary btn-sm" onClick={() => setOpen(true)} disabled={disabled}>
        <IconUpload width={15} height={15} /> Déposer
      </button>
      {open && (
        <Modal
          title="Déposer un livrable"
          onClose={() => setOpen(false)}
          footer={
            <>
              <button className="btn btn-ghost" onClick={() => setOpen(false)}>Annuler</button>
              <button className="btn btn-primary" form="up-form" disabled={busy || !file}>
                {busy ? 'Envoi…' : 'Déposer'}
              </button>
            </>
          }
        >
          <form id="up-form" onSubmit={submit}>
            <div className="form-section">
              <div className="form-section__title">Livrable</div>
              <Field label={<>Fichier <span className="req">*</span></>} hint="Image, PDF, son, vidéo, Word…">
                <input className="input" type="file" onChange={(e) => setFile(e.target.files[0])} required />
              </Field>
              <Field label="Note (facultatif)">
                <textarea className="textarea" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Précisions sur ce que contient le fichier…" />
              </Field>
            </div>
          </form>
        </Modal>
      )}
    </>
  )
}

function EvalForm({ task, onDone, compact }) {
  const toast = useToast()
  const [score, setScore] = useState(task.evaluation?.score ?? 14)
  const [appreciation, setAppreciation] = useState(task.evaluation?.appreciation ?? '')
  const [busy, setBusy] = useState(false)
  const [open, setOpen] = useState(!compact)

  if (compact && !open) {
    return (
      <button className="btn btn-ghost btn-sm" style={{ marginTop: 10 }} onClick={() => setOpen(true)}>
        <IconEdit width={14} height={14} /> Modifier l’évaluation
      </button>
    )
  }

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    try {
      await api.post(`/tasks/${task.id}/evaluation`, { score: Number(score), appreciation: appreciation || null })
      toast.success('Évaluation enregistrée.')
      onDone()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={submit} style={{ marginTop: 12 }}>
      <Field label={`Note : ${score} / 20`}>
        <input type="range" min="0" max="20" value={score} onChange={(e) => setScore(e.target.value)} style={{ width: '100%' }} />
      </Field>
      <Field label="Appréciation">
        <textarea className="textarea" value={appreciation} onChange={(e) => setAppreciation(e.target.value)} />
      </Field>
      <button className="btn btn-primary btn-sm" disabled={busy}>{busy ? '…' : 'Enregistrer l’évaluation'}</button>
      {task.status === 'livree' && (
        <p className="faint" style={{ marginTop: 8 }}>Pensez ensuite à passer la tâche à « Validée ».</p>
      )}
    </form>
  )
}
