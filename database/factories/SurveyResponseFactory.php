<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SurveyResponse>
 */
class SurveyResponseFactory extends Factory
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
            'survey_question_id' => \App\Models\SurveyQuestion::factory(),
            'voter_id' => \App\Models\Voter::factory(),
            'answered_by' => \App\Models\User::factory(),
            'response_value' => fake()->sentence(),
            'responded_at' => now(),
        ];
    }

    /**
     * Indicate that the response is a Yes/No answer.
     */
    public function yesNoResponse(bool $isYes = true): static
    {
        return $this->state(fn (array $attributes) => [
            'response_value' => $isYes ? 'Sí' : 'No',
        ]);
    }

    /**
     * Indicate that the response is a scale value.
     */
    public function scaleResponse(int $value): static
    {
        return $this->state(fn (array $attributes) => [
            'response_value' => (string) $value,
        ]);
    }

    /**
     * Indicate that the response is a text answer.
     */
    public function textResponse(string $text): static
    {
        return $this->state(fn (array $attributes) => [
            'response_value' => $text,
        ]);
    }
}
