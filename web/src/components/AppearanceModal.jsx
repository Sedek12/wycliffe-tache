import { useState } from 'react'
import { Modal } from './ui'
import {
  SIDEBAR_COLORS,
  PAGE_COLORS,
  DEFAULT_SIDEBAR,
  DEFAULT_PAGE,
  getSavedTheme,
  saveTheme,
  resetTheme,
  applyTheme,
} from '../lib/theme'

function Swatch({ color, active, onClick, label }) {
  return (
    <button
      type="button"
      className="swatch"
      onClick={onClick}
      title={label}
      aria-label={label}
      aria-pressed={active}
      style={{ '--swatch-color': color, borderColor: active ? 'var(--wy-blue-600)' : 'transparent' }}
    >
      <span />
      {active && <span className="swatch__check">✓</span>}
    </button>
  )
}

export default function AppearanceModal({ onClose }) {
  const saved = getSavedTheme()
  const [sidebar, setSidebar] = useState(saved.sidebar || DEFAULT_SIDEBAR)
  const [page, setPage] = useState(saved.page || DEFAULT_PAGE)

  // Aperçu en direct pendant qu'on choisit ; annuler restaure la préférence précédente.
  const preview = (next) => applyTheme(next)

  const pick = (kind, value) => {
    const next = { sidebar: kind === 'sidebar' ? value : sidebar, page: kind === 'page' ? value : page }
    if (kind === 'sidebar') setSidebar(value)
    else setPage(value)
    preview(next)
  }

  const save = () => {
    // Si les deux choix correspondent aux valeurs par défaut, on efface la préférence
    // au lieu d'enregistrer ces couleurs en dur — sinon ça fige le thème clair même
    // pour quelqu'un dont le système est en mode sombre.
    if (sidebar === DEFAULT_SIDEBAR && page === DEFAULT_PAGE) {
      resetTheme()
    } else {
      saveTheme({ sidebar, page })
    }
    onClose()
  }

  const reset = () => {
    setSidebar(DEFAULT_SIDEBAR)
    setPage(DEFAULT_PAGE)
    resetTheme()
    onClose()
  }

  const cancel = () => {
    applyTheme(saved) // annule l'aperçu si non enregistré
    onClose()
  }

  return (
    <Modal
      title="Apparence"
      onClose={cancel}
      footer={
        <>
          <button className="btn btn-ghost" onClick={reset}>Réinitialiser</button>
          <button className="btn btn-ghost" onClick={cancel}>Annuler</button>
          <button className="btn btn-primary" onClick={save}>Enregistrer</button>
        </>
      }
    >
      <p className="faint" style={{ marginTop: 0 }}>
        Personnalise les couleurs de l’interface. Ce choix n’est visible que sur cet appareil.
      </p>

      <div className="form-section">
        <div className="form-section__title">Menu latéral</div>
        <div className="swatch-row">
          {SIDEBAR_COLORS.map((c) => (
            <Swatch key={c.key} color={c.value} label={c.label} active={sidebar === c.value} onClick={() => pick('sidebar', c.value)} />
          ))}
        </div>
      </div>

      <div className="form-section">
        <div className="form-section__title">Fond de page</div>
        <div className="swatch-row">
          {PAGE_COLORS.map((c) => (
            <Swatch key={c.key} color={c.value} label={c.label} active={page === c.value} onClick={() => pick('page', c.value)} />
          ))}
        </div>
      </div>
    </Modal>
  )
}
