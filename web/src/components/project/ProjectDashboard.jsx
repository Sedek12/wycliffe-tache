import { ProgressRing } from '../ui'
import { IconTasks, IconAlert, IconDone, IconBudget } from '../Icons'

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

export default function ProjectDashboard({ project }) {
  const activities = project.activities || []
  const total = activities.length
  const overdue = activities.filter((a) => a.is_overdue).length
  const done = activities.filter((a) => a.status === 'validee').length
  const pct = project.budget_consumed_pct

  const byStatus = {}
  activities.forEach((a) => { byStatus[a.status] = (byStatus[a.status] || 0) + 1 })

  return (
    <>
      {project.risk_flag && (
        <div className="card card-pad" style={{ marginBottom: 16, borderLeft: '4px solid var(--red)' }}>
          <strong style={{ color: 'var(--red)' }}>⚠ Projet à risque</strong>
          <p className="faint" style={{ margin: '4px 0 0' }}>
            {overdue > 0 && `${overdue} activité(s) en retard. `}
            {pct !== null && pct > 100 && 'Dépassement budgétaire.'}
          </p>
        </div>
      )}

      <div className="dash-grid">
        <div className="card card-pad" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <ProgressRing value={project.progress} caption="avancement" />
        </div>

        <div className="card card-pad">
          <h3 style={{ marginTop: 0 }}>Indicateurs</h3>
          <div className="kpi-row" style={{ marginTop: 8 }}>
            <Kpi icon={IconTasks} tone="blue" label="Activités" value={total} />
            <Kpi icon={IconDone} tone="green" label="Validées" value={done} />
            <Kpi icon={IconAlert} tone="red" label="En retard" value={overdue} />
            <Kpi icon={IconBudget} tone="orange" label="Budget consommé" value={pct === null ? '—' : `${pct}%`} />
          </div>
        </div>
      </div>

      <div className="card card-pad" style={{ marginTop: 16 }}>
        <h3 style={{ marginTop: 0 }}>Répartition des activités par statut</h3>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 8 }}>
          {Object.entries(byStatus).length === 0 && <p className="faint">Aucune activité.</p>}
          {Object.entries(byStatus).map(([status, n]) => (
            <span key={status} className="badge badge-blue">{status} — {n}</span>
          ))}
        </div>
      </div>
    </>
  )
}
