<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'positions_count' => $this->when(isset($this->positions_count), $this->positions_count),
            'has_chef' => $this->when(isset($this->chefs_count), fn () => $this->chefs_count > 0),
            'positions' => $this->whenLoaded('positions', fn () => $this->positions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'members_count' => $p->members_count ?? null,
            ])),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at,
        ];
    }
}
