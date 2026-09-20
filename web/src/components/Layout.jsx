import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Avatar } from './ui'
import NotificationBell from './NotificationBell'
import UserMenu from './UserMenu'
import ErrorBoundary from './ErrorBoundary'
import RouteLoader from './RouteLoader'
import logo from '../assets/logo-wycliffe-benin.png'
import {
  IconDashboard,
  IconTasks,
  IconDepartments,
  IconUsers,
  IconInbox,
  IconReports,
  IconBriefcase,
  IconProjects,
} from './Icons'

export default function Layout() {
  const { user, isSupervisor, isChef } = useAuth()
  const location = useLocation()

  // Groupe « haut » : le travail quotidien. Groupe « bas » : l'administration.
  const topNav = [
    { to: '/', label: 'Tableau de bord', icon: IconDashboard, end: true, show: true },
    { to: '/projets', label: 'Projets', icon: IconProjects, show: true },
    { to: '/taches', label: 'Tâches', icon: IconTasks, show: true },
    { to: '/demandes', label: 'Demandes', icon: IconInbox, show: isChef || isSupervisor },
    { to: '/rapports', label: 'Rapports', icon: IconReports, show: isSupervisor || isChef },
  ].filter((n) => n.show)

  const bottomNav = [
    { to: '/departements', label: 'Départements', icon: IconDepartments, show: true },
    { to: '/postes', label: 'Postes', icon: IconBriefcase, show: isSupervisor || isChef },
    { to: '/utilisateurs', label: 'Utilisateurs', icon: IconUsers, show: isSupervisor },
  ].filter((n) => n.show)

  const renderLink = ({ to, label, icon: Icon, end }) => (
    <NavLink key={to} to={to} end={end}>
      <Icon width={17} height={17} />
      {label}
    </NavLink>
  )

  const roleText = user?.system_role_label || (isChef ? 'Chef de département' : 'Collaborateur')

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <img src={logo} alt="Wycliffe Bénin" />
          <span className="sidebar-brand-text">
            Wycliffe Bénin
            <small>Gestion des tâches</small>
          </span>
        </div>

        <nav className="sidebar-nav">
          <div className="sidebar-group">{topNav.map(renderLink)}</div>
          <div className="sidebar-spacer" />
          <div className="sidebar-group">
            <div className="sidebar-group__label">Administration</div>
            {bottomNav.map(renderLink)}
          </div>
        </nav>

        <div className="sidebar-foot">
          <Avatar user={user} size={30} />
          <div style={{ minWidth: 0 }}>
            <div className="sidebar-foot__name">{user?.name}</div>
            <div className="sidebar-foot__role">{roleText}</div>
          </div>
        </div>
      </aside>

      <div className="main">
        <header className="topbar">
          <div className="topbar-logo">
            <img src={logo} alt="Wycliffe Bénin" />
            <span>Wycliffe Bénin</span>
          </div>
          <div style={{ flex: 1 }} />
          <NotificationBell />
          <UserMenu />
        </header>
        <div className="content-wrap">
          <RouteLoader />
          <div className="content">
            <ErrorBoundary key={location.pathname}>
              <Outlet />
            </ErrorBoundary>
          </div>
        </div>
      </div>
    </div>
  )
}
