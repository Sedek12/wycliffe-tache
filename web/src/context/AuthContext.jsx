import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import api, { getToken, setToken } from '../lib/api'
import { PROJECT_MANAGER_ROLES } from '../lib/projectRoles'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  const loadMe = useCallback(async () => {
    if (!getToken()) {
      setLoading(false)
      return
    }
    try {
      const { data } = await api.get('/me')
      setUser(data.data ?? data)
    } catch {
      setToken(null)
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadMe()
  }, [loadMe])

  const login = useCallback(async (email, password) => {
    const { data } = await api.post('/login', { email, password, device_name: 'web' })
    setToken(data.token)
    setUser(data.user.data ?? data.user)
    return data.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } catch {
      /* ignore */
    }
    setToken(null)
    setUser(null)
  }, [])

  const refresh = loadMe

  // Helpers de rôle
  const isAdmin = user?.system_role === 'admin'
  const isDirecteur = user?.system_role === 'directeur'
  const isSupervisor = isAdmin || isDirecteur
  const ledDepartments = (user?.departments ?? []).filter((d) => d.role === 'chef')
  const isChef = ledDepartments.length > 0
  const isChefOf = (id) => ledDepartments.some((d) => d.id === Number(id))

  const userProjects = user?.projects ?? []
  const projectRoleOf = (id) => userProjects.find((p) => p.id === Number(id))?.role || null
  const isProjectManagerOf = (id) => PROJECT_MANAGER_ROLES.includes(projectRoleOf(id))
  const isProjectMemberOf = (id) => userProjects.some((p) => p.id === Number(id))
  const managedProjects = userProjects.filter((p) => PROJECT_MANAGER_ROLES.includes(p.role))

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        logout,
        refresh,
        setUser,
        isAdmin,
        isDirecteur,
        isSupervisor,
        isChef,
        isChefOf,
        ledDepartments,
        projectRoleOf,
        isProjectManagerOf,
        isProjectMemberOf,
        managedProjects,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth doit être utilisé dans <AuthProvider>')
  return ctx
}
