<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::REVIEWER,
            UserRole::COORDINATOR,
            UserRole::LEADER,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        if ($user->hasAnyRole([UserRole::SUPER_ADMIN, UserRole::ADMIN_CAMPAIGN, UserRole::REVIEWER])) {
            return true;
        }

        return $invitation->invited_by_user_id === $user->id
            || $invitation->leader_user_id === $user->id
            || $invitation->coordinator_user_id === $user->id;

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN, 
            UserRole::ADMIN_CAMPAIGN, 
            UserRole::REVIEWER, 
            UserRole::LEADER, 
            UserRole::COORDINATOR
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invitation $invitation): bool
    {
        if ($user->hasAnyRole([UserRole::SUPER_ADMIN, UserRole::ADMIN_CAMPAIGN, UserRole::REVIEWER])) {
            return true;
        }

        return $invitation->invited_by_user_id === $user->id && $invitation->status === 'pending';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        if ($user->hasAnyRole([UserRole::SUPER_ADMIN, UserRole::ADMIN_CAMPAIGN])) {
            return true;
        }

        if ($user->hasRole(UserRole::REVIEWER)) {
            return $invitation->status === 'pending';
        }

        return $invitation->invited_by_user_id === $user->id && $invitation->status === 'pending';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invitation $invitation): bool
    {
        return $user->hasAnyRole([UserRole::SUPER_ADMIN, UserRole::ADMIN_CAMPAIGN]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invitation $invitation): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN);
    }
}
