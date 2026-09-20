<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Setting;
use App\Models\Task;
use App\Notifications\TaskDeadlineReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDeadlineReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Envoie les rappels d\'échéance (J-3, J-1, jour J) et les alertes de retard.';

    public function handle(): int
    {
        $daysBefore = collect(Setting::get('reminders')['days_before_due'] ?? [3, 1, 0])
            ->map(fn ($d) => (int) $d);

        $openStatuses = collect(TaskStatus::cases())
            ->reject(fn ($s) => $s === TaskStatus::Validee)
            ->map->value
            ->all();

        $sent = 0;

        // Rappels avant échéance.
        foreach ($daysBefore as $days) {
            $target = Carbon::today()->addDays($days);

            $tasks = Task::query()
                ->whereIn('status', $openStatuses)
                ->whereDate('due_at', $target)
                ->with(['assignees', 'creator'])
                ->get();

            foreach ($tasks as $task) {
                foreach ($this->recipients($task) as $user) {
                    $user->notify(new TaskDeadlineReminder($task, $days));
                    $sent++;
                }
            }
        }

        // Alerte de retard : échéance dépassée hier (une seule relance).
        $overdue = Task::query()
            ->whereIn('status', $openStatuses)
            ->whereDate('due_at', Carbon::yesterday())
            ->with(['assignees', 'creator'])
            ->get();

        foreach ($overdue as $task) {
            foreach ($this->recipients($task) as $user) {
                $user->notify(new TaskDeadlineReminder($task, 0, overdue: true));
                $sent++;
            }
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function recipients(Task $task)
    {
        return $task->assignees
            ->push($task->creator)
            ->filter()
            ->unique('id');
    }
}
