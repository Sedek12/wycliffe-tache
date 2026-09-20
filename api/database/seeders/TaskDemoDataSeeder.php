<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Complète les données de démonstration déjà créées par DatabaseSeeder/ProjectSeeder :
 * journal d'activité (onglet « Activité » des tâches) et livrables déposés
 * (onglet « Livrables »), pour que les pages Tâches/Détail tâche ne soient
 * pas vides. Idempotent : ignore les tâches qui ont déjà de l'activité.
 */
class TaskDemoDataSeeder extends Seeder
{
    private const SAMPLE_MIMES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
    ];

    public function run(): void
    {
        $tasks = Task::with(['creator', 'assignees', 'statusHistory.changedBy', 'progressUpdates.user'])->get();
        $tasksBackfilled = 0;
        $deliverablesCreated = 0;

        foreach ($tasks as $task) {
            if ($task->activities()->exists()) {
                continue; // déjà journalisée (créée manuellement ou par un précédent run).
            }

            $this->backfillActivity($task);
            $tasksBackfilled++;

            // Sur ~45 % des tâches avancées, on simule un livrable déposé par un assigné.
            if ($task->progress > 0 && $task->assignees->isNotEmpty() && random_int(1, 100) <= 45) {
                $this->attachDeliverable($task);
                $deliverablesCreated++;
            }
        }

        $this->seedSettings();

        $this->command->info("Journal d'activité rempli sur {$tasksBackfilled} tâches, {$deliverablesCreated} livrables ajoutés, paramètres plateforme initialisés.");
    }

    private function backfillActivity(Task $task): int
    {
        $creator = $task->creator;
        if (! $creator) {
            return 0;
        }

        TaskActivity::log($task, 'task_created', "Tâche créée par {$creator->name}.", $creator);
        $count = 1;

        foreach ($task->statusHistory as $h) {
            if ($h->from_status === null) {
                continue; // déjà couvert par task_created ci-dessus.
            }
            $actor = $h->changedBy ?? $creator;
            $label = $h->to_status->label();
            TaskActivity::log(
                $task,
                'status_changed',
                "{$actor->name} a fait passer la tâche à « {$label} ».",
                $actor,
            );
            $count++;
        }

        foreach ($task->progressUpdates as $p) {
            $actor = $p->user;
            if (! $actor) {
                continue;
            }
            TaskActivity::log(
                $task,
                'progress_added',
                "{$actor->name} a soumis un point d'avancement ({$p->percent} %).",
                $actor,
            );
            $count++;
        }

        return $count;
    }

    private function attachDeliverable(Task $task): void
    {
        $uploader = $task->assignees->first();
        $ext = array_rand(self::SAMPLE_MIMES);
        $name = Str::slug(Str::limit($task->title, 40, '')) . "-v1.{$ext}";
        $path = "deliverables/{$task->id}/" . Str::random(20) . "_{$name}";

        Storage::disk('public')->put($path, "Document de démonstration pour « {$task->title} ».");

        $deliverable = Deliverable::create([
            'task_id' => $task->id,
            'uploaded_by' => $uploader->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $name,
            'mime' => self::SAMPLE_MIMES[$ext],
            'size' => Storage::disk('public')->size($path),
            'note' => 'Livrable de démonstration.',
        ]);

        TaskActivity::log(
            $task,
            'deliverable_added',
            "{$uploader->name} a publié le livrable « {$deliverable->original_name} ».",
            $uploader,
        );
    }

    /** Initialise explicitement les paramètres plateforme (au lieu de reposer sur les défauts implicites). */
    private function seedSettings(): void
    {
        \App\Models\Setting::put('files', [
            'max_size_mb' => 50,
            'allowed_extensions' => [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'mp3', 'wav', 'm4a', 'ogg',
                'mp4', 'webm', 'mov',
                'zip', 'txt', 'csv',
            ],
            'retention_days' => 365,
        ]);

        \App\Models\Setting::put('reminders', [
            'days_before_due' => [3, 1, 0],
        ]);
    }
}
