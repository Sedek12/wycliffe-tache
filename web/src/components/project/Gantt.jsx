import { fmtDate } from '../../lib/format'

const DAY = 24 * 60 * 60 * 1000

function toTs(value) {
  if (!value) return null
  const t = new Date(value).getTime()
  return Number.isNaN(t) ? null : t
}

/**
 * Diagramme de Gantt léger, sans dépendance externe : positionnement en
 * pourcentage via CSS (pas de librairie de planning).
 * props :
 *  - activities : tâches racine (avec sub_tasks, status, starts_at, due_at, depends_on)
 *  - milestones : [{ id, title, date, is_reached }]
 */
export default function Gantt({ activities = [], milestones = [] }) {
  const rows = []
  activities.forEach((a) => {
    rows.push({ ...a, depth: 0 })
    ;(a.sub_tasks || []).forEach((s) => rows.push({ ...s, depth: 1 }))
  })

  const allDates = [
    ...rows.flatMap((r) => [toTs(r.starts_at ?? r.due_at), toTs(r.due_at)]),
    ...milestones.map((m) => toTs(m.date)),
  ].filter(Boolean)

  if (allDates.length === 0) {
    return <p className="faint">Aucune date renseignée sur les activités ou jalons — rien à tracer.</p>
  }

  const rangeStart = Math.min(...allDates) - 2 * DAY
  const rangeEnd = Math.max(...allDates) + 2 * DAY
  const total = Math.max(rangeEnd - rangeStart, DAY)
  const pct = (ts) => Math.min(100, Math.max(0, ((ts - rangeStart) / total) * 100))

  // Graduations mensuelles simples.
  const ticks = []
  const cursor = new Date(rangeStart)
  cursor.setDate(1)
  while (cursor.getTime() < rangeEnd) {
    ticks.push(new Date(cursor))
    cursor.setMonth(cursor.getMonth() + 1)
  }

  return (
    <div className="gantt">
      <div className="gantt__ticks">
        {ticks.map((t) => (
          <span key={t.toISOString()} style={{ left: `${pct(t.getTime())}%` }}>
            {t.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' })}
          </span>
        ))}
      </div>

      <div className="gantt__body">
        {rows.map((r) => {
          const start = toTs(r.starts_at) ?? toTs(r.due_at) ?? rangeStart
          const end = toTs(r.due_at) ?? start
          const left = pct(start)
          const width = Math.max(1.5, pct(end) - left)
          const blocked = (r.depends_on || []).filter((d) => d.status !== 'validee')

          return (
            <div key={r.id} className="gantt__row">
              <div className="gantt__label" style={{ paddingLeft: r.depth * 16 }}>
                {r.title}
              </div>
              <div className="gantt__track">
                <div
                  className={`gantt__bar gantt__bar--${r.status}`}
                  style={{ left: `${left}%`, width: `${width}%` }}
                  title={`${r.title} — ${fmtDate(r.starts_at)} → ${fmtDate(r.due_at)}`}
                />
                {blocked.length > 0 && (
                  <span className="gantt__blocked" style={{ left: `${left}%` }}>
                    ⛔ Bloquée par : {blocked.map((b) => b.title).join(', ')}
                  </span>
                )}
              </div>
            </div>
          )
        })}

        {milestones.map((m) => (
          <div key={`m-${m.id}`} className="gantt__row gantt__row--milestone">
            <div className="gantt__label">🚩 {m.title}</div>
            <div className="gantt__track">
              <span
                className={`gantt__milestone ${m.is_reached ? 'is-reached' : ''}`}
                style={{ left: `${pct(toTs(m.date))}%` }}
                title={`${m.title} — ${fmtDate(m.date)}`}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
