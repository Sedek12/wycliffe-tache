<?php

namespace Database\Seeders;

use App\Enums\DepartmentRole;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $admin = User::updateOrCreate(
            ['email' => 'admin@wycliffebenin.org'],
            ['name' => 'Administrateur', 'password' => $password, 'system_role' => SystemRole::Admin, 'job_title' => 'Administrateur système']
        );

        $directeur = User::updateOrCreate(
            ['email' => 'directeur@wycliffebenin.org'],
            ['name' => 'Direction exécutive', 'password' => $password, 'system_role' => SystemRole::Directeur, 'job_title' => 'Directeur exécutif']
        );

        $blueprint = [
            'Traduction de la Bible' => [
                'chef' => 'Jean Kokou',
                'employes' => ['Awa Dossou', 'Paul Agbessi'],
                'stagiaires' => ['Rebecca Houngbo'],
                'postes' => ['Traducteur', 'Exégète-conseil', 'Vérificateur communautaire', 'Assistant linguistique'],
            ],
            'Alphabétisation' => [
                'chef' => 'Marthe Sagbo',
                'employes' => ['Isaac Adjovi', 'Grace Tossou'],
                'stagiaires' => ['Daniel Kpassa'],
                'postes' => ['Animateur alpha', 'Formateur de moniteurs', 'Concepteur de supports'],
            ],
            'Développement communautaire' => [
                'chef' => 'Samuel Ahouansou',
                'employes' => ['Esther Djossou', 'Josué Adande'],
                'stagiaires' => ['Ruth Zinsou'],
                'postes' => ['Agent de terrain', 'Chargé de suivi-évaluation', 'Mobilisateur communautaire'],
            ],
            'Communication' => [
                'chef' => 'Débora Aholou',
                'employes' => ['Élie Sossou'],
                'stagiaires' => ['Naomi Gbaguidi'],
                'postes' => ['Community manager', 'Graphiste', 'Chargé des médias'],
            ],
            'Administration & Finances' => [
                'chef' => 'Ézéchiel Codjo',
                'employes' => ['Sarah Alladatin', 'Timothée Houssou'],
                'stagiaires' => ['Myriam Dagba'],
                'postes' => ['Comptable', 'Assistant administratif', 'Gestionnaire de paie', 'Logisticien'],
            ],
        ];

        $chefs = [];
        $members = [];

        foreach ($blueprint as $deptName => $spec) {
            $department = Department::updateOrCreate(
                ['name' => $deptName],
                ['description' => "Département « {$deptName} » de Wycliffe Bénin.", 'created_by' => $directeur->id]
            );

            $positions = [];
            foreach ($spec['postes'] as $posteName) {
                $positions[$posteName] = $department->positions()->updateOrCreate(['name' => $posteName]);
            }
            $posteList = array_values($positions);

            $chef = $this->makeUser($spec['chef'], $password, 'Chef de département');
            $department->members()->syncWithoutDetaching([$chef->id => [
                'role' => DepartmentRole::Chef->value,
                'position_id' => $posteList[0]->id,
            ]]);
            $chefs[$deptName] = $chef;

            foreach ($spec['employes'] as $i => $name) {
                $u = $this->makeUser($name, $password, 'Employé');
                $department->members()->syncWithoutDetaching([$u->id => [
                    'role' => DepartmentRole::Employe->value,
                    'position_id' => $posteList[($i + 1) % count($posteList)]->id,
                ]]);
                $members[$deptName][] = $u;
            }

            foreach ($spec['stagiaires'] as $name) {
                $u = $this->makeUser($name, $password, 'Stagiaire');
                $department->members()->syncWithoutDetaching([$u->id => [
                    'role' => DepartmentRole::Stagiaire->value,
                    'position_id' => end($posteList)->id,
                ]]);
                $members[$deptName][] = $u;
            }
        }

        // Quelques tâches de démonstration dans le département Traduction.
        $trad = Department::where('name', 'Traduction de la Bible')->first();
        $chef = $chefs['Traduction de la Bible'];
        $team = $members['Traduction de la Bible'];

        $this->makeTask($trad, $chef, [$team[0]], 'Réviser le livre de Ruth (chap. 1-2)', TaskStatus::EnCours, 40, now()->subDays(3), now()->addDays(4));
        $this->makeTask($trad, $chef, [$team[1]], 'Vérification communautaire — Actes 3', TaskStatus::Assignee, 0, now()->addDay(), now()->addDays(10));
        $this->makeTask($trad, $chef, [$team[0], $team[2]], 'Harmonisation du glossaire théologique', TaskStatus::Livree, 100, now()->subDays(12), now()->subDays(1), isTeam: true);
        $done = $this->makeTask($trad, $chef, [$team[1]], 'Saisie des corrections — Marc 1', TaskStatus::Validee, 100, now()->subDays(20), now()->subDays(10));
        $done->evaluation()->create(['evaluated_by' => $chef->id, 'score' => 16, 'appreciation' => 'Travail soigné, livré dans les délais.']);
        $done->update(['completed_at' => now()->subDays(11)]);

        // Historique de 6 mois : tâches validées + évaluées dans tous les départements
        // (pour peupler les graphes de la page Rapports).
        $this->seedHistory($chefs, $members);

        // Module 1 — Gestion de projets : projets, activités, jalons, dépendances,
        // dépenses et documents versionnés de démonstration.
        $this->call(ProjectSeeder::class);

        // Journal d'activité, livrables et paramètres plateforme, pour que toutes
        // les pages (pas seulement Tâches/Projets) soient peuplées.
        $this->call(TaskDemoDataSeeder::class);

        $this->command->info('Comptes : admin@wycliffebenin.org / directeur@wycliffebenin.org / <prenom.nom>@wycliffebenin.org — mot de passe : password');
    }

    /** Génère ~6 mois d'activité terminée + évaluée dans chaque département. */
    private function seedHistory(array $chefs, array $members): void
    {
        $verbs = ['Rédaction', 'Révision', 'Saisie', 'Vérification', 'Préparation', 'Suivi', 'Formation', 'Rapport', 'Atelier', 'Compte rendu'];
        $objets = ['mensuel', 'trimestriel', 'de terrain', 'communautaire', 'des moniteurs', 'du glossaire', 'des supports', 'de collecte', 'budgétaire', 'de zone'];

        foreach ($chefs as $deptName => $chef) {
            $dept = \App\Models\Department::where('name', $deptName)->first();
            $team = $members[$deptName] ?? [];
            if (empty($team)) {
                continue;
            }

            // 5 mois complets en arrière + le mois courant
            for ($monthAgo = 5; $monthAgo >= 0; $monthAgo--) {
                $perMonth = random_int(2, 4);
                for ($i = 0; $i < $perMonth; $i++) {
                    $assignee = $team[array_rand($team)];
                    $base = now()->copy()->subMonths($monthAgo)->startOfMonth()->addDays(random_int(1, 24));
                    $startsAt = $base->copy()->subDays(random_int(6, 14));
                    $dueAt = $base->copy()->addDays(random_int(1, 6));
                    // ~72 % dans les délais
                    $completedAt = random_int(1, 100) <= 72
                        ? $dueAt->copy()->subDays(random_int(0, 3))
                        : $dueAt->copy()->addDays(random_int(1, 5));

                    $title = $verbs[array_rand($verbs)] . ' ' . $objets[array_rand($objets)] . ' — ' . $base->translatedFormat('F Y');

                    $task = $this->makeTask($dept, $chef, [$assignee], $title, TaskStatus::Validee, 100, $startsAt, $dueAt);
                    $task->update(['completed_at' => $completedAt]);
                    $task->statusHistory()->create(['from_status' => 'livree', 'to_status' => 'validee', 'changed_by' => $chef->id]);

                    $score = random_int(9, 20);
                    $task->evaluation()->create([
                        'evaluated_by' => $chef->id,
                        'score' => $score,
                        'appreciation' => $score >= 16 ? 'Très bon travail.' : ($score >= 12 ? 'Travail correct.' : 'À améliorer.'),
                    ]);
                }
            }
        }
    }

    private function makeUser(string $name, string $password, string $jobTitle): User
    {
        $slug = \Illuminate\Support\Str::slug($name, '.');

        return User::updateOrCreate(
            ['email' => "{$slug}@wycliffebenin.org"],
            ['name' => $name, 'password' => $password, 'job_title' => $jobTitle]
        );
    }

    private function makeTask(Department $dept, User $chef, array $assignees, string $title, TaskStatus $status, int $progress, $startsAt, $dueAt, bool $isTeam = false): Task
    {
        $task = Task::create([
            'department_id' => $dept->id,
            'created_by' => $chef->id,
            'supervisor_id' => $chef->id,
            'title' => $title,
            'description' => "Objectif : {$title}. Merci de déposer le livrable (PDF, Word, audio, vidéo ou capture) avant l'échéance.",
            'starts_at' => $startsAt,
            'due_at' => $dueAt,
            'status' => $status,
            'progress' => $progress,
            'is_team' => $isTeam || count($assignees) > 1,
        ]);

        foreach ($assignees as $i => $user) {
            $task->assignees()->attach($user->id, [
                'instructions' => $isTeam || count($assignees) > 1 ? "Partie " . ($i + 1) . " du travail." : null,
            ]);
        }

        $task->statusHistory()->create(['from_status' => null, 'to_status' => $status->value, 'changed_by' => $chef->id]);

        if ($progress > 0) {
            $task->progressUpdates()->create([
                'user_id' => $assignees[0]->id,
                'percent' => $progress,
                'comment' => 'Point d\'avancement initial.',
            ]);
        }

        return $task;
    }
}
