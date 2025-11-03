<?php

namespace Database\Factories;

use App\Enums\VoterStatus;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ValidationHistory>
 */
class ValidationHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voter_id' => Voter::factory(),
            'previous_status' => VoterStatus::PENDING_REVIEW,
            'new_status' => VoterStatus::VERIFIED_CENSUS,
            'validated_by' => User::factory(),
            'validation_type' => 'census',
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }

    /**
     * Indicate a census validation.
     */
    public function censusValidation(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => VoterStatus::PENDING_REVIEW,
            'new_status' => VoterStatus::VERIFIED_CENSUS,
            'validation_type' => 'census',
        ]);
    }

    /**
     * Indicate a call verification.
     */
    public function callValidation(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => VoterStatus::VERIFIED_CENSUS,
            'new_status' => VoterStatus::VERIFIED_CALL,
            'validation_type' => 'call',
        ]);
    }

    /**
     * Indicate a manual validation.
     */
    public function manualValidation(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_type' => 'manual',
        ]);
    }

    /**
     * Indicate a rejection.
     */
    public function rejection(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => VoterStatus::PENDING_REVIEW,
            'new_status' => VoterStatus::REJECTED_CENSUS,
            'validation_type' => 'census',
            'notes' => 'No se encontró en el censo electoral',
        ]);
    }
}
