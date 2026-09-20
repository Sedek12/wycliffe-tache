import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import { MetaProvider } from './context/MetaContext'
import { ToastProvider } from './context/ToastContext'
import { Spinner } from './components/ui'
import Layout from './components/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Tasks from './pages/Tasks'
import TaskDetail from './pages/TaskDetail'
import Departments from './pages/Departments'
import DepartmentDetail from './pages/DepartmentDetail'
import Projects from './pages/Projects'
import ProjectDetail from './pages/ProjectDetail'
import Positions from './pages/Positions'
import Users from './pages/Users'
import CollaborationRequests from './pages/CollaborationRequests'
import Reports from './pages/Reports'
import NotFound from './pages/NotFound'

function Protected({ children, allow }) {
  const auth = useAuth()
  if (auth.loading) return <Spinner />
  if (!auth.user) return <Navigate to="/connexion" replace />
  if (allow && !allow(auth)) return <Navigate to="/" replace />
  return children
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <MetaProvider>
          <Routes>
            <Route path="/connexion" element={<Login />} />
            <Route
              element={
                <Protected>
                  <Layout />
                </Protected>
              }
            >
              <Route index element={<Dashboard />} />
              <Route path="taches" element={<Tasks />} />
              <Route path="taches/:id" element={<TaskDetail />} />
              <Route path="departements" element={<Departments />} />
              <Route path="departements/:id" element={<DepartmentDetail />} />
              <Route path="projets" element={<Projects />} />
              <Route path="projets/:id" element={<ProjectDetail />} />
              <Route
                path="postes"
                element={
                  <Protected allow={(a) => a.isSupervisor || a.isChef}>
                    <Positions />
                  </Protected>
                }
              />
              <Route path="demandes" element={<CollaborationRequests />} />
              <Route
                path="rapports"
                element={
                  <Protected allow={(a) => a.isSupervisor || a.isChef}>
                    <Reports />
                  </Protected>
                }
              />
              <Route
                path="utilisateurs"
                element={
                  <Protected allow={(a) => a.isSupervisor}>
                    <Users />
                  </Protected>
                }
              />
              <Route path="profil" element={<Navigate to="/" replace />} />
              <Route path="parametres" element={<Navigate to="/" replace />} />
              <Route path="*" element={<NotFound />} />
            </Route>
          </Routes>
        </MetaProvider>
      </ToastProvider>
    </AuthProvider>
  )
}
