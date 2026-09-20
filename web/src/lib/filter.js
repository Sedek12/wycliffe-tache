/**
 * Utilitaires de filtrage/recherche côté client (aucun appel réseau).
 * Recherche insensible à la casse et aux accents.
 */
const DIACRITICS = /[̀-ͯ]/g

export const normalize = (s) =>
  String(s ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(DIACRITICS, '')
    .trim()

/** true si tous les mots de `needle` se retrouvent dans `parts` (chaînes ou tableaux). */
export const matchQuery = (needle, ...parts) => {
  const q = normalize(needle)
  if (!q) return true
  const hay = parts.flat(Infinity).map(normalize).join('  ')
  return q.split(/\s+/).every((word) => hay.includes(word))
}
