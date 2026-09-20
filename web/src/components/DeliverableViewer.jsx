import { isAudio, isImage, isOfficeFile, isPdf, isVideo } from '../lib/format'
import { Modal } from './ui'
import { IconDownload, IconExternal } from './Icons'

/**
 * Aperçu direct d'un fichier selon son type (image, PDF, vidéo, son, Office),
 * + actions Télécharger / Ouvrir dans un onglet.
 * `file` : { original_name, mime, url, preview_url }
 */
export default function DeliverableViewer({ deliverable, onClose }) {
  const d = deliverable
  const name = d.original_name
  const office = isOfficeFile(name)
  const canPreview =
    isImage(d.mime) || isPdf(d.mime, name) || office || isAudio(d.mime) || isVideo(d.mime)

  return (
    <Modal
      title={name}
      onClose={onClose}
      wide
      footer={
        <>
          <a className="btn btn-ghost" href={d.url} target="_blank" rel="noreferrer">
            <IconExternal width={15} height={15} /> Ouvrir dans un onglet
          </a>
          <a className="btn btn-primary" href={d.url} download>
            <IconDownload width={15} height={15} /> Télécharger
          </a>
        </>
      }
    >
      <div style={{ minHeight: 300 }}>
        {isImage(d.mime) && (
          <img src={d.url} alt={name} style={{ width: '100%', borderRadius: 8, objectFit: 'contain' }} />
        )}
        {isPdf(d.mime, name) && (
          <iframe title={name} src={d.url} style={{ width: '100%', height: '70vh', border: 0, borderRadius: 8 }} />
        )}
        {office && (
          <>
            <p className="faint">Aperçu via le visualiseur Microsoft Office en ligne.</p>
            <iframe title={name} src={d.preview_url} style={{ width: '100%', height: '70vh', border: 0, borderRadius: 8 }} />
          </>
        )}
        {isAudio(d.mime) && <audio controls src={d.url} style={{ width: '100%', marginTop: 40 }} />}
        {isVideo(d.mime) && (
          <video controls src={d.url} style={{ width: '100%', maxHeight: '70vh', borderRadius: 8, background: '#000' }} />
        )}
        {!canPreview && <p>Ce format ne peut pas être prévisualisé — utilisez « Télécharger ».</p>}
      </div>
    </Modal>
  )
}
