<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'job_title' => $this->job_title,
            'system_role' => $this->system_role?->value,
            'system_role_label' => $this->system_role?->label(),
            'is_active' => $this->is_active,
            'avatar_url' => $this->avatar_url,
            'initials' => $this->initials,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'role' => $d->pivot->role,
                'position_id' => $d->pivot->position_id,
            ])),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'role' => $p->pivot->role,
            ])),
            'pivot' => $this->when(
                $this->pivot !== null,
                fn () => [
                    'role' => $this->pivot->role ?? null,
                    'position_id' => $this->pivot->position_id ?? null,
                ]
            ),
            'created_at' => $this->created_at,
        ];
    }
}
