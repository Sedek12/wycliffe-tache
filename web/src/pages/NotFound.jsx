import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <div className="center-page" style={{ flexDirection: 'column', gap: 12 }}>
      <h1 style={{ fontSize: '2.5rem', margin: 0 }}>404</h1>
      <p className="muted">Cette page n’existe pas.</p>
      <Link className="btn btn-blue" to="/">Retour au tableau de bord</Link>
    </div>
  )
}
