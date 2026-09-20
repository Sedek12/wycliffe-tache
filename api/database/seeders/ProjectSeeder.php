<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données de démonstration pour le Module 1 — Gestion de projets.
 * S'appuie sur les départements/utilisateurs déjà créés par DatabaseSeeder.
 * Idempotent : si un projet existe déjà (même titre + département), on ne
 * régénère pas ses activités/jalons/dépenses.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTraductionProject();
        $this->seedAlphabetisationProject();
        $this->seedDeveloppementProject();
        $this->seedCommunicationProject();

        $this->command->info('Projets de démonstration créés (Traduction, Alphabétisation, Développement communautaire, Communication).');
    }

    // ------------------------------------------------------------------
    // Projet 1 — en cours, avec dépendance bloquante et document versionné
    // ------------------------------------------------------------------
    private function seedTraductionProject(): void
    {
        $dept = Department::where('name', 'Traduction de la Bible')->first();
        if (! $dept) {
            return;
        }

        $jean = User::where('email', 'jean.kokou@wycliffebenin.org')->first();
        $awa = User::where('email', 'awa.dossou@wycliffebenin.org')->first();
        $paul = User::where('email', 'paul.agbessi@wycliffebenin.org')->first();
        $rebecca = User::where('email', 'rebecca.houngbo@wycliffebenin.org')->first();
        if (! $jean || ! $awa || ! $paul) {
            return;
        }

        [$project, $isNew] = $this->firstOrCreateProject($dept, $jean, [
            'title' => 'Traduction du Nouveau Testament — Programme 2026',
            'description' => "Traduction, vérification communautaire et diffusion du Nouveau Testament en langue locale.",
            'effet' => "Les Écritures du Nouveau Testament sont accessibles et comprises par la communauté linguistique.",
            'extrant' => "Nouveau Testament traduit, vérifié par la communauté et diffusé sous forme imprimée et audio.",
            'budget_previsionnel' => 8_000_000,
            'parties_prenantes' => ['Église locale partenaire', 'Alliance Biblique du Bénin', 'Bailleur SIL International'],
            'starts_at' => now()->subMonths(4),
            'due_at' => now()->addMonths(8),
            'status' => ProjectStatus::EnCours->value,
        ]);

        $this->syncMembers($project, [
            $jean->id => ProjectRole::ChefDepartement,
            $awa->id => ProjectRole::CoordonnateurFacilitateur,
            $paul->id => ProjectRole::Moniteur,
        ]);

        if (! $isNew) {
            return;
        }

        // Activité 1 : Marc — terminée, avec une sous-activité (vérification communautaire).
        $marc = $this->makeActivity($project, $jean, [
            'title' => 'Traduire l’Évangile de Marc',
            'description' => 'Traduction complète de l’Évangile de Marc à partir du texte grec, avec appui exégétique.',
            'status' => TaskStatus::Validee,
            'progress' => 100,
            'starts_at' => now()->subMonths(4),
            'due_at' => now()->subMonths(2),
            'assignees' => [['user' => $awa, 'hours' => 120]],
        ]);
        $marc->update(['completed_at' => now()->subMonths(2)->subDays(2)]);
        $marc->evaluation()->create(['evaluated_by' => $jean->id, 'score' => 18, 'appreciation' => 'Traduction fidèle, délais respectés.']);

        $this->makeActivity($project, $jean, [
            'title' => 'Vérification communautaire — Marc',
            'description' => 'Session de vérification avec un panel de lecteurs natifs.',
            'status' => TaskStatus::Validee,
            'progress' => 100,
            'starts_at' => now()->subMonths(2)->subDays(10),
            'due_at' => now()->subMonths(2),
            'assignees' => $rebecca ? [['user' => $rebecca, 'hours' => 24]] : [],
            'parent_id' => $marc->id,
        ]);

        // Activité 2 : Luc — en cours, dépend de Marc (déjà validée → non bloquée).
        $luc = $this->makeActivity($project, $jean, [
            'title' => 'Traduire l’Évangile de Luc',
            'description' => 'Traduction de l’Évangile de Luc, deuxième étape du programme.',
            'status' => TaskStatus::EnCours,
            'progress' => 55,
            'starts_at' => now()->subMonths(2),
            'due_at' => now()->addMonths(2),
            'assignees' => [['user' => $paul, 'hours' => 150]],
            'dependsOn' => [$marc->id],
        ]);

        // Activité 3 : glossaire — dépend de Luc (pas encore validée → BLOQUÉE, pour la démo).
        $this->makeActivity($project, $jean, [
            'title' => 'Réviser le glossaire théologique',
            'description' => 'Harmonisation des termes théologiques clés avant la phase finale.',
            'status' => TaskStatus::Assignee,
            'progress' => 0,
            'starts_at' => now()->addMonths(2),
            'due_at' => now()->addMonths(4),
            'assignees' => [['user' => $awa, 'hours' => 60]],
            'dependsOn' => [$luc->id],
        ]);

        $project->milestones()->create([
            'title' => 'Validation de l’Évangile de Marc',
            'date' => now()->subMonths(2),
            'is_reached' => true,
            'reached_at' => now()->subMonths(2)->subDays(2),
            'created_by' => $jean->id,
        ]);
        $project->milestones()->create([
            'title' => 'Lancement de la diffusion du Nouveau Testament',
            'date' => now()->addMonths(8),
            'is_reached' => false,
            'created_by' => $jean->id,
        ]);

        $project->expenses()->createMany([
            ['title' => 'Atelier de vérification communautaire', 'amount' => 850_000, 'date' => now()->subMonths(2), 'created_by' => $jean->id],
            ['title' => 'Impression de brouillons de travail', 'amount' => 320_000, 'date' => now()->subMonths(1), 'created_by' => $jean->id],
            ['title' => 'Déplacements équipe de traduction', 'amount' => 410_000, 'date' => now()->subDays(20), 'created_by' => $jean->id],
        ]);

        // Document projet avec 2 versions, pour démontrer le versioning.
        $this->makeVersionedDeliverable(
            $project,
            $jean,
            'rapport_etape_v1.pdf',
            "Rapport d'étape — version 1 (brouillon).",
            'rapport_etape_v2.pdf',
            "Rapport d'étape — version 2 (corrigée par le chef de département).",
        );
    }

    // ------------------------------------------------------------------
    // Projet 2 — en cours, dépassement budgétaire volontaire (démo « à risque »)
    // ------------------------------------------------------------------
    private function seedAlphabetisationProject(): void
    {
        $dept = Department::where('name', 'Alphabétisation')->first();
        if (! $dept) {
            return;
        }

        $marthe = User::where('email', 'marthe.sagbo@wycliffebenin.org')->first();
        $isaac = User::where('email', 'isaac.adjovi@wycliffebenin.org')->first();
        $grace = User::where('email', 'grace.tossou@wycliffebenin.org')->first();
        if (! $marthe || ! $isaac || ! $grace) {
            return;
        }

        [$project, $isNew] = $this->firstOrCreateProject($dept, $marthe, [
            'title' => 'Campagne d’alphabétisation fonctionnelle 2026',
            'description' => 'Ouverture de centres d’alphabétisation et formation de moniteurs dans les zones ciblées.',
            'effet' => 'Les adultes des communautés ciblées lisent et écrivent en langue locale.',
            'extrant' => '300 apprenants formés dans 10 centres d’alphabétisation.',
            'budget_previsionnel' => 2_500_000,
            'parties_prenantes' => ['Mairie locale', 'Comité de développement villageois'],
            'starts_at' => now()->subMonths(3),
            'due_at' => now()->addMonths(3),
            'status' => ProjectStatus::EnCours->value,
        ]);

        $this->syncMembers($project, [
            $marthe->id => ProjectRole::ChefDepartement,
            $isaac->id => ProjectRole::FacilitateurZone,
            $grace->id => ProjectRole::Moniteur,
        ]);

        if (! $isNew) {
            return;
        }

        $this->makeActivity($project, $marthe, [
            'title' => 'Former les moniteurs alpha',
            'description' => 'Formation initiale des moniteurs recrutés dans les 10 centres.',
            'status' => TaskStatus::Validee,
            'progress' => 100,
            'starts_at' => now()->subMonths(3),
            'due_at' => now()->subMonths(2),
            'assignees' => [['user' => $isaac, 'hours' => 80]],
        ]);

        // Volontairement en retard (due_at dans le passé, statut non clos) pour peupler
        // le signal « à risque » du tableau de bord projet.
        $this->makeActivity($project, $marthe, [
            'title' => 'Ouvrir les centres d’alphabétisation',
            'description' => 'Mise en place effective des 10 centres et démarrage des sessions.',
            'status' => TaskStatus::EnCours,
            'progress' => 60,
            'starts_at' => now()->subMonths(2),
            'due_at' => now()->subDays(15),
            'assignees' => [['user' => $grace, 'hours' => 100]],
        ]);

        $project->milestones()->create([
            'title' => 'Mi-parcours de la campagne',
            'date' => now()->subMonth(),
            'is_reached' => true,
            'reached_at' => now()->subMonth(),
            'created_by' => $marthe->id,
        ]);

        // Dépenses dépassant volontairement le budget prévisionnel (2 500 000).
        $project->expenses()->createMany([
            ['title' => 'Kits pédagogiques (10 centres)', 'amount' => 1_400_000, 'date' => now()->subMonths(2), 'created_by' => $marthe->id],
            ['title' => 'Indemnités des moniteurs', 'amount' => 1_100_000, 'date' => now()->subMonth(), 'created_by' => $marthe->id],
            ['title' => 'Location de salles supplémentaires', 'amount' => 550_000, 'date' => now()->subDays(10), 'created_by' => $marthe->id],
        ]);
    }

    // ------------------------------------------------------------------
    // Projet 3 — brouillon (en cadrage, rien démarré)
    // ------------------------------------------------------------------
    private function seedDeveloppementProject(): void
    {
        $dept = Department::where('name', 'Développement communautaire')->first();
        if (! $dept) {
            return;
        }

        $samuel = User::where('email', 'samuel.ahouansou@wycliffebenin.org')->first();
        if (! $samuel) {
            return;
        }

        [$project, $isNew] = $this->firstOrCreateProject($dept, $samuel, [
            'title' => 'Programme d’accès à l’eau potable',
            'description' => 'Construction de forages dans les communautés partenaires les plus enclavées.',
            'effet' => 'Les communautés ciblées ont un accès durable à l’eau potable.',
            'extrant' => '5 forages construits, équipés et fonctionnels.',
            'budget_previsionnel' => 12_000_000,
            'parties_prenantes' => ['Comités de gestion de l’eau', 'Bailleur partenaire international'],
            'starts_at' => now()->addMonth(),
            'due_at' => now()->addMonths(10),
            'status' => ProjectStatus::Brouillon->value,
        ]);

        $this->syncMembers($project, [$samuel->id => ProjectRole::ChefDepartement]);

        if (! $isNew) {
            return;
        }

        $this->makeActivity($project, $samuel, [
            'title' => 'Étude de faisabilité des sites',
            'description' => 'Identification et validation technique des 5 sites de forage.',
            'status' => TaskStatus::Brouillon,
            'progress' => 0,
            'starts_at' => now()->addMonth(),
            'due_at' => now()->addMonths(2),
            'assignees' => [],
        ]);
    }

    // ------------------------------------------------------------------
    // Projet 4 — clôturé (terminé, budget respecté)
    // ------------------------------------------------------------------
    private function seedCommunicationProject(): void
    {
        $dept = Department::where('name', 'Communication')->first();
        if (! $dept) {
            return;
        }

        $debora = User::where('email', 'debora.aholou@wycliffebenin.org')->first();
        $elie = User::where('email', 'elie.sossou@wycliffebenin.org')->first();
        if (! $debora || ! $elie) {
            return;
        }

        [$project, $isNew] = $this->firstOrCreateProject($dept, $debora, [
            'title' => 'Refonte du site web et des supports de communication',
            'description' => 'Modernisation de l’identité visuelle et du site institutionnel de Wycliffe Bénin.',
            'effet' => 'La communication institutionnelle reflète l’activité actuelle de l’organisation.',
            'extrant' => 'Nouvelle charte graphique et site web publiés.',
            'budget_previsionnel' => 1_800_000,
            'parties_prenantes' => ['Direction exécutive'],
            'starts_at' => now()->subMonths(6),
            'due_at' => now()->subMonth(),
            'status' => ProjectStatus::Cloture->value,
        ]);

        $this->syncMembers($project, [
            $debora->id => ProjectRole::ChefDepartement,
            $elie->id => ProjectRole::CoordonnateurFacilitateur,
        ]);

        if (! $isNew) {
            return;
        }

        $this->makeActivity($project, $debora, [
            'title' => 'Concevoir la nouvelle charte graphique',
            'description' => 'Logo, palette, typographie et gabarits de communication.',
            'status' => TaskStatus::Validee,
            'progress' => 100,
            'starts_at' => now()->subMonths(6),
            'due_at' => now()->subMonths(4),
            'assignees' => [['user' => $elie, 'hours' => 60]],
        ]);
        $this->makeActivity($project, $debora, [
            'title' => 'Développer le nouveau site institutionnel',
            'description' => 'Développement et mise en ligne du site.',
            'status' => TaskStatus::Validee,
            'progress' => 100,
            'starts_at' => now()->subMonths(4),
            'due_at' => now()->subMonth(),
            'assignees' => [['user' => $elie, 'hours' => 140]],
        ]);

        $project->milestones()->create([
            'title' => 'Mise en ligne du nouveau site',
            'date' => now()->subMonth(),
            'is_reached' => true,
            'reached_at' => now()->subMonth(),
            'created_by' => $debora->id,
        ]);

        $project->expenses()->create([
            'title' => 'Prestation agence web',
            'amount' => 1_500_000,
            'date' => now()->subMonths(2),
            'created_by' => $debora->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array{0: Project, 1: bool} [projet, a été créé maintenant ?] */
    private function firstOrCreateProject(Department $dept, User $creator, array $attributes): array
    {
        $existing = Project::where('department_id', $dept->id)->where('title', $attributes['title'])->first();
        if ($existing) {
            return [$existing, false];
        }

        $project = Project::create(array_merge($attributes, [
            'department_id' => $dept->id,
            'created_by' => $creator->id,
        ]));

        return [$project, true];
    }

    private function syncMembers(Project $project, array $roleByUserId): void
    {
        $sync = [];
        foreach ($roleByUserId as $userId => $role) {
            $sync[$userId] = ['role' => $role instanceof ProjectRole ? $role->value : $role];
        }
        $project->members()->syncWithoutDetaching($sync);
    }

    private function makeActivity(Project $project, User $creator, array $spec): Task
    {
        $task = Task::create([
            'department_id' => $project->department_id,
            'project_id' => $project->id,
            'parent_id' => $spec['parent_id'] ?? null,
            'created_by' => $creator->id,
            'supervisor_id' => $creator->id,
            'title' => $spec['title'],
            'description' => $spec['description'],
            'starts_at' => $spec['starts_at'],
            'due_at' => $spec['due_at'],
            'status' => $spec['status'],
            'progress' => $spec['progress'],
            'is_team' => count($spec['assignees'] ?? []) > 1,
        ]);

        foreach ($spec['assignees'] ?? [] as $a) {
            $task->assignees()->attach($a['user']->id, [
                'estimated_hours' => $a['hours'] ?? null,
            ]);
        }

        $task->statusHistory()->create([
            'from_status' => null,
            'to_status' => $spec['status']->value,
            'changed_by' => $creator->id,
        ]);

        foreach ($spec['dependsOn'] ?? [] as $dependsOnId) {
            $task->dependsOn()->attach($dependsOnId);
        }

        return $task;
    }

    private function makeVersionedDeliverable(
        Project $project,
        User $uploader,
        string $nameV1,
        string $noteV1,
        string $nameV2,
        string $noteV2,
    ): void {
        $dir = "deliverables/projects/{$project->id}";

        $pathV1 = "{$dir}/" . Str::random(20) . '_' . $nameV1;
        Storage::disk('public')->put($pathV1, "Document de démonstration — {$noteV1}");

        $v1 = $project->deliverables()->create([
            'uploaded_by' => $uploader->id,
            'disk' => 'public',
            'path' => $pathV1,
            'original_name' => $nameV1,
            'mime' => 'application/pdf',
            'size' => Storage::disk('public')->size($pathV1),
            'note' => $noteV1,
            'version' => 1,
        ]);

        $pathV2 = "{$dir}/" . Str::random(20) . '_' . $nameV2;
        Storage::disk('public')->put($pathV2, "Document de démonstration — {$noteV2}");

        $project->deliverables()->create([
            'uploaded_by' => $uploader->id,
            'disk' => 'public',
            'path' => $pathV2,
            'original_name' => $nameV2,
            'mime' => 'application/pdf',
            'size' => Storage::disk('public')->size($pathV2),
            'note' => $noteV2,
            'version' => 2,
            'group_key' => $v1->group_key,
        ]);
    }
}
