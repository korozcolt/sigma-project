<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Survey>
 */
class SurveyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => \App\Models\Campaign::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'is_active' => fake()->boolean(80),
            'version' => 1,
            'parent_survey_id' => null,
        ];
    }

    /**
     * Indicate that the survey is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the survey is a new version of an existing survey.
     */
    public function newVersion(int $parentSurveyId, int $version = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_survey_id' => $parentSurveyId,
            'version' => $version,
        ]);
    }
}
