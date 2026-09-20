# Wycliffe Bénin — Plateforme de gestion des tâches

Application interne (indépendante du site web) : chaque **chef de département** crée et
assigne des tâches à ses **employés / stagiaires**, suit l'avancement (%), reçoit les
**livrables** (PDF, Word, image, audio, vidéo, capture), **valide** et **évalue** (note /20
+ mention). Le **directeur** et l'**administrateur** supervisent tous les départements et
gèrent les comptes. Équipes possibles sur une même tâche, y compris avec un membre d'un
autre département via une **demande de collaboration** validée par le chef sollicité.

- **`api/`** — Laravel 12 (PHP 8.2), MySQL, authentification Sanctum (jeton Bearer).
- **`web/`** — React 19 + Vite + React Router 7 + Recharts + **jQuery DataTables** (tableaux d'administration).

## Départements, postes et affectations

- Un **département** a : des **membres** (chef / employé / stagiaire) et des **postes** (ex. « Traducteur », « Comptable »).
- Chaque membre occupe **un poste** du département et y a **un rôle** (chef / employé / stagiaire).
- Écran **Départements** (directeur / admin) : créer, modifier, activer/désactiver, supprimer — tableau jQuery.
- Écran **Département › onglet Postes** : créer / modifier / supprimer les postes propres au département.
- Écran **Département › onglet Membres** : ajouter un employé/stagiaire en choisissant **rôle + poste**, modifier ou retirer.
- Écran **Utilisateurs** : le formulaire de compte permet d'affecter directement la personne à un **département**, sous un **poste**, comme **employé** ou **stagiaire**.

## Démarrer en local

### 1. API (port 8001)

```bash
cd api
composer install
# .env déjà configuré : DB=wycliffe_taches (MySQL), APP_URL=http://localhost:8001, FRONTEND_URL=http://localhost:5174
php artisan migrate:fresh --seed     # crée le schéma + jeu de démonstration
php artisan storage:link             # une seule fois (accès public aux livrables)
php artisan serve --port=8001
```

Rappels d'échéance (J-3 / J-1 / jour J) et alertes de retard :

```bash
php artisan tasks:send-reminders     # manuel
# en production : cron « * * * * * cd .../api && php artisan schedule:run »
```

### 2. Frontend (port 5174)

```bash
cd web
npm install
npm run dev                          # http://localhost:5174
```

`web/.env` : `VITE_API_URL=http://localhost:8001/api`

## Comptes de démonstration (mot de passe : `password`)

| Rôle | E-mail |
|---|---|
| Administrateur | `admin@wycliffebenin.org` |
| Directeur exécutif | `directeur@wycliffebenin.org` |
| Chef de département | `jean.kokou@wycliffebenin.org` (Traduction), `marthe.sagbo@wycliffebenin.org` (Alphabétisation)… |
| Employé / Stagiaire | `<prenom.nom>@wycliffebenin.org` |

## Rôles & permissions

| Action | Admin | Directeur | Chef (son dépt.) | Employé / Stagiaire |
|---|:--:|:--:|:--:|:--:|
| Gérer comptes & rôles | ✅ | ✅ | — | — |
| Créer départements / nommer chefs | ✅ | ✅ | — | — |
| Créer & assigner une tâche | — | — | ✅ | — |
| Avancement (%) + livrable | — | — | ✅ | ✅ (ses tâches) |
| Valider + évaluer | — | — | ✅ (créateur) | — |
| Voir tous les départements | ✅ | ✅ | son département | ses tâches |
| Paramètres plateforme | ✅ | ✅ | — | — |

## Cycle de vie d'une tâche

`brouillon → assignée → en cours → livrée → validée` (avec `à refaire → en cours`).
« En retard » est un état dérivé (échéance dépassée, non validée).

## Structure

```
api/
  app/Enums/              SystemRole, DepartmentRole, TaskStatus, EvaluationMention, CollaborationRequestStatus
  app/Models/             User, Department, Task, ProgressUpdate, Deliverable, Evaluation,
                          TaskStatusHistory, CollaborationRequest, Setting
  app/Policies/           DepartmentPolicy, TaskPolicy, UserPolicy, CollaborationRequestPolicy
  app/Services/           TaskWorkflow (transitions + historique + notifications)
  app/Notifications/      TaskAssigned, TaskStatusChanged, DeliverableSubmitted,
                          TaskDeadlineReminder, CollaborationRequested, CollaborationAnswered
  app/Http/Controllers/Api/   Auth, User, Department, Task, TaskProgress, TaskStatus,
                          Deliverable, Evaluation, CollaborationRequest, Dashboard, Report,
                          Setting, Notification, Meta, Profile
  routes/api.php          toutes les routes sous /api (auth:sanctum)
  routes/console.php      planification du rappel quotidien

web/
  src/context/            AuthContext, MetaContext, ToastContext
  src/components/          Layout, NotificationBell, TaskForm, CollaborationModal,
                          DeliverableViewer, charts, ui, Icons
  src/pages/              Login, Dashboard, Tasks, TaskDetail, Departments, DepartmentDetail,
                          Users, CollaborationRequests, Reports, Settings, Profile, NotFound
```

## Paramètres configurables (écran Paramètres, directeur/admin)

- Taille maximale des livrables (Mo), extensions autorisées, durée de conservation.
- Jours de rappel avant échéance (par défaut 3, 1, 0).

## Notes

- Les fichiers bureautiques (Word/Excel/PowerPoint) sont prévisualisés via le **visualiseur
  Microsoft Office en ligne** (aucun visualiseur hébergé en interne) ; PDF, images, audio et
  vidéo sont lus nativement par le navigateur.
- Les livrables sont stockés sur le disque `public` de Laravel (`api/storage/app/public`).
  Pour un stockage externe, changer `FILESYSTEM_DISK` sans toucher au code.
- En dev, `MAIL_MAILER=log` : les e-mails de notification sont écrits dans
  `api/storage/logs/laravel.log`.
