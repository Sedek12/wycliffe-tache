import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

export const CHART_COLORS = {
  blue: '#2540c4',
  orange: '#f7941e',
  green: '#1f9d63',
  red: '#d64550',
  amber: '#c9860b',
  slate: '#8b95a7',
}

const axisProps = {
  stroke: 'var(--text-faint)',
  fontSize: 12,
  tickLine: false,
  axisLine: false,
}

const tooltipStyle = {
  contentStyle: {
    background: 'var(--surface)',
    border: '1px solid var(--border)',
    borderRadius: 10,
    fontSize: 13,
    color: 'var(--text)',
    boxShadow: '0 8px 24px rgba(20,32,92,0.12)',
  },
  labelStyle: { color: 'var(--text-soft)', fontWeight: 600, marginBottom: 2 },
}

/* ---------- Donut (répartition) ---------- */
export function DonutChart({ data, height = 220, centerLabel, centerValue }) {
  const total = data.reduce((s, d) => s + (d.value || 0), 0)
  return (
    <div style={{ position: 'relative' }}>
      <ResponsiveContainer width="100%" height={height}>
        <PieChart>
          <Tooltip {...tooltipStyle} />
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            innerRadius="62%"
            outerRadius="92%"
            paddingAngle={data.length > 1 ? 2 : 0}
            stroke="var(--surface)"
            strokeWidth={2}
          >
            {data.map((d, i) => (
              <Cell key={i} fill={d.color} />
            ))}
          </Pie>
        </PieChart>
      </ResponsiveContainer>
      <div
        style={{
          position: 'absolute',
          inset: 0,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          pointerEvents: 'none',
        }}
      >
        <div style={{ fontSize: '1.9rem', fontWeight: 800, lineHeight: 1 }}>
          {centerValue ?? total}
        </div>
        <div className="faint" style={{ fontSize: '0.75rem' }}>{centerLabel ?? 'total'}</div>
      </div>
    </div>
  )
}

export function ChartLegend({ items }) {
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px 14px', marginTop: 10 }}>
      {items.map((it) => (
        <span key={it.label} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: '0.8rem', color: 'var(--text-soft)' }}>
          <span style={{ width: 10, height: 10, borderRadius: 3, background: it.color }} />
          {it.label}
          {it.value != null && <strong style={{ color: 'var(--text)' }}>&nbsp;{it.value}</strong>}
        </span>
      ))}
    </div>
  )
}

/* ---------- Aire (évolution) ---------- */
export function AreaTrend({ data, xKey, areas, height = 240 }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data} margin={{ top: 6, right: 6, left: -18, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
        <XAxis dataKey={xKey} {...axisProps} />
        <YAxis {...axisProps} allowDecimals={false} width={34} />
        <Tooltip {...tooltipStyle} />
        {areas.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {areas.map((a) => (
          <Area
            key={a.key}
            type="monotone"
            dataKey={a.key}
            name={a.label}
            stroke={a.color}
            strokeWidth={2.5}
            fill={a.color}
            fillOpacity={0.12}
            dot={false}
            activeDot={{ r: 4 }}
            connectNulls
          />
        ))}
      </AreaChart>
    </ResponsiveContainer>
  )
}

/* ---------- Barres horizontales (charge) ---------- */
export function HBarChart({ data, yKey, bars, height = 260 }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} layout="vertical" margin={{ top: 4, right: 12, left: 8, bottom: 0 }} barSize={16}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />
        <XAxis type="number" {...axisProps} allowDecimals={false} />
        <YAxis type="category" dataKey={yKey} {...axisProps} width={130} />
        <Tooltip {...tooltipStyle} cursor={{ fill: 'var(--surface-2)' }} />
        {bars.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {bars.map((b, i) => (
          <Bar
            key={b.key}
            dataKey={b.key}
            name={b.label}
            fill={b.color}
            stackId={b.stack ? 'a' : undefined}
            radius={i === bars.length - 1 ? [0, 6, 6, 0] : 0}
          />
        ))}
      </BarChart>
    </ResponsiveContainer>
  )
}

/* ---------- Existants (Rapports) ---------- */
export function BarChartCard({ data, xKey, bars, height = 260, stacked }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
        <XAxis dataKey={xKey} {...axisProps} />
        <YAxis {...axisProps} allowDecimals={false} />
        <Tooltip {...tooltipStyle} cursor={{ fill: 'var(--surface-2)' }} />
        {bars.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {bars.map((b) => (
          <Bar
            key={b.key}
            dataKey={b.key}
            name={b.label}
            fill={b.color}
            radius={stacked ? 0 : [4, 4, 0, 0]}
            stackId={stacked ? 'a' : undefined}
          >
            {b.cells && data.map((entry, i) => <Cell key={i} fill={b.cells(entry)} />)}
          </Bar>
        ))}
      </BarChart>
    </ResponsiveContainer>
  )
}

export function LineChartCard({ data, xKey, lines, height = 260 }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <LineChart data={data} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
        <XAxis dataKey={xKey} {...axisProps} />
        <YAxis {...axisProps} allowDecimals={false} />
        <Tooltip {...tooltipStyle} />
        {lines.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {lines.map((l) => (
          <Line key={l.key} type="monotone" dataKey={l.key} name={l.label} stroke={l.color} strokeWidth={2} dot={{ r: 3 }} connectNulls />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
}
