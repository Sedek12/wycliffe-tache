import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useMeta } from '../context/MetaContext'
import { Spinner, ProgressBar } from '../components/ui'
import JqDataTable from '../components/DataTable'
import FilterBar from '../components/FilterBar'
import PageHeader from '../components/PageHeader'
import ProjectForm from '../components/ProjectForm'
import { matchQuery } from '../lib/filter'
import { rowActions } from '../lib/rowActions'
import { STATUS_BADGE } from '../lib/format'
import { IconPlus } from '../components/Icons'

const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export default function Projects() {
  const { isAdmin, isChef, ledDepartments, managedProjects } = useAuth()
  const { meta } = useMeta()
  const navigate = useNavigate()
  const canCreate = isAdmin || (isChef && ledDepartments.length > 0) || managedProjects.length > 0

  const [items, setItems] = useState(null)
  const [editing, setEditing] = useState(null)
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')

  const load = useCallback(() => {
    api.get('/projects', { params: { per_page: 200 } }).then(({ data }) => setItems(data.data)).catch(() => setItems([]))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const rows = useMemo(() => {
    if (!items) return []
    return items.filter((p) => {
      if (status && p.status !== status) return false
      return matchQuery(query, p.title, p.department?.name)
    })
  }, [items, query, status])

  const onSaved = (project) => navigate(`/projets/${project.id}`)

  const columns = [
    {
      title: 'Projet',
      data: null,
      render: (_d, _t, r) =>
        `<span class="cell-strong">${esc(r.title)}</span>` +
        `<div class="faint">${esc(r.department?.name || '')}</div>`,
    },
    {
      title: 'Statut',
      data: 'status',
      render: (v, _t, r) => `<span class="badge ${STATUS_BADGE[v] || 'badge-grey'}">${esc(r.status_label || v)}</span>`,
    },
    {
      title: 'Avancement',
      data: 'progress',
      render: (v) => `<div style="min-width:110px"><div class="progress" title="${v}%"><span style="width:${Math.min(100, v)}%"></span></div></div>`,
    },
    {
      title: 'Budget consommé',
      data: 'budget_consumed_pct',
      className: 'dt-center',
      render: (v) => (v === null || v === undefined ? '—' : `${v}%`),
    },
    {
      title: 'Risque',
      data: 'risk_flag',
      className: 'dt-center',
      render: (v) => (v ? '<span class="badge badge-red">à risque</span>' : '<span class="badge badge-green">ok</span>'),
    },
    {
      title: 'Actions',
      data: null,
      orderable: false,
      searchable: false,
      render: () => rowActions([{ act: 'view' }]),
    },
  ]

  const onAction = (act, row) => {
    if (act === 'view') navigate(`/projets/${row.id}`)
  }

  if (items === null) return <Spinner />

  return (
    <>
      <PageHeader
        title="Projets"
        subtitle="Plan de Travail Annuel et Budget (PTAB) — cadrage, planification, ressources et suivi des projets."
        actions={
          canCreate && (
            <button className="btn btn-primary" onClick={() => setEditing({})}>
              <IconPlus width={16} height={16} /> Nouveau projet
            </button>
          )
        }
      />

      <FilterBar
        query={query}
        onQuery={setQuery}
        placeholder="Rechercher un projet…"
        filters={[
          {
            key: 'status',
            label: 'Statut',
            value: status,
            onChange: setStatus,
            allLabel: 'Tous les statuts',
            options: (meta?.project_statuses || []).map((s) => ({ value: s.value, label: s.label })),
          },
        ]}
        count={rows.length}
        total={items.length}
        resultNoun="projet"
      />

      <JqDataTable columns={columns} rows={rows} onAction={onAction} options={{ order: [[0, 'asc']] }} />

      {editing && <ProjectForm project={editing} onClose={() => setEditing(null)} onSaved={onSaved} />}
    </>
  )
}
