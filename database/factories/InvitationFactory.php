<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Invitation;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(60),
            'invited_by_user_id' => User::factory(),
            'invited_email' => fake()->safeEmail(),
            'invited_name' => fake()->name(),
            'target_role' => 'LEADER',
            'campaign_id' => Campaign::factory(),
            'municipality_id' => Municipality::factory(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    public function forLeader(User $leader): static
    {
        return $this->state(fn () => [
            'leader_user_id' => $leader->id,
            'target_role' => 'LEADER',
        ]);
    }

    public function forCoordinator(User $coordinator): static
    {
        return $this->state(fn () => [
            'coordinator_user_id' => $coordinator->id,
            'target_role' => 'COORDINATOR',
        ]);
    }
}
