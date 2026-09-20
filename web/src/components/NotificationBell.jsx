import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../lib/api'
import { fromNow } from '../lib/format'
import { IconBell } from './Icons'

export default function NotificationBell() {
  const [open, setOpen] = useState(false)
  const [data, setData] = useState({ unread_count: 0, items: [] })
  const ref = useRef(null)
  const navigate = useNavigate()

  const load = () => api.get('/notifications').then(({ data }) => setData(data)).catch(() => {})

  useEffect(() => {
    load()
    const t = setInterval(load, 60000)
    return () => clearInterval(t)
  }, [])

  useEffect(() => {
    const onClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const openItem = async (n) => {
    if (!n.read_at) {
      await api.post(`/notifications/${n.id}/read`).catch(() => {})
      load()
    }
    setOpen(false)
    const taskId = n.data?.task_id
    if (n.data?.type?.startsWith('collaboration')) navigate('/demandes')
    else if (taskId) navigate(`/taches/${taskId}`)
  }

  const markAll = async () => {
    await api.post('/notifications/read-all').catch(() => {})
    load()
  }

  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button className="bell" onClick={() => setOpen((o) => !o)} aria-label="Notifications">
        <IconBell />
        {data.unread_count > 0 && <span className="dot">{data.unread_count > 9 ? '9+' : data.unread_count}</span>}
      </button>
      {open && (
        <div className="popover">
          <div className="spread" style={{ padding: '10px 14px', borderBottom: '1px solid var(--border)' }}>
            <strong style={{ fontSize: '0.9rem' }}>Notifications</strong>
            {data.unread_count > 0 && (
              <button className="btn btn-ghost btn-sm" onClick={markAll}>
                Tout marquer lu
              </button>
            )}
          </div>
          {data.items.length === 0 && <div className="notif faint">Aucune notification.</div>}
          {data.items.map((n) => (
            <div key={n.id} className={`notif ${n.read_at ? '' : 'unread'}`} onClick={() => openItem(n)}>
              <div>{n.data?.message || n.data?.type}</div>
              <div className="faint" style={{ fontSize: '0.75rem', marginTop: 2 }}>{fromNow(n.created_at)}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
