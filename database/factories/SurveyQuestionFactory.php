<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory
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
            'question_text' => fake()->sentence().'?',
            'question_type' => \App\Enums\QuestionType::YES_NO,
            'order' => fake()->numberBetween(0, 10),
            'is_required' => fake()->boolean(60),
            'configuration' => null,
        ];
    }

    /**
     * Indicate that the question is of type YES_NO.
     */
    public function yesNo(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => \App\Enums\QuestionType::YES_NO,
            'configuration' => null,
        ]);
    }

    /**
     * Indicate that the question is of type SCALE.
     */
    public function scale(int $min = 1, int $max = 5, ?string $minLabel = null, ?string $maxLabel = null): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => \App\Enums\QuestionType::SCALE,
            'configuration' => [
                'min' => $min,
                'max' => $max,
                'min_label' => $minLabel,
                'max_label' => $maxLabel,
            ],
        ]);
    }

    /**
     * Indicate that the question is of type TEXT.
     */
    public function text(int $maxLength = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => \App\Enums\QuestionType::TEXT,
            'configuration' => [
                'max_length' => $maxLength,
            ],
        ]);
    }

    /**
     * Indicate that the question is of type MULTIPLE_CHOICE.
     */
    public function multipleChoice(array $options = ['Option 1', 'Option 2', 'Option 3']): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => \App\Enums\QuestionType::MULTIPLE_CHOICE,
            'configuration' => [
                'options' => $options,
            ],
        ]);
    }

    /**
     * Indicate that the question is of type SINGLE_CHOICE.
     */
    public function singleChoice(array $options): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => \App\Enums\QuestionType::SINGLE_CHOICE,
            'configuration' => [
                'options' => $options,
            ],
        ]);
    }

    /**
     * Indicate that the question is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the question is optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }
}
