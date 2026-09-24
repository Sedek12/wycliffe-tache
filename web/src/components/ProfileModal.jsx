import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import api, { errorMessage } from '../lib/api'
import { Avatar, Field, Modal } from './ui'
import { IconEdit } from './Icons'

export default function ProfileModal({ onClose }) {
  const { user, setUser, isChef } = useAuth()
  const toast = useToast()
  const [mode, setMode] = useState('view') // 'view' | 'edit'
  const [busy, setBusy] = useState(false)
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
    job_title: user?.job_title || '',
  })
  const [pwd, setPwd] = useState({ current_password: '', password: '' })
  const [avatar, setAvatar] = useState(null)

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }))

  const save = async (e) => {
    e.preventDefault()
    setBusy(true)
    try {
      const fd = new FormData()
      Object.entries(form).forEach(([k, v]) => fd.append(k, v ?? ''))
      if (pwd.password) {
        fd.append('current_password', pwd.current_password)
        fd.append('password', pwd.password)
      }
      if (avatar) fd.append('avatar', avatar)
      fd.append('_method', 'PUT')
      const { data } = await api.post('/profile', fd)
      setUser(data.data ?? data)
      setPwd({ current_password: '', password: '' })
      setAvatar(null)
      setMode('view')
      toast.success('Profil mis à jour.')
    } catch (err) {
      toast.error(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  const roleText = user?.system_role_label || (isChef ? 'Chef de département' : 'Collaborateur')

  return (
    <Modal
      title="Mon profil"
      onClose={onClose}
      footer={
        mode === 'view' ? (
          <>
            <button type="button" className="btn btn-ghost" onClick={onClose}>Fermer</button>
            <button type="button" className="btn btn-primary" onClick={() => setMode('edit')}>
              <IconEdit width={15} height={15} /> Modifier
            </button>
          </>
        ) : (
          <>
            <button type="button" className="btn btn-ghost" onClick={() => setMode('view')}>Annuler</button>
            <button type="submit" className="btn btn-primary" form="profile-form" disabled={busy}>
              {busy ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </>
        )
      }
    >
      {mode === 'view' ? (
        <div>
          <div style={{ display: 'flex', gap: 14, alignItems: 'center', marginBottom: 16 }}>
            <Avatar user={user} size={64} />
            <div>
              <div style={{ fontWeight: 700, fontSize: '1.05rem' }}>{user?.name}</div>
              <div className="muted">{roleText}</div>
            </div>
          </div>
          <dl className="kv">
            <dt>E-mail</dt><dd>{user?.email || '—'}</dd>
            <dt>Téléphone</dt><dd>{user?.phone || '—'}</dd>
            <dt>Fonction</dt><dd>{user?.job_title || '—'}</dd>
            <dt>Départements</dt>
            <dd>
              {(user?.departments || []).length
                ? user.departments.map((d) => `${d.name} (${d.role})`).join(', ')
                : '—'}
            </dd>
          </dl>
        </div>
      ) : (
        <form id="profile-form" onSubmit={save}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 16 }}>
            <Avatar user={user} size={56} />
            <label className="btn btn-ghost btn-sm">
              Changer la photo
              <input type="file" accept="image/*" hidden onChange={(e) => setAvatar(e.target.files[0])} />
            </label>
            {avatar && <span className="faint">{avatar.name}</span>}
          </div>

          <Field label="Nom complet"><input className="input" value={form.name} onChange={set('name')} required /></Field>
          <Field label="E-mail"><input className="input" type="email" value={form.email} onChange={set('email')} required /></Field>
          <div className="row">
            <Field label="Téléphone"><input className="input" value={form.phone} onChange={set('phone')} /></Field>
            <Field label="Fonction"><input className="input" value={form.job_title} onChange={set('job_title')} /></Field>
          </div>

          <div className="sep" />
          <p className="faint">Laisser vide pour conserver le mot de passe actuel.</p>
          <div className="row">
            <Field label="Mot de passe actuel">
              <input className="input" type="password" value={pwd.current_password} onChange={(e) => setPwd((p) => ({ ...p, current_password: e.target.value }))} />
            </Field>
            <Field label="Nouveau mot de passe">
              <input className="input" type="password" value={pwd.password} onChange={(e) => setPwd((p) => ({ ...p, password: e.target.value }))} />
            </Field>
          </div>
        </form>
      )}
    </Modal>
  )
}
