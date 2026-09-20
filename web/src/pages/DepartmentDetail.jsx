import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useMeta } from '../context/MetaContext'
import { useToast } from '../context/ToastContext'
import { Field, Modal, Spinner } from '../components/ui'
import JqDataTable from '../components/DataTable'
import PageHeader from '../components/PageHeader'
import { rowActions } from '../lib/rowActions'
import { IconPlus } from '../components/Icons'

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
const initials = (name = '') =>
  name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || 'U'

export default function DepartmentDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { isDirecteur, isAdmin } = useAuth()
  const { meta } = useMeta()
  const toast = useToast()
  const canManage = isDirecteur || isAdmin

  const [dept, setDept] = useState(null)
  const [allUsers, setAllUsers] = useState([])
  const [tab, setTab] = useState('membres')
  const [addMember, setAddMember] = useState({ user_id: '', role: 'employe', position_id: '' })
  const [addPoste, setAddPoste] = useState({ name: '', description: '', is_active: true })
  const [editMember, setEditMember] = useState(null)
  const [editPoste, setEditPoste] = useState(null)

  const load = useCallback(() => {
    api.get(`/departments/${id}`).then(({ data }) => setDept(data.data)).catch(() => setDept(false))
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    if (canManage) {
      api.get('/users', { params: { per_page: 300, active_only: 1 } }).then(({ data }) => setAllUsers(data.data))
    }
  }, [canManage])

  const positionsById = useMemo(
    () => Object.fromEntries((dept?.positions || []).map((p) => [p.id, p.name])),
    [dept],
  )
  const roleLabel = (v) => meta?.department_roles?.find((r) => r.value === v)?.label || v

  if (dept === false) return <p className="muted">Département introuvable.</p>
  if (!dept) return <Spinner />

  const upsertMember = async (user_id, role, position_id) => {
    try {
      await api.post(`/departments/${id}/members`, { user_id, role, position_id: position_id || null })
      toast.success('Membre enregistré.')
      setAddMember({ user_id: '', role: 'employe', position_id: '' })
      setEditMember(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const removeMember = async (uid) => {
    if (!confirm('Retirer ce membre du département ?')) return
    await api.delete(`/departments/${id}/members/${uid}`)
    load()
  }

  const savePoste = async (form) => {
    const payload = {
      name: form.name,
      description: form.description,
      is_active: form.is_active !== false,
    }
    try {
      if (form.id) await api.put(`/positions/${form.id}`, payload)
      else await api.post('/positions', { department_id: Number(id), ...payload })
      toast.success('Poste enregistré.')
      setAddPoste({ name: '', description: '', is_active: true })
      setEditPoste(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const removePoste = async (pid) => {
    if (!confirm('Supprimer ce poste ? Les membres concernés n’auront plus de poste.')) return
    await api.delete(`/positions/${pid}`)
    load()
  }

  // -------- Colonnes MEMBRES --------
  const memberColumns = [
    {
      title: 'Membre',
      data: null,
      render: (_d, _t, r) =>
        `<span class="dt-avatar">${esc(initials(r.name))}</span><span class="cell-strong">${esc(r.name)}</span>` +
        `<div class="faint">${esc(r.email)}</div>`,
    },
    { title: 'Fonction', data: 'job_title', render: (v) => esc(v || '—') },
    {
      title: 'Poste',
      data: null,
      render: (_d, _t, r) => esc(positionsById[r.pivot?.position_id] || '—'),
    },
    {
      title: 'Rôle',
      data: null,
      render: (_d, _t, r) => `<span class="badge badge-blue">${esc(roleLabel(r.pivot?.role))}</span>`,
    },
    canManage && {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: () => rowActions([{ act: 'edit' }, { act: 'delete', title: 'Retirer du département' }]),
    },
  ].filter(Boolean)

  const onMemberAction = (act, row) => {
    if (act === 'edit') {
      setEditMember({
        id: row.id,
        name: row.name,
        role: row.pivot?.role || 'employe',
        position_id: row.pivot?.position_id || '',
      })
    }
    if (act === 'delete') removeMember(row.id)
  }

  // -------- Colonnes POSTES --------
  const posteColumns = [
    { title: 'Poste', data: 'name', render: (v) => `<span class="cell-strong">${esc(v)}</span>` },
    { title: 'Description', data: 'description', render: (v) => esc(v || '—') },
    { title: 'Membres', data: 'members_count', className: 'dt-center', render: (v) => v ?? 0 },
    {
      title: 'Statut',
      data: 'is_active',
      render: (v) =>
        v === false
          ? '<span class="badge badge-grey">inactif</span>'
          : '<span class="badge badge-green">actif</span>',
    },
    canManage && {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: (_d, _t, r) =>
        rowActions([{ act: 'edit' }, { act: 'toggle', on: r.is_active }, { act: 'delete' }]),
    },
  ].filter(Boolean)

  const onPosteAction = (act, row) => {
    if (act === 'edit') {
      setEditPoste({ id: row.id, name: row.name, description: row.description || '', is_active: row.is_active !== false })
    }
    if (act === 'toggle') togglePoste(row)
    if (act === 'delete') removePoste(row.id)
  }

  const togglePoste = async (row) => {
    try {
      await api.put(`/positions/${row.id}`, { is_active: !row.is_active })
      toast.success(row.is_active === false ? 'Poste réactivé.' : 'Poste désactivé.')
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const memberIds = new Set((dept.members || []).map((m) => m.id))

  return (
    <>
      <PageHeader
        back={
          <button className="btn btn-ghost btn-sm" onClick={() => navigate('/departements')} style={{ marginBottom: 8 }}>
            ← Départements
          </button>
        }
        title={dept.name}
        subtitle={dept.description || '—'}
        actions={!dept.is_active && <span className="badge badge-grey">inactif</span>}
      />

      <div style={{ display: 'flex', gap: 6, margin: '0 0 16px' }}>
        <button className={`btn btn-sm ${tab === 'membres' ? 'btn-blue' : 'btn-ghost'}`} onClick={() => setTab('membres')}>
          Membres ({dept.members?.length || 0})
        </button>
        <button className={`btn btn-sm ${tab === 'postes' ? 'btn-blue' : 'btn-ghost'}`} onClick={() => setTab('postes')}>
          Postes ({dept.positions?.length || 0})
        </button>
      </div>

      {tab === 'membres' && (
        <>
          <JqDataTable columns={memberColumns} rows={dept.members || []} onAction={onMemberAction} />

          {canManage && (
            <div className="card card-pad" style={{ marginTop: 16 }}>
              <h3>Ajouter un employé ou un stagiaire</h3>
              <div className="inline-form">
                <label className="field" style={{ margin: 0, minWidth: 200 }}>
                  <span className="faint">Personne</span>
                  <select
                    className="select"
                    value={addMember.user_id}
                    onChange={(e) => setAddMember((a) => ({ ...a, user_id: e.target.value }))}
                  >
                    <option value="">— choisir —</option>
                    {allUsers.filter((u) => !memberIds.has(u.id)).map((u) => (
                      <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                  </select>
                </label>
                <label className="field" style={{ margin: 0 }}>
                  <span className="faint">Rôle</span>
                  <select
                    className="select"
                    value={addMember.role}
                    onChange={(e) => setAddMember((a) => ({ ...a, role: e.target.value }))}
                  >
                    {meta?.department_roles?.map((r) => (
                      <option key={r.value} value={r.value}>{r.label}</option>
                    ))}
                  </select>
                </label>
                <label className="field" style={{ margin: 0, minWidth: 180 }}>
                  <span className="faint">Poste</span>
                  <select
                    className="select"
                    value={addMember.position_id}
                    onChange={(e) => setAddMember((a) => ({ ...a, position_id: e.target.value }))}
                  >
                    <option value="">— aucun —</option>
                    {(dept.positions || []).filter((p) => p.is_active !== false).map((p) => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                </label>
                <button
                  className="btn btn-primary"
                  disabled={!addMember.user_id}
                  onClick={() => upsertMember(addMember.user_id, addMember.role, addMember.position_id)}
                >
                  <IconPlus width={15} height={15} /> Ajouter
                </button>
              </div>
              <p className="faint" style={{ marginTop: 8 }}>
                Le poste proposé dépend du département sélectionné. Créez d’abord les postes dans l’onglet « Postes ».
              </p>
            </div>
          )}
        </>
      )}

      {tab === 'postes' && (
        <>
          <JqDataTable columns={posteColumns} rows={dept.positions || []} onAction={onPosteAction} options={{ order: [[0, 'asc']] }} />

          {canManage && (
            <div className="card card-pad" style={{ marginTop: 16 }}>
              <h3>Ajouter un poste à « {dept.name} »</h3>
              <div className="inline-form">
                <label className="field" style={{ margin: 0, minWidth: 200 }}>
                  <span className="faint">Intitulé du poste</span>
                  <input
                    className="input"
                    value={addPoste.name}
                    onChange={(e) => setAddPoste((a) => ({ ...a, name: e.target.value }))}
                  />
                </label>
                <label className="field" style={{ margin: 0, minWidth: 260 }}>
                  <span className="faint">Description (facultatif)</span>
                  <input
                    className="input"
                    value={addPoste.description}
                    onChange={(e) => setAddPoste((a) => ({ ...a, description: e.target.value }))}
                  />
                </label>
                <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: '0.88rem', paddingBottom: 4 }}>
                  <input
                    type="checkbox"
                    checked={addPoste.is_active !== false}
                    onChange={(e) => setAddPoste((a) => ({ ...a, is_active: e.target.checked }))}
                  />
                  Actif
                </label>
                <button className="btn btn-primary" disabled={!addPoste.name} onClick={() => savePoste(addPoste)}>
                  <IconPlus width={15} height={15} /> Ajouter le poste
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {editMember && (
        <Modal
          title={`Modifier — ${editMember.name}`}
          onClose={() => setEditMember(null)}
          footer={
            <>
              <button className="btn btn-ghost" onClick={() => setEditMember(null)}>Annuler</button>
              <button
                className="btn btn-primary"
                onClick={() => upsertMember(editMember.id, editMember.role, editMember.position_id)}
              >
                Enregistrer
              </button>
            </>
          }
        >
          <Field label="Rôle dans le département">
            <select
              className="select"
              value={editMember.role}
              onChange={(e) => setEditMember((m) => ({ ...m, role: e.target.value }))}
            >
              {meta?.department_roles?.map((r) => (
                <option key={r.value} value={r.value}>{r.label}</option>
              ))}
            </select>
          </Field>
          <Field label="Poste">
            <select
              className="select"
              value={editMember.position_id || ''}
              onChange={(e) => setEditMember((m) => ({ ...m, position_id: e.target.value }))}
            >
              <option value="">— aucun —</option>
              {(dept.positions || [])
                .filter((p) => p.is_active !== false || String(p.id) === String(editMember.position_id))
                .map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}{p.is_active === false ? ' (inactif)' : ''}
                  </option>
                ))}
            </select>
          </Field>
        </Modal>
      )}

      {editPoste && (
        <Modal
          title="Modifier le poste"
          onClose={() => setEditPoste(null)}
          footer={
            <>
              <button className="btn btn-ghost" onClick={() => setEditPoste(null)}>Annuler</button>
              <button className="btn btn-primary" onClick={() => savePoste(editPoste)}>Enregistrer</button>
            </>
          }
        >
          <Field label="Intitulé">
            <input
              className="input"
              value={editPoste.name}
              onChange={(e) => setEditPoste((p) => ({ ...p, name: e.target.value }))}
            />
          </Field>
          <Field label="Description">
            <textarea
              className="textarea"
              value={editPoste.description}
              onChange={(e) => setEditPoste((p) => ({ ...p, description: e.target.value }))}
            />
          </Field>
          <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.9rem' }}>
            <input
              type="checkbox"
              checked={editPoste.is_active !== false}
              onChange={(e) => setEditPoste((p) => ({ ...p, is_active: e.target.checked }))}
            />
            Poste actif
          </label>
        </Modal>
      )}
    </>
  )
}
