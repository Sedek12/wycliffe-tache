import { useCallback, useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { EmptyState, Field, Modal, Spinner } from '../components/ui'
import JqDataTable from '../components/DataTable'
import FilterBar from '../components/FilterBar'
import PageHeader from '../components/PageHeader'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { IconPlus } from '../components/Icons'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export default function Positions() {
  const { isDirecteur, isAdmin } = useAuth()
  const toast = useToast()
  const canManage = isDirecteur || isAdmin

  const [departments, setDepartments] = useState(null)
  const [positions, setPositions] = useState(null)
  const [filterDept, setFilterDept] = useState('')
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')
  const [editing, setEditing] = useState(null) // {} = nouveau, {id,...} = modification

  const loadDepartments = useCallback(() => {
    api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => setDepartments([]))
  }, [])

  const loadPositions = useCallback(() => {
    api.get('/positions').then(({ data }) => setPositions(data.data)).catch(() => setPositions([]))
  }, [])

  useEffect(() => {
    loadDepartments()
    loadPositions()
  }, [loadDepartments, loadPositions])

  const rows = useMemo(() => {
    if (!positions) return []
    return positions.filter((p) => {
      if (filterDept && String(p.department_id) !== String(filterDept)) return false
      if (status === 'active' && p.is_active === false) return false
      if (status === 'inactive' && p.is_active !== false) return false
      return matchQuery(query, p.name, p.description, p.department)
    })
  }, [positions, filterDept, query, status])

  const save = async (form) => {
    const payload = {
      department_id: Number(form.department_id),
      name: form.name,
      description: form.description || null,
      is_active: form.is_active,
    }
    try {
      if (form.id) await api.put(`/positions/${form.id}`, payload)
      else await api.post('/positions', payload)
      toast.success('Poste enregistré.')
      setEditing(null)
      loadPositions()
    } catch (err) {
      toast.error(errorMessage(err))
    }
  }

  const toggleActive = async (row) => {
    try {
      await api.put(`/positions/${row.id}`, { is_active: !row.is_active })
      toast.success(row.is_active ? 'Poste désactivé.' : 'Poste réactivé.')
      loadPositions()
    } catch (err) {
      toast.error(errorMessage(err))
    }
  }

  const remove = async (row) => {
    if (!confirm(`Supprimer le poste « ${row.name} » de « ${row.department} » ? Les membres concernés n’auront plus de poste.`)) return
    try {
      await api.delete(`/positions/${row.id}`)
      toast.success('Poste supprimé.')
      loadPositions()
    } catch (err) {
      toast.error(errorMessage(err))
    }
  }

  const columns = [
    { title: 'Département', data: 'department', render: (v) => `<span class="cell-strong">${esc(v)}</span>` },
    { title: 'Poste', data: 'name', render: (v) => esc(v) },
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
        rowActions([
          { act: 'edit' },
          { act: 'toggle', on: r.is_active },
          { act: 'delete' },
        ]),
    },
  ].filter(Boolean)

  const onAction = (act, row) => {
    if (act === 'edit') {
      setEditing({
        id: row.id,
        department_id: String(row.department_id),
        name: row.name,
        description: row.description || '',
        is_active: row.is_active !== false,
      })
    }
    if (act === 'toggle') toggleActive(row)
    if (act === 'delete') remove(row)
  }

  if (departments === null || positions === null) return <Spinner />

  return (
    <>
      <PageHeader
        title="Postes par département"
        subtitle="Un poste appartient à un département. Pour en créer un, choisissez d’abord le département."
        actions={
          canManage && (
            <button
              className="btn btn-primary"
              disabled={departments.length === 0}
              onClick={() =>
                setEditing({
                  department_id: filterDept || String(departments[0]?.id || ''),
                  name: '',
                  description: '',
                  is_active: true,
                })
              }
            >
              <IconPlus width={16} height={16} /> Nouveau poste
            </button>
          )
        }
      />

      {departments.length === 0 ? (
        <EmptyState title="Aucun département" hint="Créez d’abord un département." />
      ) : (
        <>
          <FilterBar
            query={query}
            onQuery={setQuery}
            placeholder="Rechercher un poste, une description…"
            filters={[
              {
                key: 'dept',
                label: 'Département',
                value: filterDept,
                onChange: setFilterDept,
                allLabel: 'Tous les départements',
                options: departments.map((d) => ({ value: String(d.id), label: d.name })),
              },
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
            total={positions.length}
            resultNoun="poste"
          />

          <JqDataTable
            columns={columns}
            rows={rows}
            onAction={onAction}
            options={{ order: [[0, 'asc'], [1, 'asc']], ...HIDE_DT_SEARCH }}
          />
        </>
      )}

      {editing && (
        <Modal
          title={editing.id ? 'Modifier le poste' : 'Nouveau poste'}
          onClose={() => setEditing(null)}
          footer={
            <>
              <button className="btn btn-ghost" onClick={() => setEditing(null)}>Annuler</button>
              <button
                className="btn btn-primary"
                disabled={!editing.department_id || !editing.name.trim()}
                onClick={() => save(editing)}
              >
                {editing.id ? 'Enregistrer' : 'Créer le poste'}
              </button>
            </>
          }
        >
          <Field label="Département" hint={editing.id ? 'Vous pouvez rattacher ce poste à un autre département.' : 'À choisir avant de créer le poste.'}>
            <select
              className="select"
              value={editing.department_id}
              onChange={(e) => setEditing((p) => ({ ...p, department_id: e.target.value }))}
            >
              <option value="">— choisir un département —</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </Field>
          <Field label="Intitulé du poste">
            <input
              className="input"
              value={editing.name}
              onChange={(e) => setEditing((p) => ({ ...p, name: e.target.value }))}
              placeholder="Ex. : Traducteur, Comptable, Animateur alpha…"
            />
          </Field>
          <Field label="Description (facultatif)">
            <textarea
              className="textarea"
              value={editing.description}
              onChange={(e) => setEditing((p) => ({ ...p, description: e.target.value }))}
            />
          </Field>
          <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.9rem' }}>
            <input
              type="checkbox"
              checked={editing.is_active !== false}
              onChange={(e) => setEditing((p) => ({ ...p, is_active: e.target.checked }))}
            />
            Poste actif (proposé lors de l’affectation des membres)
          </label>
        </Modal>
      )}
    </>
  )
}
