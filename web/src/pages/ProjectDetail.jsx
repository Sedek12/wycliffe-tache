import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Spinner, StatusBadge } from '../components/ui'
import PageHeader from '../components/PageHeader'
import ProjectCadrage from '../components/project/ProjectCadrage'
import ProjectPlanification from '../components/project/ProjectPlanification'
import ProjectRessources from '../components/project/ProjectRessources'
import ProjectDocuments from '../components/project/ProjectDocuments'
import ProjectBudget from '../components/project/ProjectBudget'
import ProjectDashboard from '../components/project/ProjectDashboard'

const TABS = [
  { key: 'cadrage', label: 'Cadrage' },
  { key: 'planification', label: 'Planification' },
  { key: 'ressources', label: 'Ressources' },
  { key: 'documents', label: 'Documents' },
  { key: 'budget', label: 'Budget' },
  { key: 'dashboard', label: 'Tableau de bord' },
]

export default function ProjectDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { isAdmin, isDirecteur, isChefOf, isProjectManagerOf } = useAuth()
  const toast = useToast()

  const [project, setProject] = useState(null)
  const [tab, setTab] = useState('cadrage')

  const load = useCallback(() => {
    api.get(`/projects/${id}`).then(({ data }) => setProject(data.data)).catch(() => setProject(false))
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  if (project === false) return <p className="muted">Projet introuvable.</p>
  if (!project) return <Spinner />

  const canManage = isAdmin || isDirecteur || isChefOf(project.department_id) || isProjectManagerOf(project.id)

  const remove = async () => {
    if (!confirm(`Supprimer le projet « ${project.title} » ? Ses activités seront aussi supprimées.`)) return
    try {
      await api.delete(`/projects/${id}`)
      toast.success('Projet supprimé.')
      navigate('/projets')
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  return (
    <>
      <PageHeader
        back={
          <button className="btn btn-ghost btn-sm" onClick={() => navigate('/projets')} style={{ marginBottom: 8 }}>
            ← Projets
          </button>
        }
        title={project.title}
        subtitle={project.department?.name}
        actions={
          <>
            <StatusBadge status={project.status} label={project.status_label} />
            {isAdmin || isDirecteur || isChefOf(project.department_id) ? (
              <button className="btn btn-ghost btn-sm" onClick={remove} style={{ marginLeft: 8 }}>Supprimer</button>
            ) : null}
          </>
        }
      />

      <div style={{ display: 'flex', gap: 6, margin: '0 0 16px', flexWrap: 'wrap' }}>
        {TABS.map((t) => (
          <button
            key={t.key}
            className={`btn btn-sm ${tab === t.key ? 'btn-blue' : 'btn-ghost'}`}
            onClick={() => setTab(t.key)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'cadrage' && <ProjectCadrage project={project} canManage={canManage} onChanged={load} />}
      {tab === 'planification' && <ProjectPlanification project={project} canManage={canManage} onChanged={load} />}
      {tab === 'ressources' && <ProjectRessources project={project} canManage={canManage} onChanged={load} />}
      {tab === 'documents' && <ProjectDocuments project={project} canManage={canManage} onChanged={load} />}
      {tab === 'budget' && <ProjectBudget project={project} canManage={canManage} onChanged={load} />}
      {tab === 'dashboard' && <ProjectDashboard project={project} />}
    </>
  )
}
