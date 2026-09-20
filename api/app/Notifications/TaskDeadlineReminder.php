<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDeadlineReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public int $daysLeft,
        public bool $overdue = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = match (true) {
            $this->overdue => 'est en retard',
            $this->daysLeft === 0 => 'arrive à échéance aujourd’hui',
            $this->daysLeft === 1 => 'arrive à échéance demain',
            default => "arrive à échéance dans {$this->daysLeft} jours",
        };

        return (new MailMessage)
            ->subject("Rappel : « {$this->task->title} » {$when}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La tâche « {$this->task->title} » {$when}.")
            ->line('Échéance : ' . $this->task->due_at->format('d/m/Y H:i'))
            ->line("Avancement actuel : {$this->task->progress}%")
            ->action('Ouvrir la tâche', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->overdue ? 'task_overdue' : 'task_due_soon',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'days_left' => $this->daysLeft,
            'overdue' => $this->overdue,
            'due_at' => $this->task->due_at,
            'message' => $this->overdue
                ? "« {$this->task->title} » est en retard."
                : "« {$this->task->title} » arrive à échéance (J-{$this->daysLeft}).",
        ];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . "/taches/{$this->task->id}";
    }
}
