<?php

namespace App\Policies;

use App\Enums\CollaborationRequestStatus;
use App\Models\CollaborationRequest;
use App\Models\User;

class CollaborationRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, CollaborationRequest $request): bool
    {
        return $user->isSupervisor()
            || $user->id === $request->requested_by
            || $user->id === $request->target_user_id
            || $user->isChefOf($request->from_department_id)
            || $user->isChefOf($request->to_department_id);
    }

    /** Accepter ou refuser : le chef du département sollicité. */
    public function respond(User $user, CollaborationRequest $request): bool
    {
        return $request->status === CollaborationRequestStatus::EnAttente
            && $user->isChefOf($request->to_department_id);
    }

    /** Annuler une demande encore en attente : son émetteur. */
    public function cancel(User $user, CollaborationRequest $request): bool
    {
        return $request->status === CollaborationRequestStatus::EnAttente
            && $user->id === $request->requested_by;
    }
}
