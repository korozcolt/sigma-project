<?php

namespace Database\Factories;

use App\Enums\PollingPlaceSource;
use App\Models\PollingPlace;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PollingPlaceResolution>
 */
class PollingPlaceResolutionFactory extends Factory
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
            'previous_source' => null,
            'new_source' => PollingPlaceSource::LIVE,
            'polling_place_id' => PollingPlace::factory(),
            'table_number' => (string) fake()->numberBetween(1, 20),
            'resolved_by' => User::factory(),
            'resolved_via' => 'interactive',
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    /**
     * Indicate an interactive (human-driven) resolution.
     */
    public function interactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_by' => User::factory(),
            'resolved_via' => 'interactive',
        ]);
    }

    /**
     * Indicate a headless reconciliation-job resolution (D-05: no human actor).
     */
    public function reconciliation(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_source' => PollingPlaceSource::SNAPSHOT,
            'new_source' => PollingPlaceSource::LIVE,
            'resolved_by' => null,
            'resolved_via' => 'reconciliation',
        ]);
    }
}
