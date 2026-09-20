<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nouvelle tâche : {$this->task->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->assignedBy->name} vous a assigné une tâche dans le département « {$this->task->department->name} ».")
            ->line("**{$this->task->title}**")
            ->line("Début : " . $this->task->starts_at->format('d/m/Y H:i'))
            ->line("Échéance : " . $this->task->due_at->format('d/m/Y H:i'))
            ->action('Ouvrir la tâche', $this->url())
            ->line('Merci de déposer votre livrable avant l’échéance.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'department' => $this->task->department->name,
            'by' => $this->assignedBy->name,
            'due_at' => $this->task->due_at,
            'message' => "{$this->assignedBy->name} vous a assigné « {$this->task->title} ».",
        ];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . "/taches/{$this->task->id}";
    }
}
