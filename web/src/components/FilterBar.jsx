import { Search, X, SlidersHorizontal } from 'lucide-react'

/**
 * Barre de recherche + filtres, 100 % côté client (aucun rechargement).
 *
 * props :
 *  - query, onQuery(str)        : texte de recherche
 *  - placeholder                : texte d'invite du champ
 *  - filters : [{ key, label, value, onChange(v), options:[{value,label}], allLabel }]
 *  - count, total               : nombre de résultats affichés / total
 *  - resultNoun                 : mot au singulier ("département", "poste", "compte"…)
 */
export default function FilterBar({
  query,
  onQuery,
  placeholder = 'Rechercher…',
  filters = [],
  count,
  total,
  resultNoun = 'résultat',
}) {
  const activeFilters = filters.filter((f) => f.value)
  const dirty = Boolean(query) || activeFilters.length > 0

  const reset = () => {
    onQuery('')
    filters.forEach((f) => f.onChange(''))
  }

  return (
    <div className="filterbar">
      <div className="filterbar__main">
        <div className="filterbar__search">
          <Search className="filterbar__search-ico" width={17} height={17} />
          <input
            type="search"
            className="filterbar__input"
            value={query}
            placeholder={placeholder}
            onChange={(e) => onQuery(e.target.value)}
          />
          {query && (
            <button
              type="button"
              className="filterbar__clear"
              aria-label="Effacer la recherche"
              onClick={() => onQuery('')}
            >
              <X width={15} height={15} />
            </button>
          )}
        </div>

        {filters.map((f) => (
          <label key={f.key} className={`filterbar__select${f.value ? ' is-active' : ''}`}>
            <SlidersHorizontal width={14} height={14} />
            <span className="filterbar__select-label">{f.label}</span>
            <select value={f.value} onChange={(e) => f.onChange(e.target.value)}>
              <option value="">{f.allLabel || 'Tous'}</option>
              {f.options.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
        ))}

        {dirty && (
          <button type="button" className="filterbar__reset" onClick={reset}>
            Réinitialiser
          </button>
        )}
      </div>

      {typeof count === 'number' && (
        <div className="filterbar__count">
          <strong>{count}</strong> {resultNoun}
          {count > 1 ? 's' : ''}
          {typeof total === 'number' && count !== total && (
            <span className="filterbar__count-total"> sur {total}</span>
          )}
        </div>
      )}
    </div>
  )
}
