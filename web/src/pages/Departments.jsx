import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Field, Modal, Spinner } from '../components/ui'
import JqDataTable from '../components/DataTable'
import FilterBar from '../components/FilterBar'
import PageHeader from '../components/PageHeader'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { IconPlus } from '../components/Icons'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export default function Departments() {
  const { isDirecteur, isAdmin } = useAuth()
  const navigate = useNavigate()
  const toast = useToast()
  const canManage = isDirecteur || isAdmin

  const [items, setItems] = useState(null)
  const [editing, setEditing] = useState(null)
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')

  const rows = useMemo(() => {
    if (!items) return []
    return items.filter((d) => {
      if (status === 'active' && !d.is_active) return false
      if (status === 'inactive' && d.is_active) return false
      return matchQuery(query, d.name, d.description)
    })
  }, [items, query, status])

  const load = useCallback(() => {
    api.get('/departments').then(({ data }) => setItems(data.data)).catch(() => setItems([]))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const save = async (form) => {
    try {
      if (form.id) await api.put(`/departments/${form.id}`, form)
      else await api.post('/departments', form)
      toast.success('Département enregistré.')
      setEditing(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const toggleActive = async (d) => {
    try {
      await api.put(`/departments/${d.id}`, { is_active: !d.is_active })
      toast.success(d.is_active ? 'Département désactivé.' : 'Département réactivé.')
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const remove = async (d) => {
    if (!confirm(`Supprimer le département « ${d.name} » ? Ses tâches et postes seront supprimés.`)) return
    try {
      await api.delete(`/departments/${d.id}`)
      toast.success('Département supprimé.')
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const onAction = (act, row) => {
    if (act === 'view') navigate(`/departements/${row.id}`)
    if (act === 'edit') setEditing(row)
    if (act === 'toggle') toggleActive(row)
    if (act === 'delete') remove(row)
  }

  const columns = [
    {
      title: 'Département',
      data: null,
      render: (_d, _t, r) =>
        `<span class="cell-strong">${esc(r.name)}</span>` +
        (r.description ? `<div class="faint">${esc(r.description)}</div>` : ''),
    },
    { title: 'Membres', data: 'members_count', className: 'dt-center' },
    { title: 'Postes', data: 'positions_count', className: 'dt-center' },
    { title: 'Tâches', data: 'tasks_count', className: 'dt-center' },
    {
      title: 'Statut',
      data: 'is_active',
      render: (v) =>
        v
          ? '<span class="badge badge-green">actif</span>'
          : '<span class="badge badge-grey">inactif</span>',
    },
    {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: (_d, _t, r) =>
        rowActions([
          { act: 'view' },
          canManage && { act: 'edit' },
          canManage && { act: 'toggle', on: r.is_active },
          canManage && { act: 'delete' },
        ]),
    },
  ]

  if (items === null) return <Spinner />

  return (
    <>
      <PageHeader
        title="Départements"
        subtitle="Créer, modifier, activer ou supprimer les départements de Wycliffe Bénin."
        actions={
          canManage && (
            <button className="btn btn-primary" onClick={() => setEditing({})}>
              <IconPlus width={16} height={16} /> Nouveau département
            </button>
          )
        }
      />

      <FilterBar
        query={query}
        onQuery={setQuery}
        placeholder="Rechercher un département…"
        filters={[
          {
            key: 'status',
            label: 'Statut',
            value: status,
            onChange: setStatus,
            allLabel: 'Tous les statuts',
            options: [
              { value: 'active', label: 'Actifs' },
              { value: 'inactive', label: 'Inactifs' },
            ],
          },
        ]}
        count={rows.length}
        total={items.length}
        resultNoun="département"
      />

      <JqDataTable
        columns={columns}
        rows={rows}
        onAction={onAction}
        options={{ order: [[0, 'asc']], ...HIDE_DT_SEARCH }}
      />

      {editing && <DepartmentForm value={editing} onClose={() => setEditing(null)} onSave={save} />}
    </>
  )
}

function DepartmentForm({ value, onClose, onSave }) {
  const [form, setForm] = useState({
    id: value.id,
    name: value.name || '',
    description: value.description || '',
    is_active: value.is_active ?? true,
  })
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    await onSave(form)
    setBusy(false)
  }

  return (
    <Modal
      title={value.id ? 'Modifier le département' : 'Nouveau département'}
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary" form="dept-form" disabled={busy}>Enregistrer</button>
        </>
      }
    >
      <form id="dept-form" onSubmit={submit}>
        <Field label="Nom">
          <input className="input" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required />
        </Field>
        <Field label="Description">
          <textarea className="textarea" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
        </Field>
        <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.9rem' }}>
          <input type="checkbox" checked={form.is_active} onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))} />
          Département actif
        </label>
      </form>
    </Modal>
  )
}
