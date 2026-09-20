import { Component } from 'react'

export default class ErrorBoundary extends Component {
  state = { error: null }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    console.error('Erreur applicative :', error, info)
  }

  render() {
    if (this.state.error) {
      return (
        <div className="card card-pad" style={{ margin: 24 }}>
          <h2 style={{ marginTop: 0 }}>Une erreur est survenue sur cet écran</h2>
          <p className="muted">{String(this.state.error?.message || this.state.error)}</p>
          <button className="btn btn-blue" onClick={() => this.setState({ error: null })}>
            Réessayer
          </button>
          <button
            className="btn btn-ghost"
            style={{ marginLeft: 8 }}
            onClick={() => {
              window.location.href = '/'
            }}
          >
            Retour au tableau de bord
          </button>
        </div>
      )
    }
    return this.props.children
  }
}
