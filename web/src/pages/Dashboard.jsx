import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useMeta } from '../context/MetaContext'
import { Spinner } from '../components/ui'
import PageHeader from '../components/PageHeader'
import JqDataTable from '../components/DataTable'
import { rowActions } from '../lib/rowActions'
import { AreaTrend, ChartLegend, DonutChart, HBarChart, CHART_COLORS } from '../components/charts'
import {
  IconTasks,
  IconTimer,
  IconAlert,
  IconDone,
  IconTrend,
  IconStar,
} from '../components/Icons'

const STATUS_COLOR = {
  brouillon: CHART_COLORS.slate,
  assignee: CHART_COLORS.blue,
  en_cours: CHART_COLORS.amber,
  livree: '#5b7bd6',
  validee: CHART_COLORS.green,
  a_refaire: CHART_COLORS.red,
}

function Kpi({ icon: Icon, tone, label, value, hint }) {
  return (
    <div className="kpi">
      <span className={`kpi__icon kpi__icon--${tone}`}>
        <Icon width={20} height={20} />
      </span>
      <div className="kpi__body">
        <div className="kpi__value">{value}</div>
        <div className="kpi__label">{label}</div>
        {hint && <div className="kpi__hint">{hint}</div>}
      </div>
    </div>
  )
}

export default function Dashboard() {
  const { user, isSupervisor, isChef } = useAuth()
  const { labelOf } = useMeta()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [series, setSeries] = useState(null)

  useEffect(() => {
    api.get('/dashboard').then(({ data }) => setData(data)).catch(() => setData(false))
  }, [])

  useEffect(() => {
    if (isSupervisor || isChef) {
      api.get('/reports/series').then(({ data }) => setSeries(data)).catch(() => setSeries(false))
    }
  }, [isSupervisor, isChef])

  if (data === null) return <Spinner />
  if (data === false) return <p className="muted">Impossible de charger le tableau de bord.</p>

  const donutData = Object.entries(data.status_counts || {})
    .filter(([, n]) => n > 0)
    .map(([value, n]) => ({ name: labelOf('task_statuses', value), value: n, color: STATUS_COLOR[value] }))

  const monthly = (series && series !== false ? series.monthly : []).map((m) => ({
    ...m,
    mois: m.month?.slice(5),
  }))

  const deptColumns = [
    { title: 'Département', data: 'name', render: (v) => `<span class="cell-strong">${v}</span>` },
    { title: 'Membres', data: 'members_count', className: 'dt-center' },
    { title: 'Tâches', data: 'tasks_count', className: 'dt-center' },
    { title: 'En cours', data: 'open_tasks_count', className: 'dt-center' },
    {
      title: 'En retard',
      data: 'overdue_tasks_count',
      className: 'dt-center',
      render: (v) => (v > 0 ? `<span class="badge badge-red">${v}</span>` : '0'),
    },
    { title: '', data: null, orderable: false, searchable: false, render: () => rowActions([{ act: 'view' }]) },
  ]

  return (
    <>
      <PageHeader
        title={`Bonjour ${user?.name?.split(' ')[0] || ''}`}
        subtitle={
          data.scope === 'global'
            ? 'Vue d’ensemble de tous les départements de Wycliffe Bénin.'
            : data.scope === 'departments'
              ? 'Activité de vos départements.'
              : 'Vos tâches en cours.'
        }
        actions={<Link to="/taches" className="btn btn-blue">Voir les tâches</Link>}
      />

      <div className="kpi-row">
        <Kpi icon={IconTasks} tone="blue" label="Tâches" value={data.totals.tasks} />
        <Kpi icon={IconTimer} tone="amber" label="En cours" value={data.totals.open} />
        <Kpi
          icon={IconAlert}
          tone="red"
          label="En retard"
          value={data.totals.overdue}
          hint={data.totals.tasks ? `${Math.round((data.totals.overdue / data.totals.tasks) * 100)}% du total` : null}
        />
        <Kpi icon={IconDone} tone="green" label="Terminées" value={data.totals.completed} />
        <Kpi
          icon={IconTrend}
          tone="blue"
          label="Ponctualité"
          value={data.on_time_rate != null ? `${data.on_time_rate}%` : '—'}
        />
        <Kpi
          icon={IconStar}
          tone="orange"
          label="Note moyenne"
          value={data.average_score != null ? `${data.average_score}/20` : '—'}
        />
      </div>

      <div className="dash-grid">
        <div className="card card-pad">
          <div className="spread" style={{ marginBottom: 6 }}>
            <h3 style={{ margin: 0 }}>Évolution mensuelle</h3>
            <span className="faint">tâches terminées</span>
          </div>
          {series === null && (isSupervisor || isChef) ? (
            <Spinner label=" " />
          ) : monthly.length ? (
            <AreaTrend
              data={monthly}
              xKey="mois"
              areas={[
                { key: 'on_time', label: 'Dans les délais', color: CHART_COLORS.green },
                { key: 'late', label: 'En retard', color: CHART_COLORS.red },
              ]}
            />
          ) : (
            <p className="faint">Pas encore assez d’historique.</p>
          )}
        </div>

        <div className="card card-pad">
          <h3 style={{ marginTop: 0 }}>Répartition par statut</h3>
          {donutData.length ? (
            <>
              <DonutChart data={donutData} centerLabel="tâches" />
              <ChartLegend items={donutData.map((d) => ({ label: d.name, color: d.color, value: d.value }))} />
            </>
          ) : (
            <p className="faint">Aucune tâche.</p>
          )}
        </div>
      </div>

      {(isSupervisor || isChef) && data.per_department?.length > 0 && (
        <>
          <div className="card card-pad" style={{ marginTop: 16 }}>
            <h3 style={{ marginTop: 0 }}>Charge par département</h3>
            <HBarChart
              data={data.per_department}
              yKey="name"
              bars={[
                { key: 'open_tasks_count', label: 'En cours', color: CHART_COLORS.amber, stack: true },
                { key: 'overdue_tasks_count', label: 'En retard', color: CHART_COLORS.red, stack: true },
              ]}
              height={Math.max(180, data.per_department.length * 46)}
            />
          </div>

          <div style={{ marginTop: 16 }}>
            <h3 style={{ marginBottom: 8 }}>Détail par département</h3>
            <JqDataTable
              columns={deptColumns}
              rows={data.per_department}
              onAction={(act, row) => act === 'view' && navigate(`/departements/${row.id}`)}
              options={{ order: [[0, 'asc']], paging: false, info: false, searching: false }}
            />
          </div>
        </>
      )}
    </>
  )
}
