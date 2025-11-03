<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'campaign_id' => null, // Global by default
            'name' => fake()->streetName(),
            'is_global' => true,
        ];
    }

    /**
     * Indicate that the neighborhood is global.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => null,
            'is_global' => true,
        ]);
    }

    /**
     * Indicate that the neighborhood belongs to a campaign.
     */
    public function forCampaign(int $campaignId): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => $campaignId,
            'is_global' => false,
        ]);
    }
}
