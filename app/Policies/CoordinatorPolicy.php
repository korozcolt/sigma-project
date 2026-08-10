<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoordinatorPolicy
{
    /**
     * Determine whether the user can view the model.
     *
     * Purely additive (Phase 13 D-03): the only new restriction this policy
     * introduces is denying an articulador (area_coordinator) direct access to
     * a coordinador that is not their own. Every other actor/target combination
     * (admin, coordinador, leader viewing any User record, or an articulador
     * viewing a non-coordinador User record) is unrestricted — this preserves
     * today's behavior on UserResource/LeaderResource/CoordinatorResource,
     * since no User-model policy existed before this one.
     */
    public function view(User $user, User $coordinator): Response
    {
        return $this->authorizeOwnership($user, $coordinator);
    }

    /**
     * Determine whether the user can update the model. Same ownership rule as view().
     */
    public function update(User $user, User $coordinator): Response
    {
        return $this->authorizeOwnership($user, $coordinator);
    }

    private function authorizeOwnership(User $user, User $coordinator): Response
    {
        if (! $user->hasRole(UserRole::AREA_COORDINATOR->value)) {
            return Response::allow();
        }

        if (! $coordinator->hasRole(UserRole::COORDINATOR->value)) {
            return Response::allow();
        }

        return $coordinator->area_coordinator_user_id === $user->id
            ? Response::allow()
            : Response::deny('Este coordinador no pertenece a tu equipo de articulador.');
    }
}
