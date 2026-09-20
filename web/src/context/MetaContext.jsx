import { createContext, useContext, useEffect, useState } from 'react'
import api from '../lib/api'
import { useAuth } from './AuthContext'

const MetaContext = createContext(null)

export function MetaProvider({ children }) {
  const { user } = useAuth()
  const [meta, setMeta] = useState(null)

  useEffect(() => {
    if (!user) {
      setMeta(null)
      return
    }
    api
      .get('/meta')
      .then(({ data }) => setMeta(data))
      .catch(() => setMeta(null))
  }, [user])

  const labelOf = (list, value) =>
    meta?.[list]?.find((x) => x.value === value)?.label ?? value

  return (
    <MetaContext.Provider value={{ meta, labelOf }}>{children}</MetaContext.Provider>
  )
}

export function useMeta() {
  const ctx = useContext(MetaContext)
  if (!ctx) throw new Error('useMeta doit être utilisé dans <MetaProvider>')
  return ctx
}
