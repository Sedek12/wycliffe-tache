/**
 * En-tête de page uniforme : titre + sous-titre à gauche, actions à droite.
 * Utilisé sur toutes les pages pour éviter les décalages.
 */
export default function PageHeader({ title, subtitle, actions, back }) {
  return (
    <div className="page-header">
      <div className="page-header__text">
        {back}
        <h1>{title}</h1>
        {subtitle && <p className="page-header__sub">{subtitle}</p>}
      </div>
      {actions && <div className="page-header__actions">{actions}</div>}
    </div>
  )
}
