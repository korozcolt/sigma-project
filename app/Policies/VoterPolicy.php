<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Voter;

class VoterPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Voter $voter): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Voter $voter): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Voter $voter): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Voter $voter): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can bulk restore models.
     */
    public function restoreAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Voter $voter): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can bulk permanently delete models.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, Voter $voter): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }

    /**
     * Determine whether the user can reorder models.
     */
    public function reorder(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN_CAMPAIGN,
            UserRole::COORDINATOR,
            UserRole::LEADER,
            UserRole::REVIEWER,
        ]);
    }
}
