<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'slug' => $this->department->slug,
            ]),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent
                ? ['id' => $this->parent->id, 'title' => $this->parent->title]
                : null),
            'project_id' => $this->project_id,
            'depends_on' => $this->whenLoaded('dependsOn', fn () => $this->dependsOn->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
            ])),
            'delegated_abilities' => $this->delegated_abilities ?? [],
            'sub_tasks' => $this->whenLoaded('subTasks', fn () => $this->subTasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'progress' => $t->progress,
                'due_at' => $t->due_at,
                'is_overdue' => $t->is_overdue,
            ])),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_next' => collect($this->status->allowedNext())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'progress' => $this->progress,
            'is_team' => $this->is_team,
            'is_overdue' => $this->is_overdue,
            'starts_at' => $this->starts_at,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor ? [
                'id' => $this->supervisor->id,
                'name' => $this->supervisor->name,
                'initials' => $this->supervisor->initials,
                'avatar_url' => $this->supervisor->avatar_url,
            ] : null),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
                'avatar_url' => $u->avatar_url,
                'instructions' => $u->pivot->instructions,
                'estimated_hours' => $u->pivot->estimated_hours !== null ? (float) $u->pivot->estimated_hours : null,
                'is_external' => (bool) $u->pivot->is_external,
                'is_done' => (bool) $u->pivot->is_done,
                'done_at' => $u->pivot->done_at,
                'reopen_requested_at' => $u->pivot->reopen_requested_at,
            ])),
            'all_parts_done' => $this->whenLoaded('assignees', fn () => $this->assignees->isNotEmpty()
                && $this->assignees->every(fn ($u) => $u->pivot->is_done)),
            'progress_updates' => $this->whenLoaded('progressUpdates', fn () => $this->progressUpdates->map(fn ($p) => [
                'id' => $p->id,
                'percent' => $p->percent,
                'stage' => $p->stage,
                'stage_label' => \App\Models\ProgressUpdate::STAGES[$p->stage]['label'] ?? null,
                'comment' => $p->comment,
                'user' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null,
                'proof_url' => $p->proof_url,
                'proof_preview_url' => $p->proof_preview_url,
                'proof_name' => $p->original_name,
                'proof_mime' => $p->mime,
                'reviewed_at' => $p->reviewed_at,
                'reviewed_by' => $p->reviewer?->name,
                'rejected_at' => $p->rejected_at,
                'rejected_by' => $p->rejecter?->name,
                'created_at' => $p->created_at,
            ])),
            'progress_stages' => collect(\App\Models\ProgressUpdate::STAGES)->map(fn ($s, $k) => [
                'value' => $k,
                'label' => $s['label'],
                'band' => $s['band'],
                'percent' => $s['percent'],
            ])->values(),
            'deliverables' => $this->whenLoaded('deliverables', fn () => $this->deliverables->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'mime' => $d->mime,
                'size' => $d->size,
                'note' => $d->note,
                'url' => $d->url,
                'preview_url' => $d->preview_url,
                'uploader' => $d->uploader ? ['id' => $d->uploader->id, 'name' => $d->uploader->name] : null,
                'created_at' => $d->created_at,
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_status?->value,
                'to' => $h->to_status?->value,
                'to_label' => $h->to_status?->label(),
                'note' => $h->note,
                'by' => $h->changedBy?->name,
                'created_at' => $h->created_at,
            ])),
            'evaluation' => $this->whenLoaded('evaluation', fn () => $this->evaluation ? [
                'score' => $this->evaluation->score,
                'mention' => $this->evaluation->mention->value,
                'mention_label' => $this->evaluation->mention->label(),
                'appreciation' => $this->evaluation->appreciation,
                'evaluator' => $this->evaluation->evaluator?->name,
                'created_at' => $this->evaluation->created_at,
            ] : null),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'stream' => $a->stream,
                'description' => $a->description,
                'actor' => $a->actor?->name,
                'created_at' => $a->created_at,
            ])),
            'collaboration_requests' => $this->whenLoaded('collaborationRequests', fn () => $this->collaborationRequests->map(fn ($c) => [
                'id' => $c->id,
                'status' => $c->status->value,
                'target_user' => $c->targetUser?->name,
                'target_user_id' => $c->target_user_id,
                'to_department_id' => $c->to_department_id,
                'from_department_id' => $c->from_department_id,
                'requested_by' => $c->requested_by,
                'created_at' => $c->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
