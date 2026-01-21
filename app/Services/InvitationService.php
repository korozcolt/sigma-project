<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;

class InvitationService
{
    public function validateInvitation(string $token): ?Invitation
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    public function hasRegistrationAssignee(Invitation $invitation): bool
    {
        return (bool) $invitation->leader_user_id;
    }

    public function getRegistrationAssigneeUserId(Invitation $invitation): int
    {
        if (! $invitation->leader_user_id) {
            throw new \InvalidArgumentException('La invitación no tiene un líder asignado.');
        }

        return $invitation->leader_user_id;
    }

    public function getInvitationStats(User $user): array
    {
        $query = Invitation::query()->where('invited_by_user_id', $user->id);

        return [
            'total' => $query->count(),
            'activas' => $query->clone()->where('status', 'pending')->count(),
            'expiradas' => $query->clone()->where('status', 'expired')->count(),
            'desactivadas' => $query->clone()->where('status', 'cancelled')->count(),
        ];
    }
}
