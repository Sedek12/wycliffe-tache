import { format, formatDistanceToNow, isPast, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'

function toDate(value) {
  if (!value) return null
  return typeof value === 'string' ? parseISO(value) : value
}

export function fmtDate(value) {
  const d = toDate(value)
  return d ? format(d, 'dd/MM/yyyy', { locale: fr }) : '—'
}

export function fmtDateTime(value) {
  const d = toDate(value)
  return d ? format(d, "dd/MM/yyyy 'à' HH:mm", { locale: fr }) : '—'
}

export function fromNow(value) {
  const d = toDate(value)
  return d ? formatDistanceToNow(d, { locale: fr, addSuffix: true }) : ''
}

export function isOverdue(value) {
  const d = toDate(value)
  return d ? isPast(d) : false
}

export function fmtBytes(bytes) {
  if (!bytes) return '0 o'
  const units = ['o', 'Ko', 'Mo', 'Go']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`
}

export const STATUS_BADGE = {
  brouillon: 'badge-grey',
  assignee: 'badge-blue',
  en_cours: 'badge-amber',
  livree: 'badge-blue',
  validee: 'badge-green',
  a_refaire: 'badge-red',
  annulee: 'badge-grey',
}

export const MENTION_BADGE = {
  insuffisant: 'badge-red',
  passable: 'badge-amber',
  assez_bien: 'badge-amber',
  bien: 'badge-blue',
  tres_bien: 'badge-green',
  excellent: 'badge-green',
}

/** Formats bureautiques dont l'aperçu passe par un visualiseur tiers. */
export function isOfficeFile(name = '') {
  return /\.(docx?|xlsx?|pptx?)$/i.test(name)
}

export function isImage(mime = '') {
  return mime.startsWith('image/')
}
export function isPdf(mime = '', name = '') {
  return mime === 'application/pdf' || /\.pdf$/i.test(name)
}
export function isAudio(mime = '') {
  return mime.startsWith('audio/')
}
export function isVideo(mime = '') {
  return mime.startsWith('video/')
}
