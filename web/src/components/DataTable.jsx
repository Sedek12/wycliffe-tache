import { useEffect, useRef } from 'react'

// jQuery + DataTables sont chargés via CDN dans index.html (window.jQuery / $.fn.dataTable).

const FRENCH = {
  emptyTable: 'Aucune donnée disponible',
  info: '_START_ à _END_ sur _TOTAL_ entrées',
  infoEmpty: '0 entrée',
  infoFiltered: '(filtré de _MAX_ entrées)',
  lengthMenu: 'Afficher _MENU_ entrées',
  loadingRecords: 'Chargement…',
  processing: 'Traitement…',
  search: 'Rechercher :',
  zeroRecords: 'Aucun résultat',
  paginate: { first: '«', last: '»', next: '›', previous: '‹' },
  aria: { sortAscending: ' : trier par ordre croissant', sortDescending: ' : trier par ordre décroissant' },
}

/**
 * Tableau jQuery DataTables piloté par React.
 *
 * props :
 *  - columns : définitions DataTables ({ title, data, render, orderable, className… })
 *  - rows    : tableau d'objets (chacun doit avoir un `id`)
 *  - onAction(action, rowData) : appelé au clic sur un `[data-act]` dans une cellule
 *  - options : options DataTables supplémentaires
 */
export default function JqDataTable({ columns, rows, onAction, options = {} }) {
  const tableRef = useRef(null)
  const dtRef = useRef(null)
  const onActionRef = useRef(onAction)
  onActionRef.current = onAction

  useEffect(() => {
    const $ = window.jQuery || window.$
    const el = tableRef.current
    if (!$ || !$.fn || !$.fn.dataTable || !el) return

    const safeColumns = columns.map((c) =>
      c && c.data == null && c.defaultContent == null ? { ...c, defaultContent: '' } : c,
    )

    dtRef.current = $(el).DataTable({
      data: rows,
      columns: safeColumns,
      rowId: 'id',
      language: FRENCH,
      order: [],
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      autoWidth: false,
      destroy: true,
      ...options,
    })

    const handler = (e) => {
      const btn = e.target.closest('[data-act]')
      if (!btn || !el.contains(btn)) return
      const tr = btn.closest('tr')
      const data = dtRef.current.row(tr).data()
      onActionRef.current?.(btn.dataset.act, data)
    }
    el.addEventListener('click', handler)

    return () => {
      el.removeEventListener('click', handler)
      try {
        dtRef.current?.destroy()
      } catch {
        /* déjà détruit */
      }
      dtRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Rafraîchit les données sans recréer l'instance.
  useEffect(() => {
    const dt = dtRef.current
    if (!dt) return
    dt.clear()
    dt.rows.add(rows)
    dt.draw(false)
  }, [rows])

  return (
    <div className="dt-shell">
      <table ref={tableRef} className="display" style={{ width: '100%' }} />
    </div>
  )
}
