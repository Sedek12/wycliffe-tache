import { useEffect, useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { errorMessage } from '../lib/api'
import { Field } from '../components/ui'

// Durée de l'intro animée avant l'affichage du formulaire (en ms).
// Mets 60000 pour une minute pleine, ou une valeur plus courte selon le besoin.
const INTRO_MS = 3500

// Délai (en secondes) entre l'apparition de chaque bloc du formulaire.
// 0 = tous les blocs glissent en même temps, sans attente.
const STEP = 0

// Phrases qui défilent au-dessus du formulaire (thème : gestion des tâches).
const PHRASES = [
  'Organisez vos tâches, avancez sereinement.',
  'Chaque tâche suivie, chaque objectif atteint.',
  'Collaborez, priorisez, livrez à temps.',
  'Vos échéances sous contrôle, en un coup d’œil.',
  'De l’idée à la réalisation, ensemble.',
  'La clarté aujourd’hui, la performance demain.',
]

export default function Login() {
  const { user, login, loading } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [showIntro, setShowIntro] = useState(true)
  const [introFading, setIntroFading] = useState(false)
  const [phraseIdx, setPhraseIdx] = useState(0)
  const [phraseIn, setPhraseIn] = useState(true)
  const [showForgot, setShowForgot] = useState(false)

  useEffect(() => {
    const fadeTimer = setTimeout(() => setIntroFading(true), INTRO_MS)
    const doneTimer = setTimeout(() => setShowIntro(false), INTRO_MS + 600)
    return () => {
      clearTimeout(fadeTimer)
      clearTimeout(doneTimer)
    }
  }, [])

  useEffect(() => {
    const cycle = setInterval(() => {
      setPhraseIn(false)
      setTimeout(() => {
        setPhraseIdx((i) => (i + 1) % PHRASES.length)
        setPhraseIn(true)
      }, 500)
    }, 4000)
    return () => clearInterval(cycle)
  }, [])

  if (!loading && user) return <Navigate to="/" replace />

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    setBusy(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(errorMessage(err, 'Connexion impossible.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth-wrap">
      {showIntro && (
        <div className={`auth-intro${introFading ? ' auth-intro--fade' : ''}`}>
          <div className="auth-intro__badge">
            <div className="auth-intro__ring" />
            <img className="auth-intro__logo" src="/logo-wycliffe-benin.png" alt="Wycliffe Bénin" />
          </div>
        </div>
      )}

      {!showIntro && (
      <form className="auth-card" onSubmit={submit}>
        <div className="auth-in auth-in--left" style={{ animationDelay: `${STEP * 0}s` }}>
          <div className="auth-ticker" aria-hidden="true">
            <span className={`auth-ticker__item${phraseIn ? ' is-in' : ' is-out'}`}>
              {PHRASES[phraseIdx]}
            </span>
          </div>
        </div>

        <div className="auth-in auth-in--right" style={{ animationDelay: `${STEP * 1}s` }}>
          <div className="brand">
            Wycliffe Bénin
            <small>Plateforme de gestion des tâches</small>
          </div>
        </div>

        <div className="auth-in auth-in--left" style={{ animationDelay: `${STEP * 2}s` }}>
          <Field label="Adresse e-mail">
            <input
              className="input"
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </Field>
        </div>

        <div className="auth-in auth-in--right" style={{ animationDelay: `${STEP * 3}s` }}>
          <Field label="Mot de passe">
            <input
              className="input"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </Field>
        </div>

        {error && (
          <div className="auth-in auth-in--left" style={{ animationDelay: '0s' }}>
            <div className="field-error" style={{ marginBottom: 12 }}>{error}</div>
          </div>
        )}

        <div className="auth-in auth-in--up" style={{ animationDelay: `${STEP * 4}s` }}>
          <button className="btn btn-primary" style={{ width: '100%', justifyContent: 'center' }} disabled={busy}>
            {busy ? 'Connexion…' : 'Se connecter'}
          </button>
        </div>

        <div className="auth-in auth-in--up" style={{ animationDelay: `${STEP * 5}s` }}>
          <button
            type="button"
            className="auth-forgot"
            onClick={() => setShowForgot((v) => !v)}
          >
            Mot de passe oublié ?
          </button>
          {showForgot && (
            <p className="auth-forgot-hint">
              Contactez votre administrateur pour réinitialiser votre mot de passe.
            </p>
          )}
        </div>
      </form>
      )}
    </div>
  )
}
