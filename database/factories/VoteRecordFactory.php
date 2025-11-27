<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VoteRecord>
 */
class VoteRecordFactory extends Factory
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
            'campaign_id' => Campaign::factory(),
            'recorded_by' => User::factory(),
            'voted_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'photo_path' => fake()->boolean(60) ? 'votes/'.fake()->uuid().'.jpg' : null,
            'latitude' => fake()->boolean(70) ? fake()->latitude(1.0, 12.0) : null,
            'longitude' => fake()->boolean(70) ? fake()->longitude(-79.0, -66.0) : null,
            'polling_station' => fake()->boolean(80) ? 'Mesa '.fake()->numberBetween(1, 100).' - Puesto '.fake()->numberBetween(1, 50) : null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the vote record has a photo
     */
    public function withPhoto(): static
    {
        return $this->state(fn (array $attributes) => [
            'photo_path' => 'votes/'.fake()->uuid().'.jpg',
        ]);
    }

    /**
     * Indicate that the vote record has GPS location
     */
    public function withLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => fake()->latitude(1.0, 12.0),
            'longitude' => fake()->longitude(-79.0, -66.0),
        ]);
    }

    /**
     * Indicate that the vote record has complete information
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'photo_path' => 'votes/'.fake()->uuid().'.jpg',
            'latitude' => fake()->latitude(1.0, 12.0),
            'longitude' => fake()->longitude(-79.0, -66.0),
            'polling_station' => 'Mesa '.fake()->numberBetween(1, 100).' - Puesto '.fake()->numberBetween(1, 50),
            'notes' => fake()->sentence(),
        ]);
    }
}
