<?php

namespace App\Rules;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Voter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DocumentNotBelongsToLeaderOrCoordinator implements ValidationRule
{
    public function __construct(private readonly ?int $excludeVoterId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $leaderOrCoordinator = User::query()
            ->role([UserRole::LEADER->value, UserRole::COORDINATOR->value])
            ->where('document_number', $value)
            ->first();

        if (! $leaderOrCoordinator) {
            return;
        }

        $isOwnSystemVoter = $this->excludeVoterId
            && Voter::whereKey($this->excludeVoterId)->where('user_id', $leaderOrCoordinator->id)->exists();

        if ($isOwnSystemVoter) {
            return;
        }

        $fail("Este documento pertenece a un líder/coordinador ({$leaderOrCoordinator->name}) y no puede registrarse como Apoyo.");
    }
}
