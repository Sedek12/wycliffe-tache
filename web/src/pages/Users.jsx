import { useCallback, useEffect, useMemo, useState } from 'react'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useMeta } from '../context/MetaContext'
import { useToast } from '../context/ToastContext'
import { Field, Modal, Spinner } from '../components/ui'
import JqDataTable from '../components/DataTable'
import FilterBar from '../components/FilterBar'
import PageHeader from '../components/PageHeader'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { IconPlus, IconUpload } from '../components/Icons'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
const initials = (name = '') =>
  name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || 'U'

export default function Users() {
  const { isDirecteur, isAdmin } = useAuth()
  const { meta } = useMeta()
  const toast = useToast()
  const canManage = isDirecteur || isAdmin

  const [users, setUsers] = useState(null)
  const [departments, setDepartments] = useState([])
  const [editing, setEditing] = useState(null)
  const [query, setQuery] = useState('')
  const [dept, setDept] = useState('')
  const [status, setStatus] = useState('')

  const load = useCallback(() => {
    api.get('/users', { params: { per_page: 500 } }).then(({ data }) => setUsers(data.data)).catch(() => setUsers([]))
  }, [])

  useEffect(() => {
    load()
    api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => {})
  }, [load])

  const rows = useMemo(() => {
    if (!users) return []
    return users.filter((u) => {
      if (status === 'active' && !u.is_active) return false
      if (status === 'inactive' && u.is_active) return false
      if (dept && !(u.departments || []).some((d) => String(d.id) === String(dept))) return false
      return matchQuery(query, u.name, u.email, u.phone, (u.departments || []).map((d) => [d.name, d.role]))
    })
  }, [users, query, dept, status])

  const save = async (fd, id) => {
    if (id) {
      fd.append('_method', 'PUT')
      await api.post(`/users/${id}`, fd)
    } else {
      await api.post('/users', fd)
    }
    toast.success(id ? 'Employé mis à jour.' : 'Employé enregistré.')
    setEditing(null)
    load()
  }

  const deactivate = async (u) => {
    if (!confirm(`Désactiver le compte de ${u.name} ?`)) return
    await api.delete(`/users/${u.id}`)
    toast.success('Compte désactivé.')
    load()
  }

  const columns = [
    {
      title: 'Employé',
      data: 'name',
      render: (v, _t, r) =>
        `<span class="dt-avatar">${esc(initials(v))}</span><span class="cell-strong">${esc(v)}</span>` +
        (r.gender ? ` <span class="faint">(${esc(r.gender)})</span>` : ''),
    },
    {
      title: 'Contact',
      data: 'email',
      render: (v, _t, r) => `<div>${esc(v)}</div>` + (r.phone ? `<div class="faint">${esc(r.phone)}</div>` : ''),
    },
    {
      title: 'Département / poste',
      data: null,
      render: (_d, _t, r) =>
        (r.departments || []).length
          ? (r.departments || []).map((d) => `<div>${esc(d.name)} <span class="faint">· ${esc(d.role)}</span></div>`).join('')
          : '<span class="faint">—</span>',
    },
    {
      title: 'Statut',
      data: 'is_active',
      render: (v) => (v ? '<span class="badge badge-green">actif</span>' : '<span class="badge badge-grey">inactif</span>'),
    },
    canManage && {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: (_d, _t, r) => rowActions([{ act: 'edit' }, r.is_active && { act: 'toggle', on: true }]),
    },
  ].filter(Boolean)

  const onAction = (act, row) => {
    if (act === 'edit') setEditing(row)
    if (act === 'toggle') deactivate(row)
  }

  if (users === null) return <Spinner />

  return (
    <>
      <PageHeader
        title="Employés"
        subtitle="Enregistrer un employé, un stagiaire ou un prestataire et l’affecter à un département sous un poste."
        actions={
          canManage && (
            <button className="btn btn-primary" onClick={() => setEditing({})}>
              <IconPlus width={16} height={16} /> Nouvel employé
            </button>
          )
        }
      />

      <FilterBar
        query={query}
        onQuery={setQuery}
        placeholder="Rechercher un nom, un e-mail, un téléphone…"
        filters={[
          {
            key: 'dept',
            label: 'Département',
            value: dept,
            onChange: setDept,
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
        total={users.length}
        resultNoun="employé"
      />

      <JqDataTable
        columns={columns}
        rows={rows}
        onAction={onAction}
        options={{ order: [[0, 'asc']], ...HIDE_DT_SEARCH }}
      />

      {editing && (
        <EmployeeForm
          value={editing}
          departmentRoles={meta?.department_roles || []}
          departments={departments}
          onClose={() => setEditing(null)}
          onSave={save}
        />
      )}
    </>
  )
}

// Découpe un nom complet en prénom / 2e prénom / nom quand les champs dédiés sont vides.
function splitName(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return { first_name: '', middle_name: '', last_name: '' }
  if (parts.length === 1) return { first_name: parts[0], middle_name: '', last_name: '' }
  return { first_name: parts[0], last_name: parts[parts.length - 1], middle_name: parts.slice(1, -1).join(' ') }
}

function EmployeeForm({ value, departmentRoles, departments, onClose, onSave }) {
  const editing = Boolean(value.id)
  const guess = splitName(value.name)
  const firstDept = value.departments?.[0]

  const [form, setForm] = useState({
    last_name: value.last_name || guess.last_name,
    first_name: value.first_name || guess.first_name,
    middle_name: value.middle_name || guess.middle_name,
    birth_date: value.birth_date || '',
    gender: value.gender || '',
    email: value.email || '',
    phone: value.phone || '',
    phone_secondary: value.phone_secondary || '',
    is_active: value.is_active ?? true,
    department_id: firstDept?.id ? String(firstDept.id) : '',
    department_role: firstDept?.role || 'employe',
    position_id: firstDept?.position_id ? String(firstDept.position_id) : '',
  })
  const [photo, setPhoto] = useState(null)
  const [photoPreview, setPhotoPreview] = useState(value.avatar_url || null)
  const [positions, setPositions] = useState([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const set = (k) => (e) =>
    setForm((f) => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  useEffect(() => {
    if (!form.department_id) {
      setPositions([])
      return
    }
    api
      .get(`/departments/${form.department_id}/positions`)
      .then(({ data }) => setPositions(data.data.filter((p) => p.is_active !== false)))
      .catch(() => setPositions([]))
  }, [form.department_id])

  const selectedDept = departments.find((d) => String(d.id) === String(form.department_id))
  const chefAlreadyTaken =
    selectedDept?.has_chef && !(editing && firstDept?.id === selectedDept.id && firstDept?.role === 'chef')
  const roleOptions = departmentRoles.filter((r) => !(r.value === 'chef' && chefAlreadyTaken))

  useEffect(() => {
    if (form.department_role === 'chef' && chefAlreadyTaken) {
      setForm((f) => ({ ...f, department_role: 'employe' }))
    }
  }, [chefAlreadyTaken, form.department_role])

  const onPhoto = (e) => {
    const file = e.target.files[0]
    if (!file) return
    setPhoto(file)
    setPhotoPreview(URL.createObjectURL(file))
  }

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    if (!form.gender) return setError('Merci d’indiquer le sexe.')
    setBusy(true)
    const fd = new FormData()
    fd.append('last_name', form.last_name.trim())
    fd.append('first_name', form.first_name.trim())
    if (form.middle_name.trim()) fd.append('middle_name', form.middle_name.trim())
    fd.append('birth_date', form.birth_date)
    fd.append('gender', form.gender)
    fd.append('email', form.email.trim())
    fd.append('phone', form.phone.trim())
    if (form.phone_secondary.trim()) fd.append('phone_secondary', form.phone_secondary.trim())
    fd.append('is_active', form.is_active ? '1' : '0')
    if (photo) fd.append('avatar', photo)
    if (form.department_id) {
      fd.append('department_id', form.department_id)
      fd.append('department_role', form.department_role)
      if (form.position_id) fd.append('position_id', form.position_id)
    }
    try {
      await onSave(fd, value.id)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      title={editing ? 'Modifier l’employé' : 'Nouvel employé'}
      onClose={onClose}
      wide
      footer={
        <>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary" form="employee-form" disabled={busy}>
            {busy ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer l’employé'}
          </button>
        </>
      }
    >
      <form id="employee-form" onSubmit={submit}>
        <div className="form-section">
          <div className="form-section__title">Identité</div>

          <div className="photo-pick">
            {photoPreview ? (
              <img className="photo-pick__preview" src={photoPreview} alt="" />
            ) : (
              <span className="photo-pick__preview">Photo</span>
            )}
            <label className="btn btn-ghost btn-sm">
              <IconUpload width={15} height={15} />
              {photo || value.avatar_url ? ' Changer la photo' : ' Ajouter une photo'}
              <input type="file" accept="image/*" hidden onChange={onPhoto} />
            </label>
            <span className="faint">Facultatif — JPG / PNG, 4 Mo max.</span>
          </div>

          <div className="form-grid form-grid--2">
            <Field label={<>Nom <span className="req">*</span></>}>
              <input className="input" value={form.last_name} onChange={set('last_name')} required autoComplete="family-name" />
            </Field>
            <Field label={<>Prénom <span className="req">*</span></>}>
              <input className="input" value={form.first_name} onChange={set('first_name')} required autoComplete="given-name" />
            </Field>
          </div>
          <div className="form-grid form-grid--3" style={{ marginTop: 14 }}>
            <Field label="Deuxième prénom">
              <input className="input" value={form.middle_name} onChange={set('middle_name')} />
            </Field>
            <Field label={<>Date de naissance <span className="req">*</span></>}>
              <input
                className="input"
                type="date"
                value={form.birth_date}
                onChange={set('birth_date')}
                max={new Date().toISOString().slice(0, 10)}
                required
              />
            </Field>
            <Field label={<>Sexe <span className="req">*</span></>}>
              <div className="seg-choice">
                <button type="button" className={form.gender === 'M' ? 'is-on' : ''} onClick={() => setForm((f) => ({ ...f, gender: 'M' }))}>
                  Masculin
                </button>
                <button type="button" className={form.gender === 'F' ? 'is-on' : ''} onClick={() => setForm((f) => ({ ...f, gender: 'F' }))}>
                  Féminin
                </button>
              </div>
            </Field>
          </div>
        </div>

        <div className="form-section">
          <div className="form-section__title">Contact</div>
          <Field label={<>Adresse e-mail <span className="req">*</span></>}>
            <input className="input" type="email" value={form.email} onChange={set('email')} required autoComplete="email" />
          </Field>
          <div className="form-grid form-grid--2">
            <Field label={<>Téléphone principal <span className="req">*</span></>}>
              <input className="input" type="tel" value={form.phone} onChange={set('phone')} required placeholder="+229 …" />
            </Field>
            <Field label="Téléphone secondaire" hint="Facultatif">
              <input className="input" type="tel" value={form.phone_secondary} onChange={set('phone_secondary')} placeholder="+229 …" />
            </Field>
          </div>
        </div>

        <div className="form-section">
          <div className="form-section__title">Affectation au département</div>
          <div className="form-grid form-grid--3">
            <Field label="Département">
              <select className="select" value={form.department_id} onChange={set('department_id')}>
                <option value="">— aucun —</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </Field>
            <Field
              label="Statut dans le département"
              hint={chefAlreadyTaken ? 'Ce département a déjà un chef.' : undefined}
            >
              <select className="select" value={form.department_role} onChange={set('department_role')} disabled={!form.department_id}>
                {roleOptions.map((r) => (
                  <option key={r.value} value={r.value}>{r.label}</option>
                ))}
              </select>
            </Field>
            <Field
              label="Poste"
              hint={form.department_id && positions.length === 0 ? 'Aucun poste actif dans ce département.' : undefined}
            >
              <select
                className="select"
                value={form.position_id}
                onChange={set('position_id')}
                disabled={!form.department_id || positions.length === 0}
              >
                <option value="">— aucun —</option>
                {positions.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </Field>
          </div>
          <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.9rem', marginTop: 14 }}>
            <input type="checkbox" checked={form.is_active} onChange={set('is_active')} /> Compte actif
          </label>
        </div>

        {error && <div className="field-error">{error}</div>}
        {!editing && (
          <p className="faint" style={{ marginTop: 4 }}>
            Un mot de passe provisoire est généré automatiquement à la création du compte.
          </p>
        )}
      </form>
    </Modal>
  )
}
