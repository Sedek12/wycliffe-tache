import { useMemo, useState } from 'react'
import { normalize } from '../lib/filter'
import { Search } from 'lucide-react'

const initials = (name = '') =>
  name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || 'U'

/**
 * Sélecteur de personnes avec recherche (multi-sélection).
 *
 * props :
 *  - members : [{ id, name, pivot?:{role}, ... }]
 *  - value   : tableau d'ids sélectionnés
 *  - onChange(ids)
 *  - roleOf(member)  : libellé de rôle à afficher (facultatif)
 *  - emptyText       : texte quand aucun membre
 *  - max             : nombre maximum de sélections (facultatif)
 */
export default function MemberPicker({ members = [], value = [], onChange, roleOf, emptyText = 'Aucun membre.', max }) {
  const [q, setQ] = useState('')

  const filtered = useMemo(() => {
    const n = normalize(q)
    const list = n ? members.filter((m) => normalize(m.name).includes(n)) : members
    return [...list].sort((a, b) => a.name.localeCompare(b.name))
  }, [members, q])

  const toggle = (id) => {
    if (value.includes(id)) onChange(value.filter((x) => x !== id))
    else if (!max || value.length < max) onChange([...value, id])
  }

  return (
    <div className="picker">
      <div className="picker__search">
        <Search width={15} height={15} />
        <input
          type="search"
          value={q}
          placeholder="Rechercher une personne…"
          onChange={(e) => setQ(e.target.value)}
        />
      </div>

      <div className="picker__list">
        {members.length === 0 && <div className="picker__empty">{emptyText}</div>}
        {members.length > 0 && filtered.length === 0 && <div className="picker__empty">Aucun résultat.</div>}
        {filtered.map((m) => {
          const on = value.includes(m.id)
          const disabled = !on && max && value.length >= max
          return (
            <button
              type="button"
              key={m.id}
              className={`picker__row${on ? ' is-on' : ''}`}
              onClick={() => toggle(m.id)}
              disabled={disabled}
            >
              <span className="picker__check">{on ? '✓' : ''}</span>
              <span className="dt-avatar" style={{ margin: 0 }}>{initials(m.name)}</span>
              <span className="picker__name">{m.name}</span>
              {roleOf && <span className="picker__role">{roleOf(m)}</span>}
            </button>
          )
        })}
      </div>

      <div className="picker__foot">
        <span>
          <strong>{value.length}</strong> sélectionné{value.length > 1 ? 's' : ''}
          {max ? ` / ${max}` : ''}
        </span>
        {value.length > 0 && (
          <button type="button" className="picker__clear" onClick={() => onChange([])}>
            Tout retirer
          </button>
        )}
      </div>
    </div>
  )
}
