<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $electionDate = fake()->dateTimeBetween('+2 months', '+6 months');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'candidate_name' => fake()->name(),
            'start_date' => $startDate,
            'end_date' => fake()->optional()->dateTimeBetween($startDate, $electionDate),
            'election_date' => $electionDate,
            'status' => CampaignStatus::DRAFT,
            'settings' => [
                'welcome_message' => '¡Bienvenido a nuestra campaña!',
                'birthday_message' => '¡Feliz cumpleaños! Que tengas un excelente día.',
                'primary_color' => '#3B82F6',
                'secondary_color' => '#10B981',
            ],
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the campaign is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the campaign is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::PAUSED,
        ]);
    }

    /**
     * Indicate that the campaign is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::COMPLETED,
            'end_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
