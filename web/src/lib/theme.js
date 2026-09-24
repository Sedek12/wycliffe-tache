// Personnalisation de l'apparence (couleur du menu latéral et du fond de page).
// Préférence locale au navigateur (localStorage), sans impact sur les autres utilisateurs.

const STORAGE_KEY = 'wiclif.theme'

// Couleurs par défaut = charte actuelle de l'application. Ne rien choisir = ne rien changer.
export const DEFAULT_SIDEBAR = '#14205c'
export const DEFAULT_PAGE = '#f4f6fb'

export const SIDEBAR_COLORS = [
  { key: 'defaut', label: 'Bleu Wycliffe (défaut)', value: DEFAULT_SIDEBAR },
  { key: 'marine', label: 'Bleu marine', value: '#1e3a5f' },
  { key: 'ardoise', label: 'Gris ardoise', value: '#1f2937' },
  { key: 'foret', label: 'Vert forêt', value: '#14532d' },
  { key: 'violet', label: 'Violet', value: '#3b2170' },
  { key: 'bordeaux', label: 'Bordeaux', value: '#5c1a2e' },
  { key: 'noir', label: 'Noir', value: '#18181b' },
]

// Des teintes assez marquées pour rester bien visibles et distinctes entre elles,
// tout en restant assez claires pour ne pas gêner la lecture du texte/cartes blanches.
export const PAGE_COLORS = [
  { key: 'defaut', label: 'Gris clair (défaut)', value: DEFAULT_PAGE },
  { key: 'blanc', label: 'Blanc pur', value: '#ffffff' },
  { key: 'ciel', label: 'Bleu ciel', value: '#cfe3f7' },
  { key: 'lavande', label: 'Lavande', value: '#dfd6f5' },
  { key: 'menthe', label: 'Menthe', value: '#cdf0dc' },
  { key: 'peche', label: 'Pêche', value: '#fbdcc2' },
  { key: 'rose', label: 'Rose poudré', value: '#f8d2de' },
  { key: 'jaune', label: 'Jaune pâle', value: '#f7edb0' },
  { key: 'turquoise', label: 'Turquoise clair', value: '#c9ecec' },
  { key: 'sable', label: 'Sable', value: '#ecdfc4' },
  { key: 'gris_chaud', label: 'Gris chaud', value: '#e6e2db' },
  { key: 'gris_fonce', label: 'Gris ardoise clair', value: '#d6dbe3' },
]

function readSaved() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

function writeSaved(theme) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(theme))
  } catch {
    /* stockage indisponible (navigation privée…) : la préférence ne sera juste pas retenue */
  }
}

/** Applique les couleurs (ou les valeurs par défaut) sur la page. */
export function applyTheme({ sidebar, page } = {}) {
  const root = document.documentElement
  if (sidebar) root.style.setProperty('--sidebar-bg', sidebar)
  else root.style.removeProperty('--sidebar-bg')

  if (page) root.style.setProperty('--bg', page)
  else root.style.removeProperty('--bg')
}

/** À appeler une fois au démarrage de l'app pour restaurer le choix précédent. */
export function applySavedTheme() {
  applyTheme(readSaved())
}

export function getSavedTheme() {
  return readSaved()
}

export function saveTheme(theme) {
  writeSaved(theme)
  applyTheme(theme)
}

export function resetTheme() {
  writeSaved({})
  applyTheme({})
}
