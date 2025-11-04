<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SurveyMetrics>
 */
class SurveyMetricsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => \App\Models\Survey::factory(),
            'survey_question_id' => null,
            'metric_type' => 'overall',
            'total_responses' => fake()->numberBetween(0, 100),
            'response_rate' => fake()->randomFloat(2, 0, 100),
            'average_value' => null,
            'distribution' => null,
            'metadata' => null,
            'calculated_at' => now(),
        ];
    }

    public function questionDistribution(): static
    {
        return $this->state(fn (array $attributes) => [
            'survey_question_id' => \App\Models\SurveyQuestion::factory(),
            'metric_type' => 'question_distribution',
            'distribution' => [
                'Option A' => ['count' => 10, 'percentage' => 50.0],
                'Option B' => ['count' => 10, 'percentage' => 50.0],
            ],
        ]);
    }

    public function questionAverage(): static
    {
        return $this->state(fn (array $attributes) => [
            'survey_question_id' => \App\Models\SurveyQuestion::factory(),
            'metric_type' => 'question_average',
            'average_value' => fake()->randomFloat(2, 1, 5),
            'distribution' => [
                '1' => ['count' => 5, 'percentage' => 10.0],
                '2' => ['count' => 10, 'percentage' => 20.0],
                '3' => ['count' => 15, 'percentage' => 30.0],
                '4' => ['count' => 15, 'percentage' => 30.0],
                '5' => ['count' => 5, 'percentage' => 10.0],
            ],
        ]);
    }

    public function questionText(): static
    {
        return $this->state(fn (array $attributes) => [
            'survey_question_id' => \App\Models\SurveyQuestion::factory(),
            'metric_type' => 'question_text',
            'metadata' => [
                'sample_responses' => [
                    fake()->sentence(),
                    fake()->sentence(),
                    fake()->sentence(),
                ],
            ],
        ]);
    }
}
