import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Avatar } from './ui'
import ProfileModal from './ProfileModal'
import SettingsModal from './SettingsModal'
import { IconUser, IconSettings, IconLogout } from './Icons'

export default function UserMenu() {
  const { user, logout, isDirecteur, isAdmin } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [modal, setModal] = useState(null) // 'profile' | 'settings'
  const ref = useRef(null)

  useEffect(() => {
    const onClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const onLogout = async () => {
    setOpen(false)
    await logout()
    navigate('/connexion')
  }

  return (
    <div ref={ref} className="user-menu">
      <button className="user-menu__btn" onClick={() => setOpen((o) => !o)} aria-label="Mon compte">
        <Avatar user={user} size={34} />
      </button>

      {open && (
        <div className="user-menu__pop">
          <div className="user-menu__head">
            <Avatar user={user} size={38} />
            <div style={{ minWidth: 0 }}>
              <div className="user-menu__name">{user?.name}</div>
              <div className="faint">{user?.email}</div>
            </div>
          </div>
          <button className="user-menu__item" onClick={() => { setOpen(false); setModal('profile') }}>
            <IconUser width={16} height={16} /> Profil
          </button>
          {(isDirecteur || isAdmin) && (
            <button className="user-menu__item" onClick={() => { setOpen(false); setModal('settings') }}>
              <IconSettings width={16} height={16} /> Paramètres
            </button>
          )}
          <div className="user-menu__sep" />
          <button className="user-menu__item user-menu__item--danger" onClick={onLogout}>
            <IconLogout width={16} height={16} /> Déconnexion
          </button>
        </div>
      )}

      {modal === 'profile' && <ProfileModal onClose={() => setModal(null)} />}
      {modal === 'settings' && <SettingsModal onClose={() => setModal(null)} />}
    </div>
  )
}
