import { useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import logo from '../assets/logo-wycliffe-benin.png'

// Brève animation (cercle + logo) affichée à chaque changement de page,
// identique à l'intro de la page de connexion.
const SHOW_MS = 420
const FADE_MS = 320

export default function RouteLoader() {
  const location = useLocation()
  const [phase, setPhase] = useState('hidden') // 'hidden' | 'in' | 'out'
  const first = useRef(true)

  useEffect(() => {
    if (first.current) {
      first.current = false
      return
    }
    setPhase('in')
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    const hold = reduce ? 120 : SHOW_MS
    const t1 = setTimeout(() => setPhase('out'), hold)
    const t2 = setTimeout(() => setPhase('hidden'), hold + FADE_MS)
    return () => {
      clearTimeout(t1)
      clearTimeout(t2)
    }
  }, [location.pathname])

  if (phase === 'hidden') return null

  return (
    <div className={`route-loader${phase === 'out' ? ' route-loader--out' : ''}`} aria-hidden="true">
      <div className="auth-intro__badge">
        <div className="auth-intro__ring" />
        <img className="auth-intro__logo" src={logo} alt="" />
      </div>
    </div>
  )
}
