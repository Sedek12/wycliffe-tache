<?php

namespace App\Http\Controllers\Api;

use App\Enums\CollaborationRequestStatus;
use App\Enums\DepartmentRole;
use App\Enums\EvaluationMention;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;

class MetaController extends Controller
{
    /** Données de référence pour peupler les listes déroulantes du frontend. */
    public function index()
    {
        $enumList = fn (array $cases) => collect($cases)
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
            ->values();

        return response()->json([
            'task_statuses' => $enumList(TaskStatus::cases()),
            'department_roles' => $enumList(DepartmentRole::cases()),
            'system_roles' => $enumList(SystemRole::cases()),
            'evaluation_mentions' => $enumList(EvaluationMention::cases()),
            'collaboration_statuses' => $enumList(CollaborationRequestStatus::cases()),
            'status_transitions' => collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [
                $s->value => collect($s->allowedNext())->map->value->values(),
            ]),
            'project_roles' => $enumList(ProjectRole::cases()),
            'project_statuses' => $enumList(ProjectStatus::cases()),
            'project_status_transitions' => collect(ProjectStatus::cases())->mapWithKeys(fn ($s) => [
                $s->value => collect($s->allowedNext())->map->value->values(),
            ]),
        ]);
    }
}
