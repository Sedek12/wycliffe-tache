import { useMemo, useState } from 'react'
import api, { errorMessage } from '../../lib/api'
import { useToast } from '../../context/ToastContext'
import { fmtBytes, fromNow } from '../../lib/format'
import DeliverableViewer from '../DeliverableViewer'
import { IconUpload, IconEye, IconTrash } from '../Icons'

export default function ProjectDocuments({ project, canManage, onChanged }) {
  const toast = useToast()
  const [viewing, setViewing] = useState(null)
  const [busy, setBusy] = useState(false)

  // Regroupe les livrables par group_key, dernière version en tête.
  const groups = useMemo(() => {
    const byKey = {}
    ;(project.deliverables || []).forEach((d) => {
      const key = d.group_key || String(d.id)
      byKey[key] = byKey[key] || []
      byKey[key].push(d)
    })
    return Object.values(byKey).map((versions) => versions.sort((a, b) => b.version - a.version))
  }, [project.deliverables])

  const upload = async (file, replacesId) => {
    setBusy(true)
    const fd = new FormData()
    fd.append('file', file)
    if (replacesId) fd.append('replaces_id', replacesId)
    try {
      await api.post(`/projects/${project.id}/deliverables`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success('Document déposé.')
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  const remove = async (d) => {
    if (!confirm(`Supprimer « ${d.original_name} » ?`)) return
    await api.delete(`/projects/${project.id}/deliverables/${d.id}`)
    onChanged()
  }

  return (
    <div className="card card-pad">
      <div className="spread">
        <h3 style={{ margin: 0 }}>Documents du projet ({groups.length})</h3>
        {canManage && (
          <label className="btn btn-primary btn-sm" style={{ cursor: 'pointer' }}>
            <IconUpload width={15} height={15} /> Déposer un document
            <input
              type="file"
              hidden
              disabled={busy}
              onChange={(e) => e.target.files[0] && upload(e.target.files[0]).then(() => (e.target.value = ''))}
            />
          </label>
        )}
      </div>

      <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 10 }}>
        {groups.length === 0 && <p className="faint">Aucun document.</p>}
        {groups.map((versions) => {
          const latest = versions[0]
          return (
            <div key={latest.group_key || latest.id} className="card" style={{ padding: 12, boxShadow: 'none', background: 'var(--surface-2)' }}>
              <div className="spread">
                <div>
                  <span className="cell-strong">{latest.original_name}</span>
                  <span className="badge badge-blue" style={{ marginLeft: 8 }}>v{latest.version}</span>
                  <div className="faint">
                    {fmtBytes(latest.size)} · {latest.uploader?.name} · {fromNow(latest.created_at)}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 6 }}>
                  <button className="dt-ico dt-ico--blue" title="Aperçu" onClick={() => setViewing(latest)}>
                    <IconEye width={14} height={14} />
                  </button>
                  {canManage && (
                    <>
                      <label className="dt-ico dt-ico--blue" title="Nouvelle version" style={{ cursor: 'pointer' }}>
                        <IconUpload width={14} height={14} />
                        <input
                          type="file"
                          hidden
                          disabled={busy}
                          onChange={(e) => e.target.files[0] && upload(e.target.files[0], latest.id).then(() => (e.target.value = ''))}
                        />
                      </label>
                      <button className="dt-ico dt-ico--red" title="Supprimer" onClick={() => remove(latest)}>
                        <IconTrash width={14} height={14} />
                      </button>
                    </>
                  )}
                </div>
              </div>
              {versions.length > 1 && (
                <details style={{ marginTop: 8 }}>
                  <summary className="faint" style={{ cursor: 'pointer', fontSize: '0.8rem' }}>
                    {versions.length - 1} version(s) précédente(s)
                  </summary>
                  <div style={{ marginTop: 6, display: 'flex', flexDirection: 'column', gap: 4 }}>
                    {versions.slice(1).map((v) => (
                      <div key={v.id} className="faint" style={{ fontSize: '0.82rem', display: 'flex', justifyContent: 'space-between' }}>
                        <span>v{v.version} — {fromNow(v.created_at)} — {v.uploader?.name}</span>
                        <button className="link-btn" onClick={() => setViewing(v)}>voir</button>
                      </div>
                    ))}
                  </div>
                </details>
              )}
            </div>
          )
        })}
      </div>

      {viewing && <DeliverableViewer deliverable={viewing} onClose={() => setViewing(null)} />}
    </div>
  )
}
