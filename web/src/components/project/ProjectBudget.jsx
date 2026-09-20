import { useState } from 'react'
import api, { errorMessage } from '../../lib/api'
import { useToast } from '../../context/ToastContext'
import { ProgressBar } from '../ui'
import { fmtDate } from '../../lib/format'
import JqDataTable from '../DataTable'
import { rowActions } from '../../lib/rowActions'
import { IconPlus } from '../Icons'

const fmtMoney = (v) => (v === null || v === undefined ? '—' : `${Number(v).toLocaleString('fr-FR')} FCFA`)

export default function ProjectBudget({ project, canManage, onChanged }) {
  const toast = useToast()
  const [form, setForm] = useState({ title: '', amount: '', date: '', note: '' })

  const addExpense = async (e) => {
    e.preventDefault()
    try {
      await api.post(`/projects/${project.id}/expenses`, { ...form, amount: Number(form.amount) })
      toast.success('Dépense enregistrée.')
      setForm({ title: '', amount: '', date: '', note: '' })
      onChanged()
    } catch (err) {
      toast.error(errorMessage(err))
    }
  }

  const removeExpense = async (e) => {
    if (!confirm(`Supprimer la dépense « ${e.title} » ?`)) return
    await api.delete(`/expenses/${e.id}`)
    onChanged()
  }

  const columns = [
    { title: 'Date', data: 'date', render: (v) => fmtDate(v) },
    { title: 'Libellé', data: 'title', render: (v) => `<span class="cell-strong">${v}</span>` },
    { title: 'Montant', data: 'amount', render: (v) => fmtMoney(v) },
    { title: 'Note', data: 'note', render: (v) => v || '—' },
    canManage && {
      title: '',
      data: null,
      orderable: false,
      searchable: false,
      render: () => rowActions([{ act: 'delete' }]),
    },
  ].filter(Boolean)

  const pct = project.budget_consumed_pct

  return (
    <>
      <div className="card card-pad">
        <div className="kpi-row" style={{ marginBottom: 0 }}>
          <div className="kpi">
            <div className="kpi__body">
              <div className="kpi__value">{fmtMoney(project.budget_previsionnel)}</div>
              <div className="kpi__label">Budget prévisionnel</div>
            </div>
          </div>
          <div className="kpi">
            <div className="kpi__body">
              <div className="kpi__value">{fmtMoney(project.depenses_engagees)}</div>
              <div className="kpi__label">Dépenses engagées</div>
            </div>
          </div>
          <div className="kpi">
            <div className="kpi__body">
              <div className="kpi__value">{pct === null ? '—' : `${pct}%`}</div>
              <div className="kpi__label">Consommé</div>
            </div>
          </div>
        </div>
        {pct !== null && (
          <div style={{ marginTop: 12, maxWidth: 400 }}>
            <ProgressBar value={pct} />
            {pct > 100 && <p className="faint" style={{ color: 'var(--red)', marginTop: 6 }}>Dépassement budgétaire.</p>}
          </div>
        )}
      </div>

      <div className="card card-pad" style={{ marginTop: 16 }}>
        <h3 style={{ marginTop: 0 }}>Dépenses ({project.expenses?.length || 0})</h3>
        <JqDataTable
          columns={columns}
          rows={project.expenses || []}
          onAction={(act, row) => act === 'delete' && removeExpense(row)}
          options={{ order: [[0, 'desc']] }}
        />

        {canManage && (
          <form onSubmit={addExpense} className="inline-form" style={{ marginTop: 16 }}>
            <label className="field" style={{ margin: 0, minWidth: 200 }}>
              <span className="faint">Libellé</span>
              <input className="input" value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} required />
            </label>
            <label className="field" style={{ margin: 0, minWidth: 140 }}>
              <span className="faint">Montant (FCFA)</span>
              <input className="input" type="number" min="0" step="0.01" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} required />
            </label>
            <label className="field" style={{ margin: 0 }}>
              <span className="faint">Date</span>
              <input className="input" type="date" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} required />
            </label>
            <label className="field" style={{ margin: 0, minWidth: 200 }}>
              <span className="faint">Note (facultatif)</span>
              <input className="input" value={form.note} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))} />
            </label>
            <button className="btn btn-primary" type="submit">
              <IconPlus width={15} height={15} /> Enregistrer
            </button>
          </form>
        )}
      </div>
    </>
  )
}
