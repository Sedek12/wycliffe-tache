<?php

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public TaskStatus $from,
        public TaskStatus $to,
        public User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tâche « {$this->task->title} » : {$this->to->label()}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->actor->name} a fait passer la tâche de « {$this->from->label()} » à « {$this->to->label()} ».")
            ->action('Voir la tâche', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_status_changed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'by' => $this->actor->name,
            'message' => "« {$this->task->title} » : {$this->to->label()} (par {$this->actor->name}).",
        ];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . "/taches/{$this->task->id}";
    }
}
