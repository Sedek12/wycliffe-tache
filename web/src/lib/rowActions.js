/**
 * Boutons d'action à icônes pour les cellules DataTables (rendu en HTML).
 * Même jeu d'icônes et mêmes couleurs partout dans l'application.
 *
 * usage : render: () => rowActions([{ act: 'edit' }, { act: 'toggle', on: row.is_active }, { act: 'delete' }])
 */

// Tracés Lucide (viewBox 0 0 24 24) pour rester cohérent avec les icônes React.
const SVG = {
  view: '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
  edit: '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
  delete:
    '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
  power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
}

function icon(name) {
  return (
    `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" ` +
    `stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${SVG[name]}</svg>`
  )
}

const PRESETS = {
  view: { icon: 'view', variant: 'blue', title: 'Ouvrir' },
  edit: { icon: 'edit', variant: 'blue', title: 'Modifier' },
  delete: { icon: 'delete', variant: 'red', title: 'Supprimer' },
  accept: { icon: 'check', variant: 'green', title: 'Accepter' },
  reject: { icon: 'x', variant: 'red', title: 'Refuser' },
  cancel: { icon: 'delete', variant: 'red', title: 'Annuler' },
}

export function rowActions(list) {
  return list
    .filter(Boolean)
    .map((a) => {
      // toggle : couleur/titre selon l'état courant (a.on = actuellement actif)
      if (a.act === 'toggle') {
        const on = a.on !== false
        return button('toggle', 'power', on ? 'amber' : 'green', on ? 'Désactiver' : 'Réactiver')
      }
      const p = PRESETS[a.act] || { icon: 'edit', variant: 'blue', title: a.title || a.act }
      return button(a.act, a.icon || p.icon, a.variant || p.variant, a.title || p.title)
    })
    .join('')
}

function button(act, iconName, variant, title) {
  return (
    `<button type="button" class="dt-ico dt-ico--${variant}" data-act="${act}" ` +
    `title="${title}" aria-label="${title}">${icon(iconName)}</button>`
  )
}
