<?php

namespace App\Notifications;

use App\Models\Deliverable;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliverableSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public Deliverable $deliverable,
        public User $uploader,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Livrable déposé : {$this->task->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->uploader->name} a déposé un livrable pour « {$this->task->title} ».")
            ->line("Fichier : {$this->deliverable->original_name}")
            ->action('Consulter', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deliverable_submitted',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'deliverable_id' => $this->deliverable->id,
            'file' => $this->deliverable->original_name,
            'by' => $this->uploader->name,
            'message' => "{$this->uploader->name} a déposé un livrable pour « {$this->task->title} ».",
        ];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . "/taches/{$this->task->id}";
    }
}
