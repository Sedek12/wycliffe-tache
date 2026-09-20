<?php

namespace App\Notifications;

use App\Models\CollaborationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CollaborationRequested extends Notification
{
    use Queueable;

    public function __construct(public CollaborationRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;

        return (new MailMessage)
            ->subject("Demande de collaboration — {$r->targetUser->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$r->requester->name} ({$r->fromDepartment->name}) souhaite associer {$r->targetUser->name} de votre département à la tâche « {$r->task->title} ».")
            ->when($r->message, fn ($m) => $m->line("Message : {$r->message}"))
            ->action('Répondre à la demande', rtrim(config('app.frontend_url'), '/') . '/demandes')
            ->line('La personne ne rejoint la tâche qu’après votre validation.');
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->request;

        return [
            'type' => 'collaboration_requested',
            'request_id' => $r->id,
            'task_id' => $r->task_id,
            'task_title' => $r->task->title,
            'target_user' => $r->targetUser->name,
            'from_department' => $r->fromDepartment->name,
            'by' => $r->requester->name,
            'message' => "{$r->requester->name} demande {$r->targetUser->name} pour « {$r->task->title} ».",
        ];
    }
}
