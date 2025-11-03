<?php

namespace Database\Factories;

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voter>
 */
class VoterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'document_number' => fake()->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->boolean(80) ? fake()->dateTimeBetween('-70 years', '-18 years') : null,
            'phone' => fake()->numerify('3## ### ####'),
            'secondary_phone' => fake()->boolean(40) ? fake()->numerify('3## ### ####') : null,
            'email' => fake()->boolean(60) ? fake()->unique()->safeEmail() : null,
            'municipality_id' => Municipality::inRandomOrder()->first()?->id ?? Municipality::factory(),
            'neighborhood_id' => fake()->boolean(70) ? (Neighborhood::inRandomOrder()->first()?->id ?? Neighborhood::factory()) : null,
            'address' => fake()->boolean(80) ? fake()->address() : null,
            'detailed_address' => fake()->boolean(50) ? fake()->streetAddress() : null,
            'registered_by' => User::factory(),
            'status' => VoterStatus::PENDING_REVIEW,
            'census_validated_at' => null,
            'call_verified_at' => null,
            'confirmed_at' => null,
            'voted_at' => null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }

    /**
     * Indicate that the voter has been verified in the census
     */
    public function verifiedCensus(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::VERIFIED_CENSUS,
            'census_validated_at' => now(),
        ]);
    }

    /**
     * Indicate that the voter has been verified by call
     */
    public function verifiedCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::VERIFIED_CALL,
            'call_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the voter is confirmed
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::CONFIRMED,
            'census_validated_at' => now(),
            'call_verified_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the voter voted
     */
    public function voted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::VOTED,
            'census_validated_at' => now(),
            'call_verified_at' => now(),
            'confirmed_at' => now(),
            'voted_at' => now(),
        ]);
    }

    /**
     * Indicate that the voter did not vote
     */
    public function didNotVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::DID_NOT_VOTE,
            'census_validated_at' => now(),
            'call_verified_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the voter was rejected in census
     */
    public function rejectedCensus(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoterStatus::REJECTED_CENSUS,
            'notes' => 'No se encontró en el censo electoral',
        ]);
    }
}
