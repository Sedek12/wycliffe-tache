<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'effet' => $this->effet,
            'extrant' => $this->extrant,
            'budget_previsionnel' => $this->budget_previsionnel !== null ? (float) $this->budget_previsionnel : null,
            'depenses_engagees' => $this->depenses_engagees,
            'budget_consumed_pct' => $this->budget_consumed_pct,
            'parties_prenantes' => $this->parties_prenantes ?? [],
            'starts_at' => $this->starts_at,
            'due_at' => $this->due_at,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_next' => collect($this->status->allowedNext())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'progress' => $this->progress,
            'risk_flag' => $this->risk_flag,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
                'avatar_url' => $u->avatar_url,
                'role' => $u->pivot->role,
                'role_label' => \App\Enums\ProjectRole::from($u->pivot->role)->label(),
            ])),
            'activities' => $this->whenLoaded('activities', fn () => TaskResource::collection($this->activities)),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'date' => $m->date,
                'is_reached' => $m->is_reached,
                'reached_at' => $m->reached_at,
            ])),
            'expenses' => $this->whenLoaded('expenses', fn () => $this->expenses->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'date' => $e->date,
                'note' => $e->note,
                'created_by' => $e->creator?->name,
            ])),
            'deliverables' => $this->whenLoaded('deliverables', fn () => $this->deliverables->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'mime' => $d->mime,
                'size' => $d->size,
                'note' => $d->note,
                'version' => $d->version,
                'group_key' => $d->group_key,
                'url' => $d->url,
                'preview_url' => $d->preview_url,
                'uploader' => $d->uploader ? ['id' => $d->uploader->id, 'name' => $d->uploader->name] : null,
                'created_at' => $d->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
