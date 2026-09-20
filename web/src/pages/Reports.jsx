import { useEffect, useMemo, useState } from 'react'
import api from '../lib/api'
import { Spinner } from '../components/ui'
import PageHeader from '../components/PageHeader'
import FilterBar from '../components/FilterBar'
import JqDataTable from '../components/DataTable'
import { matchQuery } from '../lib/filter'
import { BarChartCard, LineChartCard, CHART_COLORS } from '../components/charts'
import { IconDone, IconTrend, IconStar, IconUsers } from '../components/Icons'

const HIDE_DT_SEARCH = { layout: { topEnd: null } }

const MENTION = (s) =>
  s == null ? ['—', 'badge-grey'] : s >= 16 ? ['Très bien', 'badge-green'] : s >= 14 ? ['Bien', 'badge-blue'] : s >= 12 ? ['Assez bien', 'badge-amber'] : s >= 10 ? ['Passable', 'badge-amber'] : ['Insuffisant', 'badge-red']

function Kpi({ icon: Icon, tone, label, value }) {
  return (
    <div className="kpi">
      <span className={`kpi__icon kpi__icon--${tone}`}>
        <Icon width={20} height={20} />
      </span>
      <div>
        <div className="kpi__value">{value}</div>
        <div className="kpi__label">{label}</div>
      </div>
    </div>
  )
}

function CardHead({ title, caption }) {
  return (
    <div className="spread" style={{ marginBottom: 10 }}>
      <h3 style={{ margin: 0 }}>{title}</h3>
      {caption && <span className="faint">{caption}</span>}
    </div>
  )
}

export default function Reports() {
  const [departments, setDepartments] = useState([])
  const [departmentId, setDepartmentId] = useState('')
  const [data, setData] = useState(null)
  const [query, setQuery] = useState('')

  useEffect(() => {
    api.get('/departments').then(({ data }) => setDepartments(data.data)).catch(() => {})
  }, [])

  useEffect(() => {
    setData(null)
    api
      .get('/reports/series', { params: departmentId ? { department_id: departmentId } : {} })
      .then(({ data }) => setData(data))
      .catch(() => setData(false))
  }, [departmentId])

  const monthly = useMemo(
    () => (data && data !== false ? data.monthly.map((m) => ({ ...m, mois: m.month?.slice(5) })) : []),
    [data],
  )

  const kpis = useMemo(() => {
    if (!data || data === false) return null
    const done = data.monthly.reduce((s, m) => s + (m.completed || 0), 0)
    const onTime = data.monthly.reduce((s, m) => s + (m.on_time || 0), 0)
    const scores = (data.by_department || []).filter((d) => d.avg_score != null)
    const avg = scores.length ? scores.reduce((s, d) => s + d.avg_score, 0) / scores.length : null
    return {
      done,
      punctuality: done ? Math.round((onTime / done) * 100) : null,
      avg: avg != null ? Math.round(avg * 10) / 10 : null,
      people: (data.by_user || []).length,
    }
  }, [data])

  const deptRanked = useMemo(
    () => [...((data && data !== false && data.by_department) || [])].sort((a, b) => (b.avg_score ?? -1) - (a.avg_score ?? -1)),
    [data],
  )

  const userRows = useMemo(() => {
    if (!data || data === false) return []
    return (data.by_user || []).filter((u) => matchQuery(query, u.name))
  }, [data, query])

  const userColumns = [
    { title: 'Collaborateur', data: 'name', render: (v) => `<span class="cell-strong">${v}</span>` },
    { title: 'Terminées', data: 'completed', className: 'dt-center' },
    { title: 'Note moy.', data: 'avg_score', className: 'dt-center', render: (v) => (v != null ? `${v}/20` : '—') },
    { title: 'Ponctualité', data: 'on_time_rate', className: 'dt-center', render: (v) => (v != null ? `${v}%` : '—') },
  ]

  return (
    <>
      <PageHeader
        title="Rapports & bilan"
        subtitle="Historique conservé : évolution mensuelle, ponctualité, notes moyennes, classement."
        actions={
          <select className="select" style={{ maxWidth: 260 }} value={departmentId} onChange={(e) => setDepartmentId(e.target.value)}>
            <option value="">Tous les départements</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>{d.name}</option>
            ))}
          </select>
        }
      />

      {data === null ? (
        <Spinner />
      ) : data === false ? (
        <p className="muted">Impossible de charger les rapports.</p>
      ) : (
        <>
          <div className="kpi-row">
            <Kpi icon={IconDone} tone="green" label="Terminées (6 mois)" value={kpis.done} />
            <Kpi icon={IconTrend} tone="blue" label="Ponctualité" value={kpis.punctuality != null ? `${kpis.punctuality}%` : '—'} />
            <Kpi icon={IconStar} tone="orange" label="Note moyenne" value={kpis.avg != null ? `${kpis.avg}/20` : '—'} />
            <Kpi icon={IconUsers} tone="amber" label="Collaborateurs évalués" value={kpis.people} />
          </div>

          <div className="dash-grid">
            <div className="card card-pad">
              <CardHead title="Évolution mensuelle" caption="tâches terminées" />
              {kpis.done ? (
                <BarChartCard
                  data={monthly}
                  xKey="mois"
                  stacked
                  height={260}
                  bars={[
                    { key: 'on_time', label: 'Dans les délais', color: CHART_COLORS.green },
                    { key: 'late', label: 'En retard', color: CHART_COLORS.red },
                  ]}
                />
              ) : (
                <p className="faint">Aucune tâche terminée sur la période.</p>
              )}
            </div>

            <div className="card card-pad">
              <CardHead title="Ponctualité" caption="% dans les délais" />
              {kpis.done ? (
                <LineChartCard
                  data={monthly}
                  xKey="mois"
                  height={260}
                  lines={[{ key: 'on_time_rate', label: 'Ponctualité', color: CHART_COLORS.blue }]}
                />
              ) : (
                <p className="faint">Aucune donnée.</p>
              )}
            </div>
          </div>

          <div className="card card-pad" style={{ marginTop: 16 }}>
            <CardHead title="Classement des départements" caption="note moyenne / 20" />
            {deptRanked.length && deptRanked.some((d) => d.avg_score != null) ? (
              <div className="score-list">
                {deptRanked.map((d, i) => {
                  const [mLabel, mCls] = MENTION(d.avg_score)
                  return (
                    <div className="score-list__row" key={d.department_id}>
                      <span className="score-list__rank">{i + 1}</span>
                      <span className="score-list__name">{d.department}</span>
                      <span className="score-list__bar">
                        <span style={{ width: `${((d.avg_score ?? 0) / 20) * 100}%` }} />
                      </span>
                      <span className="score-list__val">{d.avg_score != null ? `${d.avg_score}/20` : '—'}</span>
                      <span className={`badge ${mCls}`}>{mLabel}</span>
                    </div>
                  )
                })}
              </div>
            ) : (
              <p className="faint">Aucune évaluation sur la période.</p>
            )}
          </div>

          <div style={{ marginTop: 16 }}>
            <h3 style={{ marginBottom: 10 }}>Classement des collaborateurs</h3>
            <FilterBar
              query={query}
              onQuery={setQuery}
              placeholder="Rechercher un collaborateur…"
              count={userRows.length}
              total={(data.by_user || []).length}
              resultNoun="collaborateur"
            />
            <JqDataTable
              columns={userColumns}
              rows={userRows}
              options={{ order: [[2, 'desc']], rowId: 'user_id', pageLength: 10, ...HIDE_DT_SEARCH }}
            />
          </div>
        </>
      )}
    </>
  )
}
