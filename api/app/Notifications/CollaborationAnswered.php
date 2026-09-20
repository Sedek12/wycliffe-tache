<?php

namespace App\Notifications;

use App\Models\CollaborationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CollaborationAnswered extends Notification
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
        $verdict = $r->status->label();

        return (new MailMessage)
            ->subject("Demande de collaboration {$verdict} — {$r->task->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$r->respondedBy?->name} a {$verdict} votre demande d’associer {$r->targetUser->name} à « {$r->task->title} ».")
            ->when($r->response_note, fn ($m) => $m->line("Note : {$r->response_note}"))
            ->action('Voir la tâche', rtrim(config('app.frontend_url'), '/') . "/taches/{$r->task_id}");
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->request;

        return [
            'type' => 'collaboration_answered',
            'request_id' => $r->id,
            'task_id' => $r->task_id,
            'task_title' => $r->task->title,
            'status' => $r->status->value,
            'target_user' => $r->targetUser->name,
            'by' => $r->respondedBy?->name,
            'message' => "Demande pour « {$r->task->title} » : {$r->status->label()}.",
        ];
    }
}
